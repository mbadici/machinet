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

class file_api_folder_auth extends file_api_common
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

        list($driver, $path, $driver_config) = $this->api->get_driver($this->args['folder']);

        if (empty($driver) || $driver->title() === '') {
            throw new Exception("Unknown folder", file_api_core::ERROR_CODE);
        }

        // check if authentication works
        $meta = $driver->driver_metadata();
        $data = array_fill_keys(array_keys($meta['form']), '');
        $data = array_merge($data, $this->args);
        $data = $driver->driver_validate($data);

        // optionally store (encrypted) passwords
        if (!empty($data['password']) && rcube_utils::get_boolean((string) $this->args['store_passwords'])) {
            $data['password'] = $this->api->encrypt($data['password']);
        }
        else {
            unset($data['password']);
            unset($driver_config['password']);
        }

        // save changed data
        foreach (array_keys($meta['form']) as $key) {
            if ($meta['form_values'][$key] != $data[$key]) {
                // update driver config
                $driver_config = array_merge($driver_config, $data);

                $backend = $this->api->get_backend();
                $backend->driver_update($this->args['folder'], $driver_config);
                break;
            }
        }

        $result = array('folder' => $this->args['folder']);

        // get list if folders if requested
        if (rcube_utils::get_boolean((string) $this->args['list'])) {
            $prefix         = $this->args['folder'] . file_storage::SEPARATOR;
            $result['list'] = array();

            foreach ($driver->folder_list() as $folder) {
                $result['list'][] = $prefix . $folder;
            }
        }

        return $result;
    }
}
