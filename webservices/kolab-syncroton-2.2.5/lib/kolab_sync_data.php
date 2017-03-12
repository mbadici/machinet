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
 * Base class for Syncroton data backends
 */
abstract class kolab_sync_data implements Syncroton_Data_IData
{
    /**
     * ActiveSync protocol version
     *
     * @var int
     */
    protected $asversion = 0;

    /**
     * information about the current device
     *
     * @var Syncroton_Model_IDevice
     */
    protected $device;

    /**
     * timestamp to use for all sync requests
     *
     * @var DateTime
     */
    protected $syncTimeStamp;

    /**
     * name of model to use
     *
     * @var string
     */
    protected $modelName;

    /**
     * type of the default folder
     *
     * @var int
     */
    protected $defaultFolderType;

    /**
     * default container for new entries
     *
     * @var string
     */
    protected $defaultFolder;

    /**
     * type of user created folders
     *
     * @var int
     */
    protected $folderType;

    /**
     * Internal cache for kolab_storage folder objects
     *
     * @var array
     */
    protected $folders = array();

    /**
     * Internal cache for IMAP folders list
     *
     * @var array
     */
    protected $imap_folders = array();

    /**
     * Timezone
     *
     * @var string
     */
    protected $timezone;

    /**
     * List of device types with multiple folders support
     *
     * @var array
     */
    protected $ext_devices = array(
        'iphone',
        'ipad',
        'thundertine',
        'windowsphone',
        'playbook',
    );

    const RESULT_OBJECT = 0;
    const RESULT_UID    = 1;
    const RESULT_COUNT  = 2;


    /**
     * Recurrence types
     */
    const RECUR_TYPE_DAILY          = 0;     // Recurs daily.
    const RECUR_TYPE_WEEKLY         = 1;     // Recurs weekly
    const RECUR_TYPE_MONTHLY        = 2;     // Recurs monthly
    const RECUR_TYPE_MONTHLY_DAYN   = 3;     // Recurs monthly on the nth day
    const RECUR_TYPE_YEARLY         = 5;     // Recurs yearly
    const RECUR_TYPE_YEARLY_DAYN    = 6;     // Recurs yearly on the nth day

    /**
     * Day of week constants
     */
    const RECUR_DOW_SUNDAY      = 1;
    const RECUR_DOW_MONDAY      = 2;
    const RECUR_DOW_TUESDAY     = 4;
    const RECUR_DOW_WEDNESDAY   = 8;
    const RECUR_DOW_THURSDAY    = 16;
    const RECUR_DOW_FRIDAY      = 32;
    const RECUR_DOW_SATURDAY    = 64;
    const RECUR_DOW_LAST        = 127;      //  The last day of the month. Used as a special value in monthly or yearly recurrences.

    /**
     * Mapping of recurrence types
     *
     * @var array
     */
    protected $recurTypeMap = array(
        self::RECUR_TYPE_DAILY        => 'DAILY',
        self::RECUR_TYPE_WEEKLY       => 'WEEKLY',
        self::RECUR_TYPE_MONTHLY      => 'MONTHLY',
        self::RECUR_TYPE_MONTHLY_DAYN => 'MONTHLY',
        self::RECUR_TYPE_YEARLY       => 'YEARLY',
        self::RECUR_TYPE_YEARLY_DAYN  => 'YEARLY',
    );

    /**
     * Mapping of weekdays
     * NOTE: ActiveSync uses a bitmask
     *
     * @var array
     */
    protected $recurDayMap = array(
        'SU'  => self::RECUR_DOW_SUNDAY,
        'MO'  => self::RECUR_DOW_MONDAY,
        'TU'  => self::RECUR_DOW_TUESDAY,
        'WE'  => self::RECUR_DOW_WEDNESDAY,
        'TH'  => self::RECUR_DOW_THURSDAY,
        'FR'  => self::RECUR_DOW_FRIDAY,
        'SA'  => self::RECUR_DOW_SATURDAY,
    );


    /**
     * the constructor
     *
     * @param Syncroton_Model_IDevice $device
     * @param DateTime                $syncTimeStamp
     */
    public function __construct(Syncroton_Model_IDevice $device, DateTime $syncTimeStamp)
    {
        $this->backend       = kolab_sync_backend::get_instance();
        $this->device        = $device;
        $this->asversion     = floatval($device->acsversion);
        $this->syncTimeStamp = $syncTimeStamp;

        $this->defaultRootFolder = $this->defaultFolder . '::Syncroton';

        // set internal timezone of kolab_format to user timezone
        try {
            $this->timezone = rcube::get_instance()->config->get('timezone', 'GMT');
            kolab_format::$timezone = new DateTimeZone($this->timezone);
        }
        catch (Exception $e) {
            //rcube::raise_error($e, true);
            $this->timezone = 'GMT';
            kolab_format::$timezone = new DateTimeZone('GMT');
        }
    }

