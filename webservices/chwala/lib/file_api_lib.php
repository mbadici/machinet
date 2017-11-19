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

/**
 * This class gives access to Chwala API as a library
 */
class file_api_lib extends file_api_core
{
    /**
     * API methods handler
     */
    public function __call($name, $arguments)
    {
        $this->init();

        switch ($name) {
            case 'configure':
                foreach (array_keys($this->env) as $name) {
                    if (isset($arguments[0][$name])) {
                        $this->env[$name] = $arguments[0][$name];
                    }
                }
                return $this->env;

            case 'mimetypes':
                return $this->supported_mimetypes();

            case 'file_list':
                $args = array(
                    'folder' => $arguments[0],
                );
                break;

            case 'file_create':
            case 'file_update':
                $args = array(
                    'file'         => $arguments[0],
                    'path'         => $arguments[1]['path'],
                    'content'      => $arguments[1]['content'],
                    'content-type' => $arguments[1]['type'],
                );
                break;

            case 'file_delete':
            case 'file_info':
                $args = array(
                    'file' => $arguments[0],
                );
                break;

            case 'file_copy':
            case 'file_move':
                $args = array(
                    'file' => array($arguments[0] => $arguments[1]),
                );
                break;

            case 'file_get':
                // override default action, we need only to support
                // writes to file handle
                list($driver, $path) = $this->get_driver($arguments[0]);
                $driver->file_get($path, $arguments[1], $arguments[2]);
                return;

            case 'folder_list':
                // no arguments
                $args = array();
                break;

            case 'folder_create':
            case 'folder_subscribe':
            case 'folder_unsubscribe':
            case 'folder_delete':
                $args = array(
                    'folder' => $arguments[0],
                );
                break;

            case 'folder_info':
                $args = array(
                    'folder'   => $arguments[0],
                    'rights'   => 1,
                    'sessions' => 1,
                );
                break;

            case 'folder_move':
                $args = array(
                    'folder' => $arguments[0],
                    'new'    => $arguments[1],
                );
                break;

            case 'lock_create':
            case 'lock_delete':
                $args        = $arguments[1];
                $args['uri'] = $arguments[0];
                break;

            case 'lock_list':
                $args = array(
                    'uri'         => $arguments[0],
                    'child_locks' => $arguments[1],
                );
                break;

            default:
                throw new Exception("Invalid method name", \file_storage::ERROR_UNSUPPORTED);
        }

        require_once __DIR__ . "/api/common.php";
        require_once __DIR__ . "/api/$name.php";

        $class   = "file_api_$name";
        $handler = new $class($this, $args);

        return $handler->handle();
    }

    /**
     * Configure environment (this is to be overriden by implementation class)
     */
    protected function init()
    {
    }
}
