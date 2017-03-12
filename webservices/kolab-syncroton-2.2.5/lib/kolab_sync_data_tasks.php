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
 * Tasks data class for Syncroton
 */
class kolab_sync_data_tasks extends kolab_sync_data
{
    /**
     * Mapping from ActiveSync Calendar namespace fields
     */
    protected $mapping = array(
        'body'            => 'description',
        'categories'      => 'categories',
        //'complete'      => 'complete', // handled separately
        'dateCompleted'   => 'changed',
        'dueDate'         => 'due',
        'importance'      => 'priority',
        //'recurrence'      => 'recurrence',
        //'reminderSet'     => 'reminderset',
        //'reminderTime'    => 'remindertime',
        'sensitivity'     => 'sensitivity',
        'startDate'       => 'start',
        'subject'         => 'title',
        'utcDueDate'      => 'due',
        'utcStartDate'    => 'start',
    );

    /**
     * Sensitivity values
     */
    const SENSITIVITY_NORMAL       = 0;
    const SENSITIVITY_PERSONAL     = 1;
    const SENSITIVITY_PRIVATE      = 2;
    const SENSITIVITY_CONFIDENTIAL = 3;

    /**
     * mapping of sensitivity
     *
     * @var array
     */
    protected $sensitivityMap = array(
        'public'       => self::SENSITIVITY_PERSONAL,
        'private'      => self::SENSITIVITY_PRIVATE,
        'confidential' => self::SENSITIVITY_CONFIDENTIAL,
    );


    /**
     * Kolab object type
     *
     * @var string
     */
    protected $modelName = 'task';

    /**
     * Type of the default folder
     *
     * @var int
     */
    protected $defaultFolderType = Syncroton_Command_FolderSync::FOLDERTYPE_TASK;

    /**
     * Default container for new entries
     *
     * @var string
     */
    protected $defaultFolder = 'Tasks';

    /**
     * Type of user created folders
     *
     * @var int
     */
    protected $folderType = Syncroton_Command_FolderSync::FOLDERTYPE_TASK_USER_CREATED;


    /**
     * Appends contact data to xml element
     *
     * @param Syncroton_Model_SyncCollection $collection Collection data
     * @param string                         $serverId   Local entry identifier
     * @param boolean                        $as_array   Return entry as an array
     *
     * @return array|Syncroton_Model_Task|array Task object
     */
    public function getEntry(Syncroton_Model_SyncCollection $collection, $serverId, $as_array = false)
    {
        $task   = is_array($serverId) ? $serverId : $this->getObject($collection->collectionId, $serverId);
        $config = $this->getFolderConfig($task['_mailbox']);
        $result = array();

        // Completion status (required)
        $result['complete'] = intval(!empty($task['status']) && $task['status'] == 'COMPLETED');

        // Calendar namespace fields
        foreach ($this->mapping as $key => $name) {
            $value = $this->getKolabDataItem($task, $name);

            switch ($name) {
            case 'due':
            case 'start':
                if (preg_match('/^UTC/i', $key)) {
                    $value = self::date_from_kolab($value);
                }
                break;

            case 'changed':
                $value = $result['complete'] ? self::date_from_kolab($value) : null;
                break;

            case 'description':
                $value = $this->setBody($value);
                break;

            case 'sensitivity':
                $value = intval($this->sensitivityMap[$value]);
                break;

            case 'priority':
                $value = $this->prio_to_importance($value);
                break;
            }

            if (empty($value) || is_array($value)) {
                continue;
            }

            $result[$key] = $value;
        }

        // Recurrence
        $this->recurrence_from_kolab($collection, $task, $result, 'Task');

        return $as_array ? $result : new Syncroton_Model_Task($result);
    }

    /**
     * convert contact from xml to libkolab array
     *
     * @param Syncroton_Model_IEntry $data     Contact to convert
     * @param string                 $folderid Folder identifier
     * @param array                  $entry    Existing entry
     *
     * @return array
     */
    public function toKolab(Syncroton_Model_IEntry $data, $folderid, $entry = null)
    {
        $task       = !empty($entry) ? $entry : array();
        $foldername = isset($task['_mailbox']) ? $task['_mailbox'] : $this->getFolderName($folderid);
        $config     = $this->getFolderConfig($foldername);

        $task['allday'] = 0;

        // Calendar namespace fields
        foreach ($this->mapping as $key => $name) {
            $value = $data->$key;

            switch ($name) {
            case 'sensitivity':
                $map   = array_flip($this->sensitivityMap);
                $value = $map[$value];
                break;

            case 'description':
                $value = $this->getBody($value, Syncroton_Model_EmailBody::TYPE_PLAINTEXT);
                // If description isn't specified keep old description
                if ($value === null) {
                    continue 2;
                }
                break;

            case 'priority':
                $value = $this->importance_to_prio($value);
                break;
            }

            $this->setKolabDataItem($task, $name, $value);
        }

        if (!empty($data->complete)) {
            $task['status']   = 'COMPLETED';
            $task['complete'] = 100;
        }

        // recurrence
        $task['recurrence'] = $this->recurrence_to_kolab($data, $folderid, null);

        return $task;
    }

    /**
     * Returns filter query array according to specified ActiveSync FilterType
     *
     * @param int $filter_type Filter type
     *
     * @param array Filter query
     */
    protected function filter($filter_type = 0)
    {
        $filter = array(array('type', '=', $this->modelName));

        if ($filter_type == Syncroton_Command_Sync::FILTER_INCOMPLETE) {
            $filter[] = array('tags', '!~', 'x-complete');
        }

        return $filter;
    }

    /**
     * Convert Kolab priority into ActiveSync importance value
     */
    protected function prio_to_importance($value)
    {
        // ActiveSync has only 3 levels of importance:
        // 0 - Low, 1 - Normal, 2 - High
        // but Kolab uses ten levels:
        // 0 - unknown and 1-9 where 1 is the highest
        // Use mapping from http://msdn.microsoft.com/en-us/library/ee159635.aspx

        if ($value === null) {
            return;
        }

        switch ($value) {
        case 1:
        case 2:
        case 3:
        case 4:
            return 2;
        case 5:
            return 1;
        case 6:
        case 7:
        case 8:
        case 9:
            return 0;
        }

        return;
    }

    /**
     * Convert ActiveSync importance into Kolab priority value
     */
    protected function importance_to_prio($value)
    {
        // Use mapping from http://msdn.microsoft.com/en-us/library/ee159635.aspx

        if ($value === null) {
            return;
        }

        switch ($value) {
        case 0:
            return 9;
        case 1:
            return 5;
        case 2:
            return 1;
        }

        return;
    }
}
