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

class file_api_quota extends file_api_common
{
    /**
     * Request handler
     */
    public function handle()
    {
        parent::handle();

        list($driver, $path) = $this->api->get_driver($this->args['folder']);

        $quota = $driver->quota($path);

        if (!$quota['total']) {
            $quota['percent'] = 0;
        }
        else if ($quota['total']) {
            if (!isset($quota['percent'])) {
                $quota['percent'] = min(100, round(($quota['used']/max(1, $quota['total']))*100));
            }
        }

        return $quota;
    }
}
