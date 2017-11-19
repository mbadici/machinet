<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2014, Kolab Systems AG                                |
 |                                                                          |
 | This program is free software: you can redistribute it and/or modify     |
 | it under the terms of the GNU Affero General Public License as published |
 | by the Free Software Foundation, either version 3 of the License, or     |
 | (at your option) any later version.                                      |
 |                                                                          |
 | This program is distributed in the hope that it will be useful,          |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of           |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the             |
 | GNU Affero General Public License for more details.                      |
 |                                                                          |
 | You should have received a copy of the GNU Affero General Public License |
 | along with this program. If not, see <http://www.gnu.org/licenses/>      |
 +--------------------------------------------------------------------------+
 | Author: Aleksander Machniak <machniak@kolabsys.com>                      |
 +--------------------------------------------------------------------------+
*/

class file_api_file_move extends file_api_common
{
    /**
     * Request handler
     */
    public function handle()
    {
        parent::handle();

        if (!isset($this->args['file']) || $this->args['file'] === '') {
            throw new Exception("Missing file name", file_api_core::ERROR_CODE);
        }

        if (is_array($this->args['file'])) {
            if (empty($this->args['file'])) {
                throw new Exception("Missing file name", file_api_core::ERROR_CODE);
            }
        }
        else {
            if (!isset($this->args['new']) || $this->args['new'] === '') {
                throw new Exception("Missing new file name", file_api_core::ERROR_CODE);
            }

            $this->args['file'] = array($this->args['file'] => $this->args['new']);
        }

        $overwrite = rcube_utils::get_boolean((string) $this->args['overwrite']);
        $request   = $this instanceof file_api_file_copy ? 'file_copy' : 'file_move';
        $errors    = array();

        foreach ((array) $this->args['file'] as $file => $new_file) {
            if ($new_file === '') {
                throw new Exception("Missing new file name", file_api_core::ERROR_CODE);
            }

            if ($new_file === $file) {
                throw new Exception("Old and new file name is the same", file_api_core::ERROR_CODE);
            }

            list($driver, $path) = $this->api->get_driver($file);
            list($new_driver, $new_path) = $this->api->get_driver($new_file);

            try {
                // source and destination on the same driver...
                if ($driver == $new_driver) {
                    $driver->{$request}($path, $new_path);
                }
                // cross-driver move/copy...
                else {
                    // first check if destination file exists
                    $info = null;
                    try {
                        $info = $new_driver->file_info($new_path);
                    }
                    catch (Exception $e) { }

                    if (!empty($info)) {
                        throw new Exception("File exists", file_storage::ERROR_FILE_EXISTS);
                    }

                    // copy/move between backends
                    $this->file_copy($driver, $new_driver, $path, $new_path, $request == 'file_move');
                }
            }
            catch (Exception $e) {
                if ($e->getCode() == file_storage::ERROR_FILE_EXISTS) {
                    // delete existing file and do copy/move again
                    if ($overwrite) {
                        $new_driver->file_delete($new_path);

                        if ($driver == $new_driver) {
                            $driver->{$request}($path, $new_path);
                        }
                        else {
                            $this->file_copy($driver, $new_driver, $path, $new_path, $request == 'file_move');
                        }
                    }
                    // collect file-exists errors, so the client can ask a user
                    // what to do and skip or replace file(s)
                    else {
                        $errors[] = array(
                            'src' => $file,
                            'dst' => $new_file,
                        );
                    }
                }
                else {
                    throw $e;
                }
            }

            // Update manticore sessions
            if ($request == 'file_move') {
                $this->session_uri_update($file, $new_file, false);
            }
        }

        if (!empty($errors)) {
            return array('already_exist' => $errors);
        }
    }

    /**
     * File copy/move between storage backends
     */
    protected function file_copy($driver, $new_driver, $path, $new_path, $move = false)
    {
        // unable to put file on mount point
        if (strpos($new_path, file_storage::SEPARATOR) === false) {
            throw new Exception("Unable to copy/move file into specified location", file_api_core::ERROR_CODE);
        }

        // get the file from source location
        $fp = fopen('php://temp', 'w+');

        if (!$fp) {
            throw new Exception("Internal server error", file_api_core::ERROR_CODE);
        }

        $driver->file_get($path, null, $fp);

        rewind($fp);

        $chunk = stream_get_contents($fp, 102400);
        $type  = rcube_mime::file_content_type($chunk, $new_path, 'application/octet-stream', true);

        rewind($fp);

        // upload the file to new location
        $new_driver->file_create($new_path, array('content' => $fp, 'type' => $type));

        fclose($fp);

        // now we can remove the original file if it was a move action
        if ($move) {
            $driver->file_delete($path);
        }
    }
}