    /**
     * return list of supported folders for this backend
     *
     * @return array
     */
    public function getAllFolders()
    {
        $list = array();

        // device supports multiple folders ?
        if (in_array(strtolower($this->device->devicetype), $this->ext_devices)) {
            // get the folders the user has access to
            $list = $this->listFolders();
        }
        else if ($default = $this->getDefaultFolder()) {
            $list = array($default['serverId'] => $default);
        }

        // getAllFolders() is called only in FolderSync
        // throw Syncroton_Exception_Status_FolderSync exception
        if (!is_array($list)) {
            throw new Syncroton_Exception_Status_FolderSync(Syncroton_Exception_Status_FolderSync::FOLDER_SERVER_ERROR);
        }

        foreach ($list as $idx => $folder) {
            $list[$idx] = new Syncroton_Model_Folder($folder);
        }

        return $list;
    }

    /**
     * Retrieve folders which were modified since last sync
     *
     * @param DateTime $startTimeStamp
     * @param DateTime $endTimeStamp
     *
     * @return array List of folders
     */
    public function getChangedFolders(DateTime $startTimeStamp, DateTime $endTimeStamp)
    {
        return array();
    }

    /**
     * Returns default folder for current class type.
     */
    protected function getDefaultFolder()
    {
        // Check if there's any folder configured for sync
        $folders = $this->listFolders();

        if (empty($folders)) {
            return $folders;
        }

        foreach ($folders as $folder) {
            if ($folder['type'] == $this->defaultFolderType) {
                $default = $folder;
                break;
            }
        }

        // Return first on the list if there's no default
        if (empty($default)) {
            $key     = array_shift(array_keys($folders));
            $default = $folders[$key];
            // make sure the type is default here
            $default['type'] = $this->defaultFolderType;
        }

        // Remember real folder ID and set ID/name to root folder
        $default['realid']      = $default['serverId'];
        $default['serverId']    = $this->defaultRootFolder;
        $default['displayName'] = $this->defaultFolder;

        return $default;
    }

    /**
     * Creates a folder
     */
    public function createFolder(Syncroton_Model_IFolder $folder)
    {
        $parentid     = $folder->parentId;
        $type         = $folder->type;
        $display_name = $folder->displayName;

        if ($parentid) {
            $parent = $this->backend->folder_id2name($parentid, $this->device->deviceid);
        }

        $name = rcube_charset::convert($display_name, kolab_sync::CHARSET, 'UTF7-IMAP');

        if ($parent !== null) {
            $rcube   = rcube::get_instance();
            $storage = $rcube->get_storage();
            $delim   = $storage->get_hierarchy_delimiter();
            $name    = $parent . $delim . $name;
        }

        // Create IMAP folder
        $result = $this->backend->folder_create($name, $type, $this->device->deviceid);

        if ($result) {
            $folder->serverId = $this->backend->folder_id($name);
            return $folder;
        }

        // @TODO: throw exception
    }

    /**
     * Updates a folder
     */
    public function updateFolder(Syncroton_Model_IFolder $folder)
    {
        $parentid     = $folder->parentId;
        $type         = $folder->type;
        $display_name = $folder->displayName;
        $old_name     = $this->backend->folder_id2name($folder->serverId, $this->device->deviceid);

        if ($parentid) {
            $parent = $this->backend->folder_id2name($parentid, $this->device->deviceid);
        }

        $name = rcube_charset::convert($display_name, kolab_sync::CHARSET, 'UTF7-IMAP');

        if ($parent !== null) {
            $rcube   = rcube::get_instance();
            $storage = $rcube->get_storage();
            $delim   = $storage->get_hierarchy_delimiter();
            $name    = $parent . $delim . $name;
        }

        // Rename/move IMAP folder
        if ($name == $old_name) {
            $result = true;
            // @TODO: folder type change?
        }
        else {
            $result = $this->backend->folder_rename($old_name, $name, $type, $this->device->deviceid);
        }

        if ($result) {
            $folder->serverId = $this->backend->folder_id($name);
            return $folder;
        }

        // @TODO: throw exception
    }

    /**
     * Deletes a folder
     */
    public function deleteFolder($folder)
    {
        if ($folder instanceof Syncroton_Model_IFolder) {
            $folder = $folder->serverId;
        }

        $name = $this->backend->folder_id2name($folder, $this->device->deviceid);

        // @TODO: throw exception
        return $this->backend->folder_delete($name, $this->device->deviceid);
    }

    /**
     * Empty folder (remove all entries and optionally subfolders)
     *
     * @param string $folderId Folder identifier
     * @param array  $options  Options
     */
    public function emptyFolderContents($folderid, $options)
    {
        $folders = $this->extractFolders($folderid);

        foreach ($folders as $folderid) {
            $foldername = $this->backend->folder_id2name($folderid, $this->device->deviceid);

            if ($foldername === null) {
                continue;
            }

            $folder = $this->getFolderObject($foldername);

            // Remove all entries
            $folder->delete_all();

            // Remove subfolders
            if (!empty($options['deleteSubFolders'])) {
                $list = $this->listFolders($folderid);
                foreach ($list as $folderid => $folder) {
                    $foldername = $this->backend->folder_id2name($folderid, $this->device->deviceid);

                    if ($foldername === null) {
                        continue;
                    }

                    $folder = $this->getFolderObject($foldername);

                    // Remove all entries
                    $folder->delete_all();
                }
            }
        }
    }

