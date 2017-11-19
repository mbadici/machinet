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

class file_api_file_upload extends file_api_common
{
    /**
     * Request handler
     */
    public function handle()
    {
        parent::handle();

        // for Opera upload frame response cannot be application/json
        $this->api->output_type = file_api_core::OUTPUT_HTML;

        if (!isset($this->args['folder']) || $this->args['folder'] === '') {
            throw new Exception("Missing folder name", file_api_core::ERROR_CODE);
        }

        $uploads = $this->upload();
        $result  = array();

        list($driver, $path) = $this->api->get_driver($this->args['folder']);

        if (strlen($path)) {
            $path .= file_storage::SEPARATOR;
        }

        foreach ($uploads as $file) {
            $driver->file_create($path . $file['name'], $file);

            unset($file['path']);
            $result[$file['name']] = array(
                'type' => $file['type'],
                'size' => $file['size'],
            );
        }

        return $result;
    }
}
