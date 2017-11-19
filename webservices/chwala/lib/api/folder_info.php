<?php
/**
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2015, Kolab Systems AG                                |
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

class file_api_folder_info extends file_api_common
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

        $result = array(
            'folder' => $this->args['folder'],
        );

        if (!empty($this->args['rights']) && rcube_utils::get_boolean((string) $this->args['rights'])) {
            $result['rights'] = $this->folder_rights($this->args['folder']);
        }

        if (!empty($this->args['sessions']) && rcube_utils::get_boolean((string) $this->args['sessions'])) {
             $result['sessions'] = $this->folder_sessions($this->args['folder']);
        }

        return $result;
    }

    /**
     * Get editing sessions
     */
    protected function folder_sessions($folder)
    {
        $manticore = new file_manticore($this->api);
        return $manticore->session_find($folder);
    }
}
