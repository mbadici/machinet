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

class file_api_folder_create extends file_api_common
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

        // normal folder
        if (empty($this->args['driver']) || $this->args['driver'] == 'default') {
            list($driver, $path) = $this->api->get_driver($this->args['folder']);

            return $driver->folder_create($path);
        }

        // external storage (mount point)
        if (strpos($this->args['folder'], file_storage::SEPARATOR) !== false) {
            throw new Exception("Unable to mount external storage into a sub-folder", file_api_core::ERROR_CODE);
        }

        // check if driver is enabled
        $enabled = $this->rc->config->get('fileapi_drivers');

        if (!in_array($this->args['driver'], $enabled)) {
            throw new Exception("Unsupported storage driver", file_storage::ERROR_UNSUPPORTED);
        }

        // check if folder/mount point already exists
        $drivers = $this->api->get_drivers();
        foreach ($drivers as $driver) {
            if ($driver['title'] === $this->args['folder']) {
                throw new Exception("Specified folder already exists", file_storage::ERROR_FILE_EXISTS);
            }
        }

        $backend = $this->api->get_backend();
        $folders = $backend->folder_list();

        if (in_array($this->args['folder'], $folders)) {
            throw new Exception("Specified folder already exists", file_storage::ERROR_FILE_EXISTS);
        }

        // load driver
        $driver = $this->api->load_driver_object($this->args['driver']);
        $driver->configure($this->api->env, $this->args['folder']);

        // check if authentication works
        $data = $driver->driver_validate($this->args);

        $data['title']   = $this->args['folder'];
        $data['driver']  = $this->args['driver'];
        $data['enabled'] = 1;

        // optionally store (encrypted) passwords
        if (!empty($data['password']) && rcube_utils::get_boolean((string) $this->args['store_passwords'])) {
            $data['password'] = $this->api->encrypt($data['password']);
        }
        else {
            unset($data['password']);
        }

        // save the mount point info in config
        $backend->driver_create($data);
    }
}