    /**
     * Moves object into another location (folder)
     *
     * @param string $srcFolderId Source folder identifier
     * @param string $serverId    Object identifier
     * @param string $dstFolderId Destination folder identifier
     *
     * @throws Syncroton_Exception_Status
     * @return string New object identifier
     */
    public function moveItem($srcFolderId, $serverId, $dstFolderId)
    {
        $item = $this->getObject($srcFolderId, $serverId, $folder);

        if (!$item || !$folder) {
            throw new Syncroton_Exception_Status_MoveItems(Syncroton_Exception_Status_MoveItems::INVALID_SOURCE);
        }

        $dstname = $this->backend->folder_id2name($dstFolderId, $this->device->deviceid);

        if ($dstname === null) {
            throw new Syncroton_Exception_Status_MoveItems(Syncroton_Exception_Status_MoveItems::INVALID_DESTINATION);
        }

        if (!$folder->move($serverId, $dstname)) {
            throw new Syncroton_Exception_Status_MoveItems(Syncroton_Exception_Status_MoveItems::INVALID_SOURCE);
        }

        return $item['uid'];
    }

    /**
     * Add entry
     *
     * @param string                 $folderId Folder identifier
     * @param Syncroton_Model_IEntry $entry    Entry object
     *
     * @return string ID of the created entry
     */
    public function createEntry($folderId, Syncroton_Model_IEntry $entry)
    {
        $entry = $this->toKolab($entry, $folderId);
        $entry = $this->createObject($folderId, $entry);

        if (empty($entry)) {
            throw new Syncroton_Exception_Status_Sync(Syncroton_Exception_Status_Sync::SYNC_SERVER_ERROR);
        }

        return $entry['uid'];
    }

    /**
     * update existing entry
     *
     * @param string           $folderId
     * @param string           $serverId
     * @param SimpleXMLElement $entry
     *
     * @return string ID of the updated entry
     */
    public function updateEntry($folderId, $serverId, Syncroton_Model_IEntry $entry)
    {
        $oldEntry = $this->getObject($folderId, $serverId);

        if (empty($oldEntry)) {
            throw new Syncroton_Exception_NotFound('id not found');
        }

        $entry = $this->toKolab($entry, $folderId, $oldEntry);
        $entry = $this->updateObject($folderId, $serverId,  $entry);

        if (empty($entry)) {
            throw new Syncroton_Exception_Status_Sync(Syncroton_Exception_Status_Sync::SYNC_SERVER_ERROR);
        }

        return $entry['uid'];
    }

    /**
     * delete entry
     *
     * @param  string  $folderId
     * @param  string  $serverId
     * @param  array   $collectionData
     */
    public function deleteEntry($folderId, $serverId, $collectionData)
    {
        $deleted = $this->deleteObject($folderId, $serverId);

        if (!$deleted) {
            throw new Syncroton_Exception_Status_Sync(Syncroton_Exception_Status_Sync::SYNC_SERVER_ERROR);
        }
    }


    public function getFileReference($fileReference)
    {
        // to be implemented by Email data class
        // @TODO: throw "unimplemented" exception here?
    }


    /**
     * Search for existing entries
     *
     * @param string $folderid
     * @param array  $filter
     * @param int    $result_type  Type of the result (see RESULT_* constants)
     *
     * @return array|int  Search result as count or array of uids/objects
     */
    protected function searchEntries($folderid, $filter = array(), $result_type = self::RESULT_UID)
    {
        if ($folderid == $this->defaultRootFolder) {
            $folders = $this->listFolders();

            if (!is_array($folders)) {
                throw new Syncroton_Exception_Status(Syncroton_Exception_Status::SERVER_ERROR);
            }

            $folders = array_keys($folders);
        }
        else {
            $folders = array($folderid);
        }

        // there's a PHP Warning from kolab_storage if $filter isn't an array
        if (empty($filter)) {
            $filter = array();
        }

        $result = $result_type == self::RESULT_COUNT ? 0 : array();
        $found  = 0;

        foreach ($folders as $folderid) {
            $foldername = $this->backend->folder_id2name($folderid, $this->device->deviceid);

            if ($foldername === null) {
                continue;
            }

            $folder = $this->getFolderObject($foldername);

            if (!$folder) {
                continue;
            }

            $found++;
            $error = false;

            switch ($result_type) {
            case self::RESULT_COUNT:
                $count = $folder->count($filter);

                if ($count === null || $count === false) {
                    $error = true;
                }
                else {
                    $result += (int) $count;
                }
                break;

            case self::RESULT_UID:
                $uids = $folder->get_uids($filter);

                if (!is_array($uids)) {
                    $error = true;
                }
                else {
                    $result = array_merge($result, $uids);
                }
                break;

            case self::RESULT_OBJECT:
            default:
                $objects = $folder->select($filter);

                if (!is_array($objects)) {
                    $error = true;
                }
                else {
                    $result = array_merge($result, $objects);
                }
            }

            if ($error) {
                throw new Syncroton_Exception_Status(Syncroton_Exception_Status::SERVER_ERROR);
            }
        }

        if (!$found) {
            throw new Syncroton_Exception_Status(Syncroton_Exception_Status::SERVER_ERROR);
        }

        return $result;
    }

