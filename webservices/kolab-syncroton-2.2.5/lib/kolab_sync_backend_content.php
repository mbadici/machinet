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
 * Kolab backend class for content storage
 */
class kolab_sync_backend_content extends kolab_sync_backend_common implements Syncroton_Backend_IContent
{
    protected $table_name     = 'syncroton_content';
    protected $interface_name = 'Syncroton_Model_IContent';


    /**
     * mark state as deleted. The state gets removed finally,
     * when the synckey gets validated during next sync.
     *
     * @param Syncroton_Model_IContent|string $id
     */
    public function delete($id)
    {
        $id = $id instanceof Syncroton_Model_IContent ? $id->id : $id;

        $result = $this->db->query('UPDATE ' . $this->table_name . ' SET is_deleted = 1 WHERE id = ?', array($id));

        if ($result = (bool) $this->db->affected_rows($result)) {
            unset($this->cache['content_folderstate']);
        }

        return $result;
    }

    /**
     * @param Syncroton_Model_IDevice|string $_deviceId
     * @param Syncroton_Model_IFolder|string $_folderId
     * @param string $_contentId
     * @return Syncroton_Model_IContent
     */
    public function getContentState($_deviceId, $_folderId, $_contentId)
    {
        $deviceId = $_deviceId instanceof Syncroton_Model_IDevice ? $_deviceId->id : $_deviceId;
        $folderId = $_folderId instanceof Syncroton_Model_IFolder ? $_folderId->id : $_folderId;

        $where[] = $this->db->quote_identifier('device_id') . ' = ' . $this->db->quote($deviceId);
        $where[] = $this->db->quote_identifier('folder_id') . ' = ' . $this->db->quote($folderId);
        $where[] = $this->db->quote_identifier('contentid') . ' = ' . $this->db->quote($_contentId);
        $where[] = $this->db->quote_identifier('is_deleted') . ' = 0';

        $select = $this->db->query('SELECT * FROM ' . $this->table_name .' WHERE ' . implode(' AND ', $where));
        $state  = $this->db->fetch_assoc($select);

        if (empty($state)) {
            throw new Syncroton_Exception_NotFound('Content not found');
        }

        return $this->get_object($state);
    }

    /**
     * get array of ids which got send to the client for a given class
     *
     * @param Syncroton_Model_IDevice|string $_deviceId
     * @param Syncroton_Model_IFolder|string $_folderId
     * @return array
     */
    public function getFolderState($_deviceId, $_folderId)
    {
        $deviceId = $_deviceId instanceof Syncroton_Model_IDevice ? $_deviceId->id : $_deviceId;
        $folderId = $_folderId instanceof Syncroton_Model_IFolder ? $_folderId->id : $_folderId;
        $cachekey = $deviceId . ':' . $folderId;

        // in Sync request we call this function twice in case when
        // folder state changed - use cache to skip at least one SELECT query
        if (isset($this->cache['content_folderstate'][$cachekey])) {
            return $this->cache['content_folderstate'][$cachekey];
        }

        $where[] = $this->db->quote_identifier('device_id') . ' = ' . $this->db->quote($deviceId);
        $where[] = $this->db->quote_identifier('folder_id') . ' = ' . $this->db->quote($folderId);
        $where[] = $this->db->quote_identifier('is_deleted') . ' = 0';

        $select = $this->db->query('SELECT contentid FROM ' . $this->table_name .' WHERE ' . implode(' AND ', $where));
        $result = array();

        while ($state = $this->db->fetch_assoc($select)) {
            $result[] = $state['contentid'];
        }

        return $this->cache['content_folderstate'][$cachekey] = $result;
    }

    /**
     * reset list of stored id
     *
     * @param Syncroton_Model_IDevice|string $_deviceId
     * @param Syncroton_Model_IFolder|string $_folderId
     */
    public function resetState($_deviceId, $_folderId)
    {
        $deviceId = $_deviceId instanceof Syncroton_Model_IDevice ? $_deviceId->id : $_deviceId;
        $folderId = $_folderId instanceof Syncroton_Model_IFolder ? $_folderId->id : $_folderId;
        $cachekey = $deviceId . ':' . $folderId;

        unset($this->cache['content_folderstate'][$cache_key]);

        $where[] = $this->db->quote_identifier('device_id') . ' = ' . $this->db->quote($deviceId);
        $where[] = $this->db->quote_identifier('folder_id') . ' = ' . $this->db->quote($folderId);

        $this->db->query('DELETE FROM ' . $this->table_name .' WHERE ' . implode(' AND ', $where));
    }
}
