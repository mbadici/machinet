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

class file_api_folder_move extends file_api_common
{
    /**
     * Request handler
     */
    public function handle()
    {
        parent::handle();

        if (!isset($this->args['folder']) || $this->args['folder'] === '') {
            throw new Exception("Missing folder name", file_api_core::ERROR_CODE);
        }

        if (!isset($this->args['new']) || $this->args['new'] === '') {
            throw new Exception("Missing destination folder name", file_api_core::ERROR_CODE);
        }

        if ($this->args['new'] === $this->args['folder']) {
            return;
        }

        list($src_driver, $src_path, $cfg) = $this->api->get_driver($this->args['folder']);
        list($dst_driver, $dst_path)       = $this->api->get_driver($this->args['new']);

        // source folder is a mount point (driver title)...
        if ($src_driver->title() === $this->args['folder']) {
            // ...rename
            if (strpos($this->args['new'], file_storage::SEPARATOR) === false) {
                // destination folder is an existing mount point
                if (!strlen($dst_path)) {
                    throw new Exception("Destination folder already exists", file_api_core::ERROR_CODE);
                }

                return $this->driver_rename($src_driver, $this->args['new'], $cfg);
            }

            throw new Exception("Unsupported operation", file_api_core::ERROR_CODE);
        }

        // cross-driver move
        if ($src_driver != $dst_driver) {
            // destination folder is an existing mount point
            if (!strlen($dst_path)) {
                throw new Exception("Destination folder already exists", file_api_core::ERROR_CODE);
            }

            $this->folder_move_to_other_driver($src_driver, $src_path, $dst_driver, $dst_path);
        }
        else {
            $src_driver->folder_move($src_path, $dst_path);
        }

        // Update manticore
        $this->session_uri_update($this->args['folder'], $this->args['new'], true);
    }

    /**
     * Move folder between two external storage locations
     */
    protected function folder_move_to_other_driver($src_driver, $src_path, $dst_driver, $dst_path)
    {
        $src_folders = $src_driver->folder_list();
        $dst_folders = $dst_driver->folder_list();

        // first check if destination folder not exists
        if (in_array($dst_path, $dst_folders)) {
            throw new Exception("Destination folder already exists", file_api_core::ERROR_CODE);
        }

        // now recursively create/delete folders and copy their content
        $this->move_folder_with_content($src_driver, $src_path, $dst_driver, $dst_path, $src_folders);

        // now we can delete the folder
        $src_driver->folder_delete($src_path);
    }

    /**
     * Recursively moves folder and it's content to another location
     */
    protected function move_folder_with_content($src_driver, $src_path, $dst_driver, $dst_path, $src_folders)
    {
        // create folder
        $dst_driver->folder_create($dst_path);

        foreach ($src_driver->file_list($src_path) as $filename => $file) {
            $this->file_copy($src_driver, $dst_driver, $filename, $dst_path . file_storage::SEPARATOR . $file['name']);
        }

        // sub-folders...
        foreach ($src_folders as $folder) {
            if (strpos($folder, $src_path . file_storage::SEPARATOR) === 0
                && strpos($folder, file_storage::SEPARATOR, strlen($src_path) + 2) === false
            ) {
                $destination = $dst_path . file_storage::SEPARATOR . substr($folder, strlen($src_path) + 1);
                $this->move_folder_with_content($src_driver, $folder, $dst_driver, $destination, $src_folders);
            }
        }
    }

    /**
     * File move between storage backends
     */
    protected function file_copy($src_driver, $dst_driver, $src_path, $dst_path)
    {
        // unable to put file on mount point
        if (strpos($dst_path, file_storage::SEPARATOR) === false) {
            throw new Exception("Unable to move file into specified location", file_api_core::ERROR_CODE);
        }

        // get the file from source location
        $fp = fopen('php://temp', 'w+');

        if (!$fp) {
            throw new Exception("Internal server error", file_api_core::ERROR_CODE);
        }

        $src_driver->file_get($src_path, null, $fp);

        rewind($fp);

        $chunk = stream_get_contents($fp, 102400);
        $type  = rcube_mime::file_content_type($chunk, $dst_path, 'application/octet-stream', true);

        rewind($fp);

        // upload the file to new location
        $dst_driver->file_create($dst_path, array('content' => $fp, 'type' => $type));

        fclose($fp);
    }

    /**
     * External storage (mount point) rename
     */
    protected function driver_rename($driver, $new_name, $config)
    {
        $backend = $this->api->get_backend();
        $folders = $backend->folder_list();

        // first check if destination folder not exists
        if (in_array($new_name, $folders)) {
            throw new Exception("Destination folder already exists", file_api_core::ERROR_CODE);
        }

        $title           = $driver->title();
        $config['title'] = $new_name;

        // store passwords if it was already stored
        if (empty($config['password']) || $config['password'] != '%u') {
            unset($config['password']);
        }

        $backend->driver_update($title, $config);

        // authenticate again to set session password
        if (empty($config['password']) || $config['password'] != '%u') {
            $auth         = $driver->auth_info();
            list($driver) = $this->api->get_driver($new_name);

            $driver->authenticate($auth['username'], $auth['password']);
        }
    }
}