    /**
     * Returns filter query array according to specified ActiveSync FilterType
     *
     * @param int $filter_type  Filter type
     *
     * @param array  Filter query
     */
    protected function filter($filter_type = 0)
    {
        // overwrite by child class according to specified type
        return array();
    }

    /**
     * get all entries changed between two dates
     *
     * @param string   $folderId
     * @param DateTime $start
     * @param DateTime $end
     * @param int      $filterType
     *
     * @return array
     */
    public function getChangedEntries($folderId, DateTime $start, DateTime $end = null, $filter_type = null)
    {
        $filter   = $this->filter($filter_type);
        $filter[] = array('changed', '>', $start);

        if ($end) {
            $filter[] = array('changed', '<=', $end);
        }

        return $this->searchEntries($folderId, $filter, self::RESULT_UID);
    }

    /**
     * get count of entries changed between two dates
     *
     * @param string   $folderId
     * @param DateTime $start
     * @param DateTime $end
     * @param int      $filterType
     *
     * @return int
     */
    public function getChangedEntriesCount($folderId, DateTime $start, DateTime $end = null, $filter_type = null)
    {
        $filter   = $this->filter($filter_type);
        $filter[] = array('changed', '>', $start);

        if ($end) {
            $filter[] = array('changed', '<=', $end);
        }

        return $this->searchEntries($folderId, $filter, self::RESULT_COUNT);
    }

    /**
     * get id's of all entries available on the server
     *
     * @param string $folderId
     * @param int    $filterType
     *
     * @return array
     */
    public function getServerEntries($folder_id, $filter_type)
    {
        $filter = $this->filter($filter_type);
        $result = $this->searchEntries($folder_id, $filter, self::RESULT_UID);

        return $result;
    }

    /**
     * get count of all entries available on the server
     *
     * @param string $folderId
     * @param int $filterType
     *
     * @return int
     */
    public function getServerEntriesCount($folder_id, $filter_type)
    {
        $filter = $this->filter($filter_type);
        $result = $this->searchEntries($folder_id, $filter, self::RESULT_COUNT);

        return $result;
    }

    /**
     * Returns number of changed objects in the backend folder
     *
     * @param Syncroton_Backend_IContent $contentBackend
     * @param Syncroton_Model_IFolder    $folder
     * @param Syncroton_Model_ISyncState $syncState
     *
     * @return int
     */
    public function getCountOfChanges(Syncroton_Backend_IContent $contentBackend, Syncroton_Model_IFolder $folder, Syncroton_Model_ISyncState $syncState)
    {
        $allClientEntries = $contentBackend->getFolderState($this->device, $folder);
        $allServerEntries = $this->getServerEntries($folder->serverId, $folder->lastfiltertype);
        $changedEntries   = $this->getChangedEntriesCount($folder->serverId, $syncState->lastsync, null, $folder->lastfiltertype);
        $addedEntries     = array_diff($allServerEntries, $allClientEntries);
        $deletedEntries   = array_diff($allClientEntries, $allServerEntries);

        return count($addedEntries) + count($deletedEntries) + $changedEntries;
    }

    /**
     * Returns true if any data got modified in the backend folder
     *
     * @param Syncroton_Backend_IContent $contentBackend
     * @param Syncroton_Model_IFolder    $folder
     * @param Syncroton_Model_ISyncState $syncState
     *
     * @return bool
     */
    public function hasChanges(Syncroton_Backend_IContent $contentBackend, Syncroton_Model_IFolder $folder, Syncroton_Model_ISyncState $syncState)
    {
        try {
            if ($this->getChangedEntriesCount($folder->serverId, $syncState->lastsync, null, $folder->lastfiltertype)) {
                return true;
            }

            $allClientEntries = $contentBackend->getFolderState($this->device, $folder);

            // @TODO: Consider looping over all folders here, not in getServerEntries() and
            // getChangedEntriesCount(). This way we could break the loop and not check all folders
            // or at least skip redundant cache sync of the same folder
            $allServerEntries = $this->getServerEntries($folder->serverId, $folder->lastfiltertype);

            $addedEntries   = array_diff($allServerEntries, $allClientEntries);
            $deletedEntries = array_diff($allClientEntries, $allServerEntries);

            return count($addedEntries) > 0 || count($deletedEntries) > 0;
        }
        catch (Exception $e) {
            // return "no changes" if something failed
            return false;
        }
    }

    /**
     * Fetches the entry from the backend
     */
    protected function getObject($folderid, $entryid, &$folder = null)
    {
        $folders = $this->extractFolders($folderid);

        foreach ($folders as $folderid) {
            $foldername = $this->backend->folder_id2name($folderid, $this->device->deviceid);

            if ($foldername === null) {
                continue;
            }

            $folder = $this->getFolderObject($foldername);

            if ($folder && ($object = $folder->get_object($entryid))) {
                $object['_folderid'] = $folderid;

                return $object;
            }
        }
    }

