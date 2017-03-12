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

class kolab_sync_backend
{
    /**
     * Singleton instace of kolab_sync_backend
     *
     * @var kolab_sync_backend
     */
    static protected $instance;

    protected $storage;
    protected $folder_meta;
    protected $folder_uids;
    protected $root_meta;

    static protected $types = array(
        1  => '',
        2  => 'mail.inbox',
        3  => 'mail.drafts',
        4  => 'mail.wastebasket',
        5  => 'mail.sentitems',
        6  => 'mail.outbox',
        7  => 'task.default',
        8  => 'event.default',
        9  => 'contact.default',
        10 => 'note.default',
        11 => 'journal.default',
        12 => 'mail',
        13 => 'event',
        14 => 'contact',
        15 => 'task',
        16 => 'journal',
        17 => 'note',
    );

    static protected $classes = array(
        Syncroton_Data_Factory::CLASS_CALENDAR => 'event',
        Syncroton_Data_Factory::CLASS_CONTACTS => 'contact',
        Syncroton_Data_Factory::CLASS_EMAIL    => 'mail',
        Syncroton_Data_Factory::CLASS_TASKS    => 'task',
    );

    const ROOT_MAILBOX  = 'INBOX';
//    const ROOT_MAILBOX  = '';
    const ASYNC_KEY     = '/private/vendor/kolab/activesync';
    const UID_KEY       = '/shared/vendor/cmu/cyrus-imapd/uniqueid';


