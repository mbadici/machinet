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

class file_api_common
{
    protected $api;
    protected $rc;
    protected $args = array();


    public function __construct($api, $args = array())
    {
        $this->rc   = rcube::get_instance();
        $this->api  = $api;
        $this->args = (array) $args;
    }

    /**
     * Request handler
     */
    public function handle()
    {
        // GET arguments
        if (!empty($_GET)) {
            foreach (array_keys($_GET) as $key) {
                $this->args[$key] = &$_GET[$key];
            }
        }

        // POST arguments (JSON)
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $post = file_get_contents('php://input');
            $this->args += (array) json_decode($post, true);
            unset($post);
        }

        // disable script execution time limit, so we can handle big files
        @set_time_limit(360);
    }

    /**
     * File uploads handler
     */
    protected function upload()
    {
        $files = array();

        if (is_array($_FILES['file']['tmp_name'])) {
            foreach ($_FILES['file']['tmp_name'] as $i => $filepath) {
                if ($err = $_FILES['file']['error'][$i]) {
                    if ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) {
                        $maxsize = ini_get('upload_max_filesize');
                        $maxsize = $this->show_bytes(parse_bytes($maxsize));

                        throw new Exception("Maximum file size ($maxsize) exceeded", file_api_core::ERROR_CODE);
                    }

                    throw new Exception("File upload failed", file_api_core::ERROR_CODE);
                }

                $files[] = array(
                    'path' => $filepath,
                    'name' => $_FILES['file']['name'][$i],
                    'size' => filesize($filepath),
                    'type' => rcube_mime::file_content_type($filepath, $_FILES['file']['name'][$i], $_FILES['file']['type']),
                );
            }
        }
        else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            // if filesize exceeds post_max_size then $_FILES array is empty,
            if ($maxsize = ini_get('post_max_size')) {
                $maxsize = $this->show_bytes(parse_bytes($maxsize));
                throw new Exception("Maximum file size ($maxsize) exceeded", file_api_core::ERROR_CODE);
            }

            throw new Exception("File upload failed", file_api_core::ERROR_CODE);
        }

        return $files;
    }

    /**
     * Return built-in viewer opbject for specified mimetype
     *
     * @return object Viewer object
     */
    protected function find_viewer($mimetype)
    {
        $dir   = RCUBE_INSTALL_PATH . 'lib/viewers';
        $files = array();

        // First get viewers and sort by name to get priority
        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match('/^([a-z0-9_]+)\.php$/i', $file, $matches)) {
                    $files[$matches[1]] = $dir . '/' . $file;
                }
            }
            closedir($handle);
        }

        ksort($files);

        foreach ($files as $name => $file) {
            include_once $file;
            $class  = 'file_viewer_' . $name;
            $viewer = new $class($this->api);

            if ($viewer->supports($mimetype)) {
                return $viewer;
            }
        }
    }

    /**
     * Parse driver metadata information
     */
    protected function parse_metadata($metadata, $default = false)
    {
        if ($default) {
            unset($metadata['form']);
            $metadata['name'] .= ' (' . $this->api->translate('localstorage') . ')';
        }

        // localize form labels
        foreach ($metadata['form'] as $key => $val) {
            $label = $this->api->translate('form.' . $val);
            if (strpos($label, 'form.') !== 0) {
                $metadata['form'][$key] = $label;
            }
        }

        return $metadata;
    }

    /**
     * Get folder rights
     */
    protected function folder_rights($folder)
    {
        list($driver, $path) = $this->api->get_driver($folder);

        $rights = $driver->folder_rights($path);
        $result = array();
        $map    = array(
            file_storage::ACL_READ  => 'read',
            file_storage::ACL_WRITE => 'write',
        );

        foreach ($map as $key => $value) {
            if ($rights & $key) {
                $result[] = $value;
            }
        }

        return $result;
    }

    /**
     * Update document session on file/folder move
     */
    protected function session_uri_update($from, $to, $is_folder = false)
    {
        // check Manticore/WOPI support. Note: we don't use config->get('fileapi_manticore')
        // here as it may be not properly set if backend driver wasn't initialized yet
        $capabilities = $this->api->capabilities(false);

        if (!empty($capabilities['WOPI'])) {
            $document = new file_wopi($this->api);
        }
        else if (!empty($capabilities['MANTICORE'])) {
            $document = new file_manticore($this->api);
        }

        if (!empty($document)) {
            $document->session_uri_update($from, $to, $is_folder);
        }
    }
}