    /**
     * Saves the entry on the backend
     */
    protected function createObject($folderid, $data)
    {
        if ($folderid == $this->defaultRootFolder) {
            $default  = $this->getDefaultFolder();

            if (!is_array($default)) {
                return null;
            }

            $folderid = isset($default['realid']) ? $default['realid'] : $default['serverId'];
        }

        $foldername = $this->backend->folder_id2name($folderid, $this->device->deviceid);
        $folder     = $this->getFolderObject($foldername);

        if ($folder && $folder->save($data)) {
            return $data;
        }
    }

    /**
     * Updates the entry on the backend
     */
    protected function updateObject($folderid, $entryid, $data)
    {
        $object = $this->getObject($folderid, $entryid);

        if ($object) {
            $folder = $this->getFolderObject($object['_mailbox']);

            if ($folder && $folder->save($data)) {
                return $data;
            }
        }
    }

    /**
     * Removes the entry from the backend
     */
    protected function deleteObject($folderid, $entryid)
    {
        $object = $this->getObject($folderid, $entryid);

        if ($object) {
            $folder = $this->getFolderObject($object['_mailbox']);
            return $folder && $folder->delete($entryid);
        }

        // object doesn't exist, confirm deletion
        return true;
    }

    /**
     * Returns internal folder IDs
     *
     * @param string $folderid Folder identifier
     *
     * @return array List of folder identifiers
     */
    protected function extractFolders($folderid)
    {
        if ($folderid instanceof Syncroton_Model_IFolder) {
            $folderid = $folderid->serverId;
        }

        if ($folderid == $this->defaultRootFolder) {
            $folders = $this->listFolders();

            if (!is_array($folders)) {
                return null;
            }

            $folders = array_keys($folders);
        }
        else {
            $folders = array($folderid);
        }

        return $folders;
    }

    /**
     * List of all IMAP folders (or subtree)
     *
     * @param string $parentid Parent folder identifier
     *
     * @return array List of folder identifiers
     */
    protected function listFolders($parentid = null)
    {
        if (empty($this->imap_folders)) {
            $this->imap_folders = $this->backend->folders_list($this->device->deviceid, $this->modelName);
        }

        if ($parentid === null) {
            return $this->imap_folders;
        }

        $folders = array();
        $parents = array($parentid);

        foreach ($this->imap_folders as $folder_id => $folder) {
            if ($folder['parentId'] && in_array($folder['parentId'], $parents)) {
                $folders[$folder_id] = $folder;
                $parents[] = $folder_id;
            }
        }

        return $folders;
    }

    /**
     * Returns Folder object (uses internal cache)
     *
     * @param string $name  Folder name (UTF7-IMAP)
     *
     * @return kolab_storage_folder Folder object
     */
    protected function getFolderObject($name)
    {
        if ($name === null) {
            return null;
        }

        if (!isset($this->folders[$name])) {
            $this->folders[$name] = kolab_storage::get_folder($name);
        }

        return $this->folders[$name];
    }

    /**
     * Returns ActiveSync settings of specified folder
     *
     * @param string $name Folder name (UTF7-IMAP)
     *
     * @return array Folder settings
     */
    protected function getFolderConfig($name)
    {
        $metadata = $this->backend->folder_meta();

        if (!is_array($metadata)) {
            return array();
        }

        $deviceid = $this->device->deviceid;
        $config   = $metadata[$name]['FOLDER'][$deviceid];

        return array(
            'ALARMS' => $config['S'] == 2,
        );
    }

    /**
     * Returns real folder name for specified folder ID
     */
    protected function getFolderName($folderid)
    {
        if ($folderid == $this->defaultRootFolder) {
            $default  = $this->getDefaultFolder();

            if (!is_array($default)) {
                return null;
            }

            $folderid = isset($default['realid']) ? $default['realid'] : $default['serverId'];
        }

        return $this->backend->folder_id2name($folderid, $this->device->deviceid);
    }

    /**
     * Convert contact from xml to kolab format
     *
     * @param Syncroton_Model_IEntry $data     Contact data
     * @param string                 $folderId Folder identifier
     * @param array                  $entry    Old Contact data for merge
     *
     * @return array
     */
    abstract function toKolab(Syncroton_Model_IEntry $data, $folderId, $entry = null);