    /**
     * This implements the 'singleton' design pattern
     *
     * @return kolab_sync_backend The one and only instance
     */
    static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new kolab_sync_backend;
            self::$instance->startup();  // init AFTER object was linked with self::$instance
        }

        return self::$instance;
    }


    /**
     * Class initialization
     */
    public function startup()
    {
        $this->storage = rcube::get_instance()->get_storage();

        // @TODO: reset cache? if we do this for every request the cache would be useless
        // There's no session here
        //$this->storage->clear_cache('mailboxes.', true);

        // set additional header used by libkolab
        $this->storage->set_options(array(
            // @TODO: there can be Roundcube plugins defining additional headers,
            // we maybe would need to add them here
            'fetch_headers' => 'X-KOLAB-TYPE X-KOLAB-MIME-VERSION',
            'skip_deleted'  => true,
            'threading'     => false,
        ));

        // Disable paging
        $this->storage->set_pagesize(999999);
    }


    /**
     * List known devices
     *
     * @return array Device list as hash array
     */
    public function devices_list()
    {
        if ($this->root_meta === null) {
            // @TODO: consider server annotation instead of INBOX
            if ($meta = $this->storage->get_metadata(self::ROOT_MAILBOX, self::ASYNC_KEY)) {
                $this->root_meta = $this->unserialize_metadata($meta[self::ROOT_MAILBOX][self::ASYNC_KEY]);
            }
            else {
                $this->root_meta = array();
            }
        }

        if (!empty($this->root_meta['DEVICE']) && is_array($this->root_meta['DEVICE'])) {
            return $this->root_meta['DEVICE'];
        }

        return array();
    }


    /**
     * Get list of folders available for sync
     *
     * @param string $deviceid  Device identifier
     * @param string $type      Folder type
     *
     * @return array|bool List of mailbox folders, False on backend failure
     */
    public function folders_list($deviceid, $type)
    {
        // get all folders of specified type
        $folders = (array) kolab_storage::list_folders('', '*', $type, false, $typedata);

        // get folders activesync config
        $folderdata = $this->folder_meta();

        if (!is_array($folders) || !is_array($folderdata)) {
            return false;
        }

        $folders_list = array();

        // check if folders are "subscribed" for activesync
        foreach ($folderdata as $folder => $meta) {
            if (empty($meta['FOLDER']) || empty($meta['FOLDER'][$deviceid])
                || empty($meta['FOLDER'][$deviceid]['S'])
            ) {
                continue;
            }

            if (!empty($type) && !in_array($folder, $folders)) {
                continue;
            }

            // Activesync folder identifier (serverId)
            $folder_type = $typedata[$folder];
            $folder_id   = self::folder_id($folder, $folder_type);

            $folders_list[$folder_id] = $this->folder_data($folder, $folder_type);
        }

        return $folders_list;
    }

    /**
     * Getter for folder metadata
     *
     * @return array|bool Hash array with meta data for each folder, False on backend failure
     */
    public function folder_meta()
    {
        if (!isset($this->folder_meta)) {
            $this->folder_meta = array();
            // get folders activesync config
            $folderdata = $this->storage->get_metadata("*", self::ASYNC_KEY);

            if (!is_array($folderdata)) {
                return false;
            }

            foreach ($folderdata as $folder => $meta) {
                if ($asyncdata = $meta[self::ASYNC_KEY]) {
                    if ($metadata = $this->unserialize_metadata($asyncdata)) {
                        $this->folder_meta[$folder] = $metadata;
                    }
                }
            }
        }

        return $this->folder_meta;
    }


    /**
     * Creates folder and subscribes to the device
     *
     * @param string $name      Folder name (UTF7-IMAP)
     * @param int    $type      Folder (ActiveSync) type
     * @param string $deviceid  Device identifier
     *
     * @return bool True on success, False on failure
     */
    public function folder_create($name, $type, $deviceid)
    {
        if ($this->storage->folder_exists($name)) {
            $created = true;
        }
        else {
            $type    = self::type_activesync2kolab($type);
            $created = kolab_storage::folder_create($name, $type, true);
        }

        if ($created) {
            // Set ActiveSync subscription flag
            $this->folder_set($name, $deviceid, 1);

            return true;
        }

        return false;
    }


    /**
     * Renames a folder
     *
     * @param string $old_name  Old folder name (UTF7-IMAP)
     * @param string $new_name  New folder name (UTF7-IMAP)
     * @param int    $type      Folder (ActiveSync) type
     * @param string $deviceid  Device identifier
     *
     * @return bool True on success, False on failure
     */
    public function folder_rename($old_name, $new_name, $type, $deviceid)
    {
        $moved = kolab_storage::folder_rename($old_name, $new_name);

        if ($moved) {
            // UnSet ActiveSync subscription flag
            $this->folder_set($old_name, $deviceid, 0);
            // Set ActiveSync subscription flag
            $this->folder_set($new_name, $deviceid, 1);

            return true;
        }

        return false;
    }


    /**
     * Deletes folder
     *
     * @param string $name      Folder name (UTF7-IMAP)
     * @param string $deviceid  Device identifier
     *
     */
    public function folder_delete($name, $deviceid)
    {
        unset($this->folder_meta[$name]);

        return kolab_storage::folder_delete($name);
    }


    /**
     * Sets ActiveSync subscription flag on a folder
     *
     * @param string $name      Folder name (UTF7-IMAP)
     * @param string $deviceid  Device identifier
     * @param int    $flag      Flag value (0|1|2)
     */
    public function folder_set($name, $deviceid, $flag)
    {
        if (empty($deviceid)) {
            return false;
        }

        // get folders activesync config
        $metadata = $this->folder_meta();

        if (!is_array($metadata)) {
            return false;
        }

        $metadata = $metadata[$name];

        if ($flag)  {
            if (empty($metadata)) {
                $metadata = array();
            }

            if (empty($metadata['FOLDER'])) {
                $metadata['FOLDER'] = array();
            }

            if (empty($metadata['FOLDER'][$deviceid])) {
                $metadata['FOLDER'][$deviceid] = array();
            }

            // Z-Push uses:
            //  1 - synchronize, no alarms
            //  2 - synchronize with alarms
            $metadata['FOLDER'][$deviceid]['S'] = $flag;
        }

        if (!$flag) {
            unset($metadata['FOLDER'][$deviceid]['S']);

            if (empty($metadata['FOLDER'][$deviceid])) {
                unset($metadata['FOLDER'][$deviceid]);
            }

            if (empty($metadata['FOLDER'])) {
                unset($metadata['FOLDER']);
            }

            if (empty($metadata)) {
                $metadata = null;
            }
        }

        // Return if nothing's been changed
        if (!self::data_array_diff($this->folder_meta[$name], $metadata)) {
            return true;
        }

        $this->folder_meta[$name] = $metadata;

        return $this->storage->set_metadata($name, array(
            self::ASYNC_KEY => $this->serialize_metadata($metadata)));
    }


    public function device_get($id)
    {
        $devices_list = $this->devices_list();

        $result = $devices_list[$id];

        return $result;
    }

    /**
     * Registers new device on server
     *
     * @param array  $device  Device data
     * @param string $id      Device ID
     *
     * @return bool True on success, False on failure
     */
    public function device_create($device, $id)
    {
        // Fill local cache
        $this->devices_list();

        // Some devices create dummy devices with name "validate" (#1109)
        // This device entry is used in two initial requests, but later
        // the device registers a real name. We can remove this dummy entry
        // on new device creation
        $this->device_delete('validate');

        // Old Kolab_ZPush device parameters
        // MODE:  -1 | 0 | 1  (not set | flatmode | foldermode)
        // TYPE:  device type string
        // ALIAS: user-friendly device name

        // Syncroton (kolab_sync_backend_device) uses
        // ID:    internal identifier in syncroton database
        // TYPE:  device type string
        // ALIAS: user-friendly device name

        $metadata = $this->root_meta;
        $metadata['DEVICE'][$id] = $device;
        $metadata = array(self::ASYNC_KEY => $this->serialize_metadata($metadata));

        $result = $this->storage->set_metadata(self::ROOT_MAILBOX, $metadata);

        if ($result) {
            // Update local cache
            $this->root_meta['DEVICE'][$id] = $device;

            // subscribe default set of folders
            $this->device_init_subscriptions($id);
        }

        return $result;
    }

    /**
     * Device update.
     *
     * @param array  $device  Device data
     * @param string $id      Device ID
     *
     * @return bool True on success, False on failure
     */
    public function device_update($device, $id)
    {
        $devices_list = $this->devices_list();
        $old_device   = $devices_list[$id];

        if (!$old_device) {
            return false;
        }

        // Do nothing if nothing is changed
        if (!self::data_array_diff($old_device, $device)) {
            return true;
        }

        $device = array_merge($old_device, $device);

        $metadata = $this->root_meta;
        $metadata['DEVICE'][$id] = $device;
        $metadata = array(self::ASYNC_KEY => $this->serialize_metadata($metadata));

        $result = $this->storage->set_metadata(self::ROOT_MAILBOX, $metadata);

        if ($result) {
            // Update local cache
            $this->root_meta['DEVICE'][$id] = $device;
        }

        return $result;
    }


    /**
     * Device delete.
     *
     * @param string $id  Device ID
     *
     * @return bool True on success, False on failure
     */
    public function device_delete($id)
    {
        $device = $this->device_get($id);

        if (!$device) {
            return false;
        }

        unset($this->root_meta['DEVICE'][$id], $this->root_meta['FOLDER'][$id]);

        if (empty($this->root_meta['DEVICE'])) {
            unset($this->root_meta['DEVICE']);
        }
        if (empty($this->root_meta['FOLDER'])) {
            unset($this->root_meta['FOLDER']);
        }

        $metadata = $this->serialize_metadata($this->root_meta);
        $metadata = array(self::ASYNC_KEY => $metadata);

        // update meta data
        $result = $this->storage->set_metadata(self::ROOT_MAILBOX, $metadata);

        if ($result) {
            // remove device annotation for every folder
            foreach ($this->folder_meta() as $folder => $meta) {
                // skip root folder (already handled above)
                if ($folder == self::ROOT_MAILBOX)
                    continue;

                if (!empty($meta['FOLDER']) && isset($meta['FOLDER'][$id])) {
                    unset($meta['FOLDER'][$id]);

                    if (empty($meta['FOLDER'])) {
                        unset($this->folder_meta[$folder]['FOLDER']);
                        unset($meta['FOLDER']);
                    }
                    if (empty($meta)) {
                        unset($this->folder_meta[$folder]);
                        $meta = null;
                    }

                    $metadata = array(self::ASYNC_KEY => $this->serialize_metadata($meta));
                    $res = $this->storage->set_metadata($folder, $metadata);

                    if ($res && $meta) {
                        $this->folder_meta[$folder] = $meta;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Subscribe default set of folders on device registration
     */
    private function device_init_subscriptions($deviceid)
    {
        // INBOX always exists
        $this->folder_set('INBOX', $deviceid, 1);

        $supported_types = array(
            'mail.drafts',
            'mail.wastebasket',
            'mail.sentitems',
            'mail.outbox',
            'event.default',
            'contact.default',
            'task.default',
            'event',
            'contact',
            'task'
        );

        // This default set can be extended by adding following values:
        $modes = array(
            'SUB_PERSONAL' => 1, // all subscribed folders in personal namespace
            'ALL_PERSONAL' => 2, // all folders in personal namespace
            'SUB_OTHER'    => 4, // all subscribed folders in other users namespace
            'ALL_OTHER'    => 8, // all folders in other users namespace
            'SUB_SHARED'   => 16, // all subscribed folders in shared namespace
            'ALL_SHARED'   => 32, // all folders in shared namespace
        );

        $rcube   = rcube::get_instance();
        $config  = $rcube->config;
        $mode    = (int) $config->get('activesync_init_subscriptions');
        $folders = array();

        // Subscribe to default folders
        $foldertypes = kolab_storage::folders_typedata();

        if (!empty($foldertypes)) {
            $_foldertypes = array_intersect($foldertypes, $supported_types);

            // get default folders
            foreach ($_foldertypes as $folder => $type) {
                // only personal folders
                if ($this->storage->folder_namespace($folder) == 'personal') {
                    $flag = preg_match('/^(event|task)/', $type) ? 2 : 1;
                    $this->folder_set($folder, $deviceid, $flag);
                    $folders[] = $folder;
                }
            }
        }

        // we're in default mode, exit
        if (!$mode) {
            return;
        }

        // below we support additionally all mail folders
        $supported_types[] = 'mail';
        $supported_types[] = 'mail.junkemail';

        // get configured special folders
        $special_folders = array();
        $map             = array(
            'drafts' => 'mail.drafts',
            'junk'   => 'mail.junkemail',
            'sent'   => 'mail.sentitems',
            'trash'  => 'mail.wastebasket',
        );

        foreach ($map as $folder => $type) {
            if ($folder = $config->get($folder . '_mbox')) {
                $special_folders[$folder] = $type;
            }
        }

        // get folders list(s)
        if (($mode & $modes['ALL_PERSONAL']) || ($mode & $modes['ALL_OTHER']) || ($mode & $modes['ALL_SHARED'])) {
            $all_folders = $this->storage->list_folders();
            if (($mode & $modes['SUB_PERSONAL']) || ($mode & $modes['SUB_OTHER']) || ($mode & $modes['SUB_SHARED'])) {
                $subscribed_folders = $this->storage->list_folders_subscribed();
            }
        }
        else {
            $all_folders = $this->storage->list_folders_subscribed();
        }

        foreach ($all_folders as $folder) {
            // folder already subscribed
            if (in_array($folder, $folders)) {
                continue;
            }

            $type = $foldertypes[$folder] ?: 'mail';
            if ($type == 'mail' && isset($special_folders[$folder])) {
                $type = $special_folders[$folder];
            }

            if (!in_array($type, $supported_types)) {
                continue;
            }

            $ns = strtoupper($this->storage->folder_namespace($folder));

            // subscribe the folder according to configured mode
            // and folder namespace/subscription status
            if (($mode & $modes["ALL_$ns"])
                || (($mode & $modes["SUB_$ns"])
                    && (!isset($subscribed_folders) || in_array($folder, $subscribed_folders)))
            ) {
                $flag = preg_match('/^(event|task)/', $type) ? 2 : 1;
                $this->folder_set($folder, $deviceid, $flag);
            }
        }
    }

    /**
     * Helper method to decode saved IMAP metadata
     */
    private function unserialize_metadata($str)
    {
        if (!empty($str)) {
            // Support old Z-Push annotation format
            if ($str[0] != '{') {
                $str = base64_decode($str);
            }
            $data = json_decode($str, true);
            return $data;
        }

        return null;
    }

    /**
     * Helper method to encode IMAP metadata for saving
     */
    private function serialize_metadata($data)
    {
        if (!empty($data) && is_array($data)) {
            $data = json_encode($data);
//            $data = base64_encode($data);
            return $data;
        }

        return null;
    }

    /**
     * Returns Kolab folder type for specified ActiveSync type ID
     */
    public static function type_activesync2kolab($type)
    {
        if (!empty(self::$types[$type])) {
            return self::$types[$type];
        }

        return '';
    }

    /**
     * Returns ActiveSync folder type for specified Kolab type
     */
    public static function type_kolab2activesync($type)
    {
        if ($key = array_search($type, self::$types)) {
            return $key;
        }

        return key(self::$types);
    }

    /**
     * Returns Kolab folder type for specified ActiveSync class name
     */
    public static function class_activesync2kolab($class)
    {
        if (!empty(self::$classes[$class])) {
            return self::$classes[$class];
        }

        return '';
    }

    private function folder_data($folder, $type)
    {
        // Folder name parameters
        $delim = $this->storage->get_hierarchy_delimiter();
        $items = explode($delim, $folder);
        $name  = array_pop($items);

        // Folder UID
        $folder_id = $this->folder_id($folder, $type);

        // Folder type
        $type = self::type_kolab2activesync($type);
        // fix type, if there's no type annotation it's detected as UNKNOWN
        // we'll use 'mail' (12) or 'mail.inbox' (2)
        if ($type == 1) {
            $type = $folder == 'INBOX' ? 2 : 12;
        }

        // Syncroton folder data array
        return array(
            'serverId'    => $folder_id,
            'parentId'    => count($items) ? self::folder_id(implode($delim, $items)) : 0,
            'displayName' => rcube_charset::convert($name, 'UTF7-IMAP', kolab_sync::CHARSET),
            'type'        => $type,
        );
    }

    /**
     * Builds folder ID based on folder name
     */
    public function folder_id($name, $type = null)
    {
        // ActiveSync expects folder identifiers to be max.64 characters
        // So we can't use just folder name

        if ($name === '' || !is_string($name)) {
            return null;
        }

        if (isset($this->folder_uids[$name])) {
            return $this->folder_uids[$name];
        }

/*
        @TODO: For now uniqueid annotation doesn't work, we will create UIDs by ourselves.
               There's one inconvenience of this solution: folder name/type change
               would be handled in ActiveSync as delete + create.

        // get folders unique identifier
        $folderdata = $this->storage->get_metadata($name, self::UID_KEY);

        if ($folderdata && !empty($folderdata[$name])) {
            $uid = $folderdata[$name][self::UID_KEY];
            return $this->folder_uids[$name] = $uid;
        }
*/
        // Add type to folder UID hash, so type change can be detected by Syncroton
        $uid = $name . '!!' . ($type !== null ? $type : kolab_storage::folder_type($name));
        $uid = md5($uid);

        return $this->folder_uids[$name] = $uid;
    }

    /**
     * Returns IMAP folder name
     *
     * @param string $id        Folder identifier
     * @param string $deviceid  Device dentifier
     *
     * @return string Folder name (UTF7-IMAP)
     */
    public function folder_id2name($id, $deviceid)
    {
        // check in cache first
        if (!empty($this->folder_uids)) {
            if (($name = array_search($id, $this->folder_uids)) !== false) {
                return $name;
            }
        }

/*
        @TODO: see folder_id()

        // get folders unique identifier
        $folderdata = $this->storage->get_metadata('*', self::UID_KEY);

        foreach ((array)$folderdata as $folder => $data) {
            if (!empty($data[self::UID_KEY])) {
                $uid = $data[self::UID_KEY];
                $this->folder_uids[$folder] = $uid;
                if ($uid == $id) {
                    $name = $folder;
                }
            }
        }
*/
        // get all folders of specified type
        $folderdata = $this->folder_meta();

        if (!is_array($folderdata)) {
            return null;
        }

        // check if folders are "subscribed" for activesync
        foreach ($folderdata as $folder => $meta) {
            if (empty($meta['FOLDER']) || empty($meta['FOLDER'][$deviceid])
                || empty($meta['FOLDER'][$deviceid]['S'])
            ) {
                continue;
            }

            $uid = self::folder_id($folder);
            $this->folder_uids[$folder] = $uid;

            if ($uid == $id) {
                $name = $folder;
            }
        }

        return $name;
    }

    /**
     */
    public function modseq_set($deviceid, $folderid, $synctime, $data)
    {
        $synctime = $synctime->format('Ymdhis');
        $rcube    = rcube::get_instance();
        $db       = $rcube->get_dbh();

        $this->modseq[$deviceid][$folderid][$synctime] = $data;

        $data = json_encode($data);

        $db->query("UPDATE syncroton_modseq"
            ." SET data = ?"
            ." WHERE device_id = ? AND folder_id = ? AND synctime = ?",
            $data, $deviceid, $folderid, $synctime);

        if (!$db->affected_rows()) {
            $db->query("INSERT INTO syncroton_modseq (device_id, folder_id, synctime, data)"
                ." VALUES (?, ?, ?, ?)",
                $deviceid, $folderid, $synctime, $data);
        }
    }

    public function modseq_get($deviceid, $folderid, $synctime)
    {
        $synctime = $synctime->format('Ymdhis');

        if (!isset($this->modseq[$deviceid]) || !isset($this->modseq[$deviceid][$folderid])
            || !isset($this->modseq[$deviceid][$synctime])
        ) {
            $rcube = rcube::get_instance();
            $db    = $rcube->get_dbh();

            $db->limitquery("SELECT data, synctime FROM syncroton_modseq"
                ." WHERE device_id = ? AND folder_id = ? AND synctime <= ?"
                ." ORDER BY synctime DESC",
                0, 2, $deviceid, $folderid, $synctime);

            if ($row = $db->fetch_assoc()) {
                $current = $row['synctime'];
                $this->modseq[$deviceid][$folderid][$synctime] = json_decode($row['data']);

                // Cleanup: remove old records (older than 12 hours from the last one)
                if (($row = $db->fetch_assoc()) && $row['synctime'] < $current - 86400) {
                    $db->query("DELETE FROM syncroton_modseq"
                        ." WHERE device_id = ? AND folder_id = ? AND synctime < ?",
                    $deviceid, $folderid, $current - 86400);
                }
            }
        }

        return @$this->modseq[$deviceid][$folderid][$synctime];
    }

    /**
     * Compares two arrays
     *
     * @param array $array1
     * @param array $array2
     *
     * @return bool True if arrays differs, False otherwise
     */
    private static function data_array_diff($array1, $array2)
    {
        if (!is_array($array1) || !is_array($array2)) {
            return $array1 != $array2;
        }

        if (count($array1) != count($array2)) {
            return true;
        }

        foreach ($array1 as $key => $val) {
            if (!array_key_exists($key, $array2)) {
                return true;
            }
            if ($val !== $array2[$key]) {
                return true;
            }
        }

        return false;
    }
}
