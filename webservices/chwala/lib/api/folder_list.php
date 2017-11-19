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

class file_api_folder_list extends file_api_common
{
    /**
     * Request handler
     */
    public function handle()
    {
        parent::handle();

        // List parameters
        $params = array('type' => 0);
        if (!empty($this->args['unsubscribed']) && rcube_utils::get_boolean((string) $this->args['unsubscribed'])) {
            $params['type'] |= file_storage::FILTER_UNSUBSCRIBED;
        }
        if (!empty($this->args['writable']) && rcube_utils::get_boolean((string) $this->args['writable'])) {
            $params['type'] |= file_storage::FILTER_WRITABLE;
        }
        if (isset($this->args['search']) && strlen($this->args['search'])) {
            $params['search'] = $this->args['search'];
            $search = mb_strtoupper($this->args['search']);
        }
        if (!empty($this->args['permissions']) && rcube_utils::get_boolean((string) $this->args['permissions'])) {
            $params['extended']    = true;
            $params['permissions'] = true;
        }

        // get folders from default driver
        $backend = $this->api->get_backend();
        $folders = $this->folder_list($backend, $params);

        // old result format
        if ($this->api->client_version() < 2) {
            return $folders;
        }

        $drivers  = $this->api->get_drivers(true);
        $has_more = false;
        $errors   = array();

        // get folders from external sources
        foreach ($drivers as $driver) {
            $title  = $driver->title();
            $prefix = $title . file_storage::SEPARATOR;

            // folder exists in main source, replace it with external one
            if (($idx = array_search($title, $folders)) !== false) {
                foreach ($folders as $idx => $folder) {
                    if (is_array($folder)) {
                        $folder = $folder['folder'];
                    }
                    if ($folder == $title || strpos($folder, $prefix) === 0) {
                        unset($folders[$idx]);
                    }
                }
            }

            if (!isset($search) || strpos(mb_strtoupper($title), $search) !== false) {
                $has_more = count($folders) > 0;
                $folder   = $params['extended'] ? array('folder' => $title) : $title;

                if ($params['permissions'] || ($params['type'] & file_storage::FILTER_WRITABLE)) {
                    if ($readonly = !($driver->folder_rights('') & file_storage::ACL_WRITE)) {
                        if ($params['permissions']) {
                            $folder['readonly'] = true;
                        }
                    }
                }
                else {
                    $readonly = false;
                }

                if (!$readonly || !($params['type'] & file_storage::FILTER_WRITABLE)) {
                    $folders[] = $folder;
                }
            }

            if ($driver != $backend) {
                try {
                    foreach ($this->folder_list($driver, $params) as $folder) {
                        if (is_array($folder)) {
                            $folder['folder'] = $prefix . $folder['folder'];
                        }
                        else {
                            $folder = $prefix . $folder;
                        }

                        $folders[] = $folder;
                        $has_more  = true;
                    }
                }
                catch (Exception $e) {
                    if ($e->getCode() == file_storage::ERROR_NOAUTH) {
                        // inform UI about to ask user for credentials
                        $errors[$title] = $this->parse_metadata($driver->driver_metadata());
                    }
                }
            }
        }

        // re-sort the list
        if ($has_more) {
            usort($folders, array('file_utils', 'sort_folder_comparator'));
        }

        return array(
            'list'        => $folders,
            'auth_errors' => $errors,
        );
    }

    /**
     * Wrapper for folder_list() method on specified driver
     */
    protected function folder_list($driver, $params)
    {
        if ($params['type'] & file_storage::FILTER_UNSUBSCRIBED) {
            $caps = $driver->capabilities();
            if (empty($caps[file_storage::CAPS_SUBSCRIPTIONS])) {
                return array();
            }
        }

        return $driver->folder_list($params);
    }
}