    /**
     * Extracts data from kolab data array
     */
    protected function getKolabDataItem($data, $name)
    {
        $name_items = explode('.', $name);
        $count      = count($name_items);

        // multi-level array (e.g. address, phone)
        if ($count == 3) {
            $name     = $name_items[0];
            $type     = $name_items[1];
            $key_name = $name_items[2];

            if (!empty($data[$name]) && is_array($data[$name])) {
                foreach ($data[$name] as $element) {
                    if ($element['type'] == $type) {
                        return $element[$key_name];
                    }
                }
            }

            return null;
        }
/*
        // hash array e.g. organizer
        else if ($count == 2) {
            $name     = $name_items[0];
            $type     = $name_items[1];
            $key_name = $name_items[2];

            if (!empty($data[$name]) && is_array($data[$name])) {
                foreach ($data[$name] as $element) {
                    if ($element['type'] == $type) {
                        return $element[$key_name];
                    }
                }
            }

            return null;
        }
*/
        $name_items = explode(':', $name);
        $name       = $name_items[0];

        if (empty($data[$name])) {
            return null;
        }

        // simple array (e.g. email)
        if (count($name_items) == 2) {
            return $data[$name][$name_items[1]];
        }

        return $data[$name];
    }

    /**
     * Saves data in kolab data array
     */
    protected function setKolabDataItem(&$data, $name, $value)
    {
        if (empty($value)) {
            return $this->unsetKolabDataItem($data, $name);
        }

        $name_items = explode('.', $name);

        // multi-level array (e.g. address, phone)
        if (count($name_items) == 3) {
            $name     = $name_items[0];
            $type     = $name_items[1];
            $key_name = $name_items[2];

            if (!isset($data[$name])) {
                $data[$name] = array();
            }

            foreach ($data[$name] as $idx => $element) {
                if ($element['type'] == $type) {
                    $found = $idx;
                    break;
                }
            }

            if (!isset($found)) {
                $data[$name] = array_values($data[$name]);
                $found = count($data[$name]);
                $data[$name][$found] = array('type' => $type);
            }

            $data[$name][$found][$key_name] = $value;

            return;
        }

        $name_items = explode(':', $name);
        $name       = $name_items[0];

        // simple array (e.g. email)
        if (count($name_items) == 2) {
            $data[$name][$name_items[1]] = $value;
            return;
        }

        $data[$name] = $value;
    }

    /**
     * Unsets data item in kolab data array
     */
    protected function unsetKolabDataItem(&$data, $name)
    {
        $name_items = explode('.', $name);

        // multi-level array (e.g. address, phone)
        if (count($name_items) == 3) {
            $name     = $name_items[0];
            $type     = $name_items[1];
            $key_name = $name_items[2];

            if (!isset($data[$name])) {
                return;
            }

            foreach ($data[$name] as $idx => $element) {
                if ($element['type'] == $type) {
                    $found = $idx;
                    break;
                }
            }

            if (!isset($found)) {
                return;
            }

            unset($data[$name][$found][$key_name]);

            // if there's only one element and it's 'type', remove it
            if (count($data[$name][$found]) == 1 && isset($data[$name][$found]['type'])) {
                unset($data[$name][$found]['type']);
            }
            if (empty($data[$name][$found])) {
                unset($data[$name][$found]);
            }
            if (empty($data[$name])) {
                unset($data[$name]);
            }

            return;
        }

        $name_items = explode(':', $name);
        $name       = $name_items[0];

        // simple array (e.g. email)
        if (count($name_items) == 2) {
            unset($data[$name][$name_items[1]]);
            if (empty($data[$name])) {
                unset($data[$name]);
            }
            return;
        }

        unset($data[$name]);
    }

    /**
     * Setter for Body attribute according to client version
     *
     * @param string $value Body
     * @param array  $param Body parameters
     *
     * @reurn Syncroton_Model_EmailBody Body element
     */
    protected function setBody($value, $params = array())
    {
        if (empty($value) && empty($params)) {
            return;
        }

        // Old protocol version doesn't support AirSyncBase:Body, it's eg. WindowsCE
        if ($this->asversion < 12) {
            return;
        }

        if (!empty($value)) {
            // cast to string to workaround issue described in Bug #1635
            $params['data'] = (string) $value;
        }

        if (!isset($params['type'])) {
            $params['type'] = Syncroton_Model_EmailBody::TYPE_PLAINTEXT;
        }

        return new Syncroton_Model_EmailBody($params);
    }

    /**
     * Getter for Body attribute value according to client version
     *
     * @param mixed $body Body element
     * @param int   $type Result data type (to which the body will be converted, if specified).
     *                    One of Syncroton_Model_EmailBody constants.
     *
     * @return string Body value
     */
    protected function getBody($body, $type = null)
    {
        if ($body && $body->data) {
            $data = $body->data;
        }

        // Convert to specified type
        if ($data && $type && $body->type != $type) {
            $converter = new kolab_sync_body_converter($data, $body->type);
            $data      = $converter->convert($type);
        }

        return $data;
    }

