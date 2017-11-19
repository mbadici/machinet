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

class file_api_file_list extends file_api_common
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

        $params = array(
            'reverse' => rcube_utils::get_boolean((string) $this->args['reverse']),
        );

        if (!empty($this->args['sort'])) {
            $params['sort'] = strtolower($this->args['sort']);
        }

        if (!empty($this->args['search'])) {
            $params['search'] = $this->args['search'];
            if (!is_array($params['search'])) {
                $params['search'] = array('name' => $params['search']);
            }
        }

        list($driver, $path) = $this->api->get_driver($this->args['folder']);

        // mount point contains only folders
        if (!strlen($path)) {
            return array();
        }

        // add mount point prefix to file paths
        if ($path != $this->args['folder']) {
            $params['prefix'] = substr($this->args['folder'], 0, -strlen($path));
        }

        return $driver->file_list($path, $params);
    }
}
