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
 * Kolab backend class for device storage
 */
class kolab_sync_backend_device extends kolab_sync_backend_common implements Syncroton_Backend_IDevice
{
    protected $table_name     = 'syncroton_device';
    protected $interface_name = 'Syncroton_Model_IDevice';

    /**
     * Kolab Sync backend
     *
     * @var kolab_sync_backend
     */
    protected $backend;


    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->backend = kolab_sync_backend::get_instance();
    }

    /**
     * Create (register) a new device
     *
     * @param Syncroton_Model_IDevice $device Device object
     *
     * @return Syncroton_Model_IDevice Device object
     */
    public function create($device)
    {
        $device = parent::create($device);

        // Create device entry in kolab backend
        $created = $this->backend->device_create(array(
            'ID'    => $device->id,
            'TYPE'  => $device->devicetype,
            'ALIAS' => $device->friendlyname,
        ), $device->deviceid);

        if (!$created) {
            throw new Syncroton_Exception_NotFound('Device creation failed');
        }

        return $device;
    }

    /**
     * Delete a device
     *
     * @param Syncroton_Model_IDevice $device Device object
     *
     * @return bool True on success, False on failure
     */
    public function delete($device)
    {
        // Update IMAP annotation
        $this->backend->device_delete($device->deviceid);

        return parent::delete($device);
    }

    /**
     * Return device for a given user
     *
     * @param string $ownerid  User identifier
     * @param string $deviceid Device identifier
     *
     * @throws Syncroton_Exception_NotFound
     * @return Syncroton_Model_Device Device object
     */
    public function getUserDevice($ownerid, $deviceid)
    {
        $where[] = $this->db->quote_identifier('deviceid') . ' = ' . $this->db->quote($deviceid);
        $where[] = $this->db->quote_identifier('owner_id') . ' = ' . $this->db->quote($ownerid);

        $select = $this->db->query('SELECT * FROM ' . $this->table_name . ' WHERE ' . implode(' AND ', $where));
        $device = $this->db->fetch_assoc($select);

        if (empty($device)) {
            throw new Syncroton_Exception_NotFound('Device not found');
        }

        $device = $this->get_object($device);

        // Make sure device exists (could be deleted by the user)
        $dev = $this->backend->device_get($deviceid);
        if (empty($dev)) {
            // Remove the device (and related cached data) from database
            $this->delete($device);

            throw new Syncroton_Exception_NotFound('Device not found');
        }

        return $device;
    }
}