    /**
     * Converts PHP DateTime, date (YYYY-MM-DD) or unixtimestamp into PHP DateTime in UTC
     *
     * @param DateTime|int|string $date Unix timestamp, date (YYYY-MM-DD) or PHP DateTime object
     *
     * @return DateTime Datetime object
     */
    protected static function date_from_kolab($date)
    {
        if (!empty($date)) {
            if (is_numeric($date)) {
                $date = new DateTime('@' . $date);
            }
            else if (is_string($date)) {
                $date = new DateTime($date, new DateTimeZone('UTC'));
            }
            else if ($date instanceof DateTime) {
                $date    = clone $date;
                $tz      = $date->getTimezone();
                $tz_name = $tz->getName();

                // convert to UTC if needed
                if ($tz_name != 'UTC') {
                    $utc = new DateTimeZone('UTC');
                    // safe dateonly object conversion to UTC
                    // note: _dateonly flag is set by libkolab e.g. for birthdays
                    if ($date->_dateonly) {
                        // avoid time change
                        $date = new DateTime($date->format('Y-m-d'), $utc);
                        // set time to noon to avoid timezone troubles
                        $date->setTime(12, 0, 0);
                    }
                    else {
                        $date->setTimezone($utc);
                    }
                }
            }
            else {
                return null; // invalid input
            }

            return $date;
        }
    }

    /**
     * Convert Kolab event/task recurrence into ActiveSync
     */
    protected function recurrence_from_kolab($collection, $data, &$result, $type = 'Event')
    {
        if (empty($data['recurrence'])) {
            return;
        }

        $recurrence = array();
        $r          = $data['recurrence'];

        // required fields
        switch($r['FREQ']) {
        case 'DAILY':
            $recurrence['type'] = self::RECUR_TYPE_DAILY;
            break;

        case 'WEEKLY':
            $recurrence['type'] = self::RECUR_TYPE_WEEKLY;
            $recurrence['dayOfWeek'] = $this->day2bitmask($r['BYDAY']);
            break;

        case 'MONTHLY':
            if (!empty($r['BYMONTHDAY'])) {
                // @TODO: ActiveSync doesn't support multi-valued month days,
                // should we replicate the recurrence element for each day of month?
                $month_day = array_shift(explode(',', $r['BYMONTHDAY']));
                $recurrence['type'] = self::RECUR_TYPE_MONTHLY;
                $recurrence['dayOfMonth'] = $month_day;
            }
            else {
                $week = (int) substr($r['BYDAY'], 0, -2);
                $week = ($week == -1) ? 5 : $week;
                $day  = substr($r['BYDAY'], -2);
                $recurrence['type'] = self::RECUR_TYPE_MONTHLY_DAYN;
                $recurrence['weekOfMonth'] = $week;
                $recurrence['dayOfWeek'] = $this->day2bitmask($day);
            }
            break;

        case 'YEARLY':
            // @TODO: ActiveSync doesn't support multi-valued months,
            // should we replicate the recurrence element for each month?
            $month = array_shift(explode(',', $r['BYMONTH']));

            if (!empty($r['BYDAY'])) {
                $week = (int) substr($r['BYDAY'], 0, -2);
                $week = ($week == -1) ? 5 : $week;
                $day  = substr($r['BYDAY'], -2);
                $recurrence['type'] = self::RECUR_TYPE_YEARLY_DAYN;
                $recurrence['weekOfMonth'] = $week;
                $recurrence['dayOfWeek'] = $this->day2bitmask($day);
                $recurrence['monthOfYear'] = $month;
            }
            else if (!empty($r['BYMONTHDAY'])) {
                // @TODO: ActiveSync doesn't support multi-valued month days,
                // should we replicate the recurrence element for each day of month?
                $month_day = array_shift(explode(',', $r['BYMONTHDAY']));
                $recurrence['type'] = self::RECUR_TYPE_YEARLY;
                $recurrence['dayOfMonth'] = $month_day;
                $recurrence['monthOfYear'] = $month;
            }
            else {
                $recurrence['type'] = self::RECUR_TYPE_YEARLY;
                $recurrence['monthOfYear'] = $month;
            }
            break;
        }

        // required field
        $recurrence['interval'] = $r['INTERVAL'] ? $r['INTERVAL'] : 1;

        if (!empty($r['UNTIL'])) {
            $recurrence['until'] = self::date_from_kolab($r['UNTIL']);
        }
        else if (!empty($r['COUNT'])) {
            $recurrence['occurrences'] = $r['COUNT'];
        }

        $class = 'Syncroton_Model_' . $type . 'Recurrence';

        $result['recurrence'] = new $class($recurrence);

        // Tasks do not support exceptions
        if ($type == 'Event') {
            $result['exceptions'] = $this->exceptions_from_kolab($collection, $data, $result);
        }
    }

