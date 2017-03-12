<?php

/**
 +--------------------------------------------------------------------------+
 | Kolab Sync (ActiveSync for Kolab)                                        |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG <contact@kolabsys.com>         |
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
 * Database layer wrapper with transaction support
 */
class kolab_sync_db
{
    /**
     * the database adapter
     *
     * @var rcube_db
     */
    protected $db;


    public function __construct()
    {
        $this->db = rcube::get_instance()->get_dbh();
    }

    public function beginTransaction()
    {
        $query = 'BEGIN';

        $this->db->query($query);
    }

    public function commit()
    {
        $query = 'COMMIT';

        $this->db->query($query);
    }

    public function rollBack()
    {
        $query = 'ROLLBACK';

        $this->db->query($query);
    }
}
