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
 * Kolab backend class for the folder state storage
 */
class kolab_sync_backend_folder extends kolab_sync_backend_common implements Syncroton_Backend_IFolder
{
    protected $table_name     = 'syncroton_folder';
    protected $interface_name = 'Syncroton_Model_IFolder';

    /**
     * Delete all stored folder ids for a given device
     *
     * @param Syncroton_Model_Device|string $deviceid Device object or identifier
     */
    public function resetState($deviceid)
    {
        $device_id = $deviceid instanceof Syncroton_Model_IDevice ? $deviceid->id : $deviceid;

        $where[] = $this->db->quote_identifier('device_id') . ' = ' . $this->db->quote($device_id);

        $this->db->query('DELETE FROM ' . $this->table_name .' WHERE ' . implode(' AND ', $where));
    }

    /**
     * Get array of ids which got send to the client for a given class
     *
     * @param Syncroton_Model_Device|string $deviceid Device object or identifier
     * @param string                        $class    Class name
     *
     * @return array List of object identifiers
     */
    public function getFolderState($deviceid, $class)
    {
        $device_id = $deviceid instanceof Syncroton_Model_IDevice ? $deviceid->id : $deviceid;

        $where[] = $this->db->quote_identifier('device_id') . ' = ' . $this->db->quote($device_id);
        $where[] = $this->db->quote_identifier('class')     . ' = ' . $this->db->quote($class);

        $select = $this->db->query('SELECT * FROM ' . $this->table_name .' WHERE ' . implode(' AND ', $where));
        $result = array();

        while ($folder = $this->db->fetch_assoc($select)) {
            $result[$folder['folderid']] = $this->get_object($folder);
        }

        return $result;
    }

    /**
     * Get folder
     *
     * @param Syncroton_Model_Device|string  $deviceid Device object or identifier
     * @param string                         $folderid Folder identifier
     *
     * @return Syncroton_Model_IFolder Folder object
     */
    public function getFolder($deviceid, $folderid)
    {
        $device_id = $deviceid instanceof Syncroton_Model_IDevice ? $deviceid->id : $deviceid;

        $where[] = $this->db->quote_identifier('device_id') . ' = ' . $this->db->quote($device_id);
        $where[] = $this->db->quote_identifier('folderid')  . ' = ' . $this->db->quote($folderid);

        $select = $this->db->query('SELECT * FROM ' . $this->table_name .' WHERE ' . implode(' AND ', $where));
        $folder = $this->db->fetch_assoc($select);

        if (empty($folder)) {
            throw new Syncroton_Exception_NotFound('Folder not found');
        }

        return $this->get_object($folder);
    }

    /**
     * (non-PHPdoc)
     * @see kolab_sync_backend_common::from_camelcase()
     */
    protected function from_camelcase($string)
    {
        switch ($string) {
            case 'displayName':
            case 'parentId':
                return strtolower($string);
                break;

            case 'serverId':
                return 'folderid';
                break;

            default:
                return parent::from_camelcase($string);
                break;
        }
    }

    /**
     * (non-PHPdoc)
     * @see kolab_sync_backend_common::to_camelcase()
     */
    protected function to_camelcase($string, $ucFirst = true)
    {
        switch ($string) {
            case 'displayname':
                return 'displayName';
                break;

            case 'parentid':
                return 'parentId';
                break;

            case 'folderid':
                return 'serverId';
                break;

            default:
                return parent::to_camelcase($string, $ucFirst);
                break;
        }
    }
}