    /**
     * Convert ActiveSync event/task recurrence into Kolab
     */
    protected function recurrence_to_kolab($data, $folderid, $timezone = null)
    {
        if (!($data->recurrence instanceof Syncroton_Model_EventRecurrence) || !isset($data->recurrence->type)) {
            return null;
        }

        $recurrence = $data->recurrence;
        $type       = $recurrence->type;

        switch ($type) {
        case self::RECUR_TYPE_DAILY:
            break;

        case self::RECUR_TYPE_WEEKLY:
            $rrule['BYDAY'] = $this->bitmask2day($recurrence->dayOfWeek);
            break;

        case self::RECUR_TYPE_MONTHLY:
            $rrule['BYMONTHDAY'] = $recurrence->dayOfMonth;
            break;

        case self::RECUR_TYPE_MONTHLY_DAYN:
            $week = $recurrence->weekOfMonth;
            $day  = $recurrence->dayOfWeek;
            $byDay  = $week == 5 ? -1 : $week;
            $byDay .= $this->bitmask2day($day);

            $rrule['BYDAY'] = $byDay;
            break;

        case self::RECUR_TYPE_YEARLY:
            $rrule['BYMONTH']    = $recurrence->monthOfYear;
            $rrule['BYMONTHDAY'] = $recurrence->dayOfMonth;
            break;

        case self::RECUR_TYPE_YEARLY_DAYN:
            $rrule['BYMONTH'] = $recurrence->monthOfYear;

            $week = $recurrence->weekOfMonth;
            $day  = $recurrence->dayOfWeek;
            $byDay  = $week == 5 ? -1 : $week;
            $byDay .= $this->bitmask2day($day);

            $rrule['BYDAY'] = $byDay;
            break;
        }

        $rrule['FREQ']     = $this->recurTypeMap[$type];
        $rrule['INTERVAL'] = isset($recurrence->interval) ? $recurrence->interval : 1;

        if (isset($recurrence->until)) {
            if ($timezone) {
                $recurrence->until->setTimezone($timezone);
            }
            $rrule['UNTIL'] = $recurrence->until;
        }
        else if (!empty($recurrence->occurrences)) {
            $rrule['COUNT'] = $recurrence->occurrences;
        }

        // recurrence exceptions (not supported by Tasks)
        if ($data instanceof Syncroton_Model_Event) {
            $this->exceptions_to_kolab($data, $rrule, $folderid, $timezone);
        }

        return $rrule;
    }

    /**
     * Convert Kolab event recurrence exceptions into ActiveSync
     */
    protected function exceptions_from_kolab($collection, $data, $result)
    {
        if (empty($data['recurrence']['EXCEPTIONS']) && empty($data['recurrence']['EXDATE'])) {
            return null;
        }

        $ex_list = array();

        // exceptions (modified occurences)
        foreach ((array)$data['recurrence']['EXCEPTIONS'] as $exception) {
            $exception['_mailbox'] = $data['_mailbox'];
            $ex = $this->getEntry($collection, $exception, true);

            $ex['exceptionStartTime'] = clone $ex['startTime'];

            // remove fields not supported by Syncroton_Model_EventException
            unset($ex['uID']);

            // @TODO: 'thisandfuture=true' is not supported in Activesync
            // we'd need to slit the event into two separate events

            $ex_list[] = new Syncroton_Model_EventException($ex);
        }

        // exdate (deleted occurences)
        foreach ((array)$data['recurrence']['EXDATE'] as $exception) {
            if (!($exception instanceof DateTime)) {
                continue;
            }

            // set event start time to exception date
            // that can't be any time, tested with Android
            $hour   = $data['_start']->format('H');
            $minute = $data['_start']->format('i');
            $second = $data['_start']->format('s');
            $exception->setTime($hour, $minute, $second);
            $exception->_dateonly = false;

            $ex = array(
                'deleted'            => 1,
                'exceptionStartTime' => self::date_from_kolab($exception),
            );

            $ex_list[] = new Syncroton_Model_EventException($ex);
        }

        return $ex_list;
    }

    /**
     * Convert ActiveSync event recurrence exceptions into Kolab
     */
    protected function exceptions_to_kolab($data, &$rrule, $folderid, $timezone = null)
    {
        $rrule['EXDATE']     = array();
        $rrule['EXCEPTIONS'] = array();

        // handle exceptions from recurrence
        if (!empty($data->exceptions)) {
            foreach ($data->exceptions as $exception) {
                if ($exception->deleted) {
                    $date = clone $exception->exceptionStartTime;
                    if ($timezone) {
                        $date->setTimezone($timezone);
                    }
                    $date->setTime(0, 0, 0);
                    $rrule['EXDATE'][] = $date;
                }
                else if (!$exception->deleted) {
                    $ex = $this->toKolab($exception, $folderid, null, $timezone);

                    if ($data->allDayEvent) {
                        $ex['allday'] = 1;
                    }

                    $rrule['EXCEPTIONS'][] = $ex;
                }
            }
        }
    }

    /**
     * Converts string of days (TU,TH) to bitmask used by ActiveSync
     *
     * @param string $days
     *
     * @return int
     */
    protected function day2bitmask($days)
    {
        $days   = explode(',', $days);
        $result = 0;

        foreach ($days as $day) {
            $result = $result + $this->recurDayMap[$day];
        }

        return $result;
    }

    /**
     * Convert bitmask used by ActiveSync to string of days (TU,TH)
     *
     * @param int $days
     *
     * @return string
     */
    protected function bitmask2day($days)
    {
        $days_arr = array();

        for ($bitmask = 1; $bitmask <= self::RECUR_DOW_SATURDAY; $bitmask = $bitmask << 1) {
            $dayMatch = $days & $bitmask;
            if ($dayMatch === $bitmask) {
                $days_arr[] = array_search($bitmask, $this->recurDayMap);
            }
        }
        $result = implode(',', $days_arr);

        return $result;
    }
}
