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
class kolab_sync_backend_state extends kolab_sync_backend_common implements Syncroton_Backend_ISyncState
{
    protected $table_name     = 'syncroton_synckey';
    protected $interface_name = 'Syncroton_Model_ISyncState';

    /**
     * Create new sync state of a folder
     *
     * @param Syncroton_Model_ISyncState $object              State object
     * @param bool                       $keep_previous_state Don't remove other states
     *
     * @return Syncroton_Model_SyncState
     */
    public function create($object, $keep_previous_state = true)
    {
        $object = parent::create($object);

        if ($keep_previous_state !== true) {
            // remove all other synckeys
            $this->_deleteOtherStates($object);
        }

        return $object;
    }

    /**
     * Deletes states other than specified one
     */
    protected function _deleteOtherStates(Syncroton_Model_ISyncState $state)
    {
        // remove all other synckeys
        $where[] = $this->db->quote_identifier('device_id') . ' = '  . $this->db->quote($state->deviceId);
        $where[] = $this->db->quote_identifier('type')      . ' = '  . $this->db->quote($state->type);
        $where[] = $this->db->quote_identifier('counter')   . ' <> ' . $this->db->quote($state->counter);

        $this->db->query('DELETE FROM ' . $this->table_name .' WHERE ' . implode(' AND ', $where));
    }

    /**
     * @see kolab_sync_backend_common::object_to_array()
     */
    protected function object_to_array($object)
    {
        $data = parent::object_to_array($object);

        if (is_array($object->pendingdata)) {
            $data['pendingdata'] = json_encode($object->pendingdata);
        }

        return $data;
    }

    /**
     * @see kolab_sync_backend_common::get_object()
     */
    protected function get_object($data)
    {
        $object = parent::get_object($data);

        if ($object->pendingdata) {
            $object->pendingdata = json_decode($object->pendingdata);
        }

        return $object;
    }

    /**
     * Returns the latest sync state
     *
     * @param Syncroton_Model_IDevice|string $deviceid Device object or identifier
     * @param Syncroton_Model_IFolder|string $folderid Folder object or identifier
     *
     * @return Syncroton_Model_SyncState
     */
    public function getSyncState($deviceid, $folderid)
    {
        $device_id = $deviceid instanceof Syncroton_Model_IDevice ? $deviceid->id : $deviceid;
        $folder_id = $folderid instanceof Syncroton_Model_IFolder ? $folderid->id : $folderid;

        $where[] = $this->db->quote_identifier('device_id') . ' = ' . $this->db->quote($device_id);
        $where[] = $this->db->quote_identifier('type')      . ' = ' . $this->db->quote($folder_id);

        $select = $this->db->limitquery('SELECT * FROM ' . $this->table_name . ' WHERE ' . implode(' AND ', $where)
            .' ORDER BY counter DESC', 0, 1);

        $state = $this->db->fetch_assoc($select);

        if (empty($state)) {
            throw new Syncroton_Exception_NotFound('SyncState not found');
        }

        return $this->get_object($state);
    }

    /**
     * Delete all stored synckeys of given type
     *
     * @param Syncroton_Model_IDevice|string $deviceid Device object or identifier
     * @param Syncroton_Model_IFolder|string $folderid Folder object or identifier
     */
    public function resetState($deviceid, $folderid)
    {
        $device_id = $deviceid instanceof Syncroton_Model_IDevice ? $deviceid->id : $deviceid;
        $folder_id = $folderid instanceof Syncroton_Model_IFolder ? $folderid->id : $folderid;

        $where[] = $this->db->quote_identifier('device_id') . ' = ' . $this->db->quote($device_id);
        $where[] = $this->db->quote_identifier('type')      . ' = ' . $this->db->quote($folder_id);

        $this->db->query('DELETE FROM ' . $this->table_name .' WHERE ' . implode(' AND ', $where));
    }

    /**
     * Validates specified sync state by checking for existance of newer keys
     *
     * @param Syncroton_Model_IDevice|string $deviceid Device object or identifier
     * @param Syncroton_Model_IFolder|string $folderid Folder object or identifier
     * @param int                            $sync_key State key
     *
     * @return Syncroton_Model_SyncState
     */
    public function validate($deviceid, $folderid, $sync_key)
    {
        $device_id = $deviceid instanceof Syncroton_Model_IDevice ? $deviceid->id : $deviceid;
        $folder_id = $folderid instanceof Syncroton_Model_IFolder ? $folderid->id : $folderid;
        $states    = array();

        // get sync data
        // we'll get all records, thanks to this we'll be able to
        // skip _deleteOtherStates() call below (one DELETE query less)
        $where['device_id'] = $this->db->quote_identifier('device_id') . ' = ' . $this->db->quote($device_id);
        $where['type']      = $this->db->quote_identifier('type')      . ' = ' . $this->db->quote($folder_id);

        $select = $this->db->query('SELECT * FROM ' . $this->table_name .' WHERE ' . implode(' AND ', $where));

        while ($row = $this->db->fetch_assoc($select)) {
            $states[$row['counter']] = $this->get_object($row);
        }

        // last state not found
        if (empty($states) || empty($states[$sync_key])) {
            return false;
        }

        $state = $states[$sync_key];
        $next  = max(array_keys($states));

        $where = array();
        $where['device_id']  = $this->db->quote_identifier('device_id')  . ' = ' . $this->db->quote($device_id);
        $where['folder_id']  = $this->db->quote_identifier('folder_id')  . ' = ' . $this->db->quote($folder_id);
        $where['is_deleted'] = $this->db->quote_identifier('is_deleted') . ' = 1';

        // found more recent synckey => the last sync response got not received by the client
        if ($next > $sync_key) {
            $where['synckey'] = $this->db->quote_identifier('creation_synckey') . ' = ' . $this->db->quote($state->counter);
            // undelete entries marked as deleted in syncroton_content table
            $this->db->query('UPDATE syncroton_content SET is_deleted = 0 WHERE ' . implode(' AND ', $where));

            // remove entries added during latest sync in syncroton_content table
            unset($where['is_deleted']);
            $where['synckey'] = $this->db->quote_identifier('creation_synckey') . ' > ' . $this->db->quote($state->counter);

            $this->db->query('DELETE FROM syncroton_content WHERE ' . implode(' AND ', $where));
        }
        else {
            // finaly delete all entries marked for removal in syncroton_content table
            $this->db->query('DELETE FROM syncroton_content WHERE ' . implode(' AND ', $where));
        }

        // remove all other synckeys
        if (count($states) > 1) {
            $this->_deleteOtherStates($state);
        }

        return $state;
    }
}
