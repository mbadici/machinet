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
 * Calendar (Events) data class for Syncroton
 */
class kolab_sync_data_calendar extends kolab_sync_data implements Syncroton_Data_IDataCalendar
{
    /**
     * Mapping from ActiveSync Calendar namespace fields
     */
    protected $mapping = array(
        'allDayEvent'             => 'allday',
        //'attendees'               => 'attendees',
        'body'                    => 'description',
        //'bodyTruncated'           => 'bodytruncated',
        'busyStatus'              => 'free_busy',
        //'categories'              => 'categories',
        'dtStamp'                 => 'changed',
        'endTime'                 => 'end',
        //'exceptions'              => 'exceptions',
        'location'                => 'location',
        //'meetingStatus'           => 'meetingstatus',
        //'organizerEmail'          => 'organizeremail',
        //'organizerName'           => 'organizername',
        //'recurrence'              => 'recurrence',
        //'reminder'                => 'reminder',
        //'responseRequested'       => 'responserequested',
        //'responseType'          => 'responsetype',
        'sensitivity'             => 'sensitivity',
        'startTime'               => 'start',
        'subject'                 => 'title',
        //'timezone'                => 'timezone',
        'uID'                     => 'uid',
    );

    /**
     * Kolab object type
     *
     * @var string
     */
    protected $modelName = 'event';

    /**
     * Type of the default folder
     *
     * @var int
     */
    protected $defaultFolderType = Syncroton_Command_FolderSync::FOLDERTYPE_CALENDAR;

    /**
     * Default container for new entries
     *
     * @var string
     */
    protected $defaultFolder = 'Calendar';

    /**
     * Type of user created folders
     *
     * @var int
     */
    protected $folderType = Syncroton_Command_FolderSync::FOLDERTYPE_CALENDAR_USER_CREATED;

    /**
     * attendee status
     */
    const ATTENDEE_STATUS_UNKNOWN       = 0;
    const ATTENDEE_STATUS_TENTATIVE     = 2;
    const ATTENDEE_STATUS_ACCEPTED      = 3;
    const ATTENDEE_STATUS_DECLINED      = 4;
    const ATTENDEE_STATUS_NOTRESPONDED  = 5;

    /**
     * attendee types
     */
    const ATTENDEE_TYPE_REQUIRED = 1;
    const ATTENDEE_TYPE_OPTIONAL = 2;
    const ATTENDEE_TYPE_RESOURCE = 3;

    /**
     * busy status constants
     */
    const BUSY_STATUS_FREE        = 0;
    const BUSY_STATUS_TENTATIVE   = 1;
    const BUSY_STATUS_BUSY        = 2;
    const BUSY_STATUS_OUTOFOFFICE = 3;

    /**
     * Sensitivity values
     */
    const SENSITIVITY_NORMAL       = 0;
    const SENSITIVITY_PERSONAL     = 1;
    const SENSITIVITY_PRIVATE      = 2;
    const SENSITIVITY_CONFIDENTIAL = 3;

    /**
     * Mapping of attendee status
     *
     * @var array
     */
    protected $attendeeStatusMap = array(
        'UNKNOWN'      => self::ATTENDEE_STATUS_UNKNOWN,
        'TENTATIVE'    => self::ATTENDEE_STATUS_TENTATIVE,
        'ACCEPTED'     => self::ATTENDEE_STATUS_ACCEPTED,
        'DECLINED'     => self::ATTENDEE_STATUS_DECLINED,
        'DELEGATED'    => self::ATTENDEE_STATUS_UNKNOWN,
        'NEEDS-ACTION' => self::ATTENDEE_STATUS_UNKNOWN,
        //self::ATTENDEE_STATUS_NOTRESPONDED,
    );

    /**
     * Mapping of attendee type
     *
     * NOTE: recurrences need extra handling!
     * @var array
     */
    protected $attendeeTypeMap = array(
        'REQ-PARTICIPANT' => self::ATTENDEE_TYPE_REQUIRED,
        'OPT-PARTICIPANT' => self::ATTENDEE_TYPE_OPTIONAL,
//        'NON-PARTICIPANT' => self::ATTENDEE_TYPE_RESOURCE,
//        'CHAIR'           => self::ATTENDEE_TYPE_RESOURCE,
    );

    /**
     * Mapping of busy status
     *
     * @var array
     */
    protected $busyStatusMap = array(
        'free'        => self::BUSY_STATUS_FREE,
        'tentative'   => self::BUSY_STATUS_TENTATIVE,
        'busy'        => self::BUSY_STATUS_BUSY,
        'outofoffice' => self::BUSY_STATUS_OUTOFOFFICE,
    );

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
     * Appends contact data to xml element
     *
     * @param Syncroton_Model_SyncCollection $collection Collection data
     * @param string                         $serverId   Local entry identifier
     * @param boolean                        $as_array   Return entry as array
     *
     * @return array|Syncroton_Model_Event|array Event object
     */
    public function getEntry(Syncroton_Model_SyncCollection $collection, $serverId, $as_array = false)
    {
        $event  = is_array($serverId) ? $serverId : $this->getObject($collection->collectionId, $serverId);
        $config = $this->getFolderConfig($event['_mailbox']);
        $result = array();

        // Timezone
        // Kolab Format 3.0 and xCal does support timezone per-date, but ActiveSync allows
        // only one timezone per-event. We'll use timezone of the start date
        if ($event['start'] instanceof DateTime) {
            $timezone = $event['start']->getTimezone();

            if ($timezone && ($tz_name = $timezone->getName()) != 'UTC') {
                $tzc = kolab_sync_timezone_converter::getInstance();

                if ($tz_name = $tzc->encodeTimezone($tz_name)) {
                    $result['timezone'] = $tz_name;
                }
            }
        }

        // Calendar namespace fields
        foreach ($this->mapping as $key => $name) {
            $value = $this->getKolabDataItem($event, $name);

            switch ($name) {
            case 'changed':
            case 'end':
            case 'start':
                // For all-day events Kolab uses different times
                // At least Android doesn't display such event as all-day event
                if ($value && is_a($value, 'DateTime')) {
                    $date = clone $value;
                    if ($event['allday']) {
                        // need this for self::date_from_kolab()
                        $date->_dateonly = false;

                        if ($name == 'start') {
                            $date->setTime(0, 0, 0);
                        }
                        else if ($name == 'end') {
                            $date->setTime(0, 0, 0);
                            $date->modify('+1 day');
                        }
                    }

                    // set this date for use in recurrence exceptions handling
                    if ($name == 'start') {
                        $event['_start'] = $date;
                    }

                    $value = self::date_from_kolab($date);
                }

                break;

            case 'sensitivity':
                $value = intval($this->sensitivityMap[$value]);
                break;

            case 'free_busy':
                $value = $this->busyStatusMap[$value];
                break;

            case 'description':
                $value = $this->setBody($value);
                break;
            }

            if (empty($value) || is_array($value)) {
                continue;
            }

            $result[$key] = $value;
        }

        // Event reminder time
        if ($config['ALARMS'] && ($minutes = $this->from_kolab_alarm($event['alarms']))) {
            $result['reminder'] = $minutes;
        }

        $result['categories'] = array();
        $result['attendees'] = array();

        // Categories, Roundcube Calendar plugin supports only one category at a time
        if (!empty($event['categories'])) {
            $result['categories'] = (array) $event['categories'];
        }

        // Organizer
        if (!empty($event['attendees'])) {
            foreach ($event['attendees'] as $idx => $attendee) {
                if ($attendee['role'] == 'ORGANIZER') {
                    $organizer = $attendee;
                    if ($name = $attendee['name']) {
                        $result['organizerName'] = $name;
                    }
                    if ($email = $attendee['email']) {
                        $result['organizerEmail'] = $email;
                    }

                    unset($event['attendees'][$idx]);
                    break;
                }
            }
        }

        // Attendees
        if (!empty($event['attendees'])) {
            foreach ($event['attendees'] as $idx => $attendee) {
                $att = array();

                if ($name = $attendee['name']) {
                    $att['name'] = $name;
                }
                if ($email = $attendee['email']) {
                    $att['email'] = $email;
                }

                if ($this->asversion >= 12) {
                    $type   = isset($attendee['role'])   ? $this->attendeeTypeMap[$attendee['role']] : null;
                    $status = isset($attendee['status']) ? $this->attendeeStatusMap[$attende['status']] : null;

                    $att['attendeeType']   = $type ? $type : self::ATTENDEE_TYPE_REQUIRED;
                    $att['attendeeStatus'] = $status ? $status : self::ATTENDEE_STATUS_UNKNOWN;
                }

                $result['attendees'][] = new Syncroton_Model_EventAttendee($att);
            }
        }

        // Event meeting status
        $result['meetingStatus'] = intval(!empty($result['attendees']));

        // Recurrence (and exceptions)
        $this->recurrence_from_kolab($collection, $event, $result);

        return $as_array ? $result : new Syncroton_Model_Event($result);
    }

    /**
     * convert contact from xml to libkolab array
     *
     * @param Syncroton_Model_IEntry $data     Contact to convert
     * @param string                 $folderid Folder identifier
     * @param array                  $entry    Existing entry
     * @param DateTimeZone           $timezone Timezone of the event
     *
     * @return array
     */
    public function toKolab(Syncroton_Model_IEntry $data, $folderid, $entry = null, $timezone = null)
    {
        $event        = !empty($entry) ? $entry : array();
        $foldername   = isset($event['_mailbox']) ? $event['_mailbox'] : $this->getFolderName($folderid);
        $config       = $this->getFolderConfig($foldername);
        $is_exception = $data instanceof Syncroton_Model_EventException;

        $event['allday'] = 0;

        // Timezone
        if (!$timezone && isset($data->timezone)) {
            $tzc      = kolab_sync_timezone_converter::getInstance();
            $expected = kolab_format::$timezone->getName();

            if (!empty($event['start']) && ($event['start'] instanceof DateTime)) {
                $expected = $event['start']->getTimezone()->getName();
            }

            $timezone = $tzc->getTimezone($data->timezone, $expected);
            try {
                $timezone = new DateTimeZone($timezone);
            }
            catch (Exception $e) {
                $timezone = null;
            }
        }
        if (empty($timezone)) {
            $timezone = new DateTimeZone('UTC');
        }

        // Calendar namespace fields
        foreach ($this->mapping as $key => $name) {
            // skip UID field, unsupported in event exceptions
            // we need to do this here, because the next line (data getter) will throw an exception
            if ($is_exception && $key == 'uID') {
                continue;
            }

            $value = $data->$key;

            switch ($name) {
            case 'changed':
                $value = null;
                break;

            case 'end':
            case 'start':
                if ($timezone && $value) {
                    $value->setTimezone($timezone);
                }
                // In ActiveSync all-day event ends on 00:00:00 next day
                if ($value && $data->allDayEvent && $name == 'end') {
                    $value->modify('-1 second');
                }

                break;

            case 'sensitivity':
                $map   = array_flip($this->sensitivityMap);
                $value = $map[$value];
                break;

            case 'free_busy':
                $map   = array_flip($this->busyStatusMap);
                $value = $map[$value];
                break;

            case 'description':
                $value = $this->getBody($value, Syncroton_Model_EmailBody::TYPE_PLAINTEXT);
                // If description isn't specified keep old description
                if ($value === null) {
                    continue 2;
                }
                break;

            case 'uid':
                // If UID is too long, use auto-generated UID (#1034)
                // It's because UID is used as ServerId which cannot be longer than 64 chars
                if (strlen($value) > 64) {
                    $value = null;
                }
                break;
            }

            $this->setKolabDataItem($event, $name, $value);
        }

        // Try to fix allday events from Android
        // It doesn't set all-day flag but the period is a whole day
        if (!$event['allday'] && $event['end'] && $event['start']) {
            $interval = @date_diff($event['start'], $event['end']);
            if ($interval && $interval->format('%y%m%d%h%i%s') == '001000') {
                $event['allday'] = 1;
                $event['end']    = clone $event['start'];
            }
        }

        // Reminder
        // @TODO: should alarms be used when importing event from phone?
        if ($config['ALARMS']) {
            $event['alarms'] = $this->to_kolab_alarm($data->reminder, $event);
        }

        $event['attendees']  = array();
        $event['categories'] = array();

        // Categories
        if (isset($data->categories)) {
            foreach ($data->categories as $category) {
                $event['categories'][] = $category;
            }
        }

        // Organizer
        if (!$is_exception) {
            $name  = $data->organizerName;
            $email = $data->organizerEmail;
            if ($name || $email) {
                $event['attendees'][] = array(
                    'role'  => 'ORGANIZER',
                    'name'  => $name,
                    'email' => $email,
                );
            }
        }

        // Attendees
        if (isset($data->attendees)) {
            foreach ($data->attendees as $attendee) {
                $role = false;
                if (isset($attendee->attendeeType)) {
                    $role = array_search($attendee->attendeeType, $this->attendeeTypeMap);
                }
                if ($role === false) {
                    $role = array_search(self::ATTENDEE_TYPE_REQUIRED, $this->attendeeTypeMap);
                }

                // AttendeeStatus send only on repsonse (?)

                $event['attendees'][] = array(
                    'role'  => $role,
                    'name'  => $attendee->name,
                    'email' => $attendee->email,
                );
            }
        }

        // recurrence (and exceptions)
        if (!$is_exception) {
            $event['recurrence'] = $this->recurrence_to_kolab($data, $folderid, $timezone);
        }

        return $event;
    }

    /**
     * Set attendee status for meeting
     *
     * @param Syncroton_Model_MeetingResponse $request The meeting response
     *
     * @return string ID of new calendar entry
     */
    public function setAttendeeStatus(Syncroton_Model_MeetingResponse $request)
    {
        // @TODO: not implemented
        throw new Syncroton_Exception_Status_MeetingResponse(Syncroton_Exception_Status_MeetingMeeting::MEETING_ERROR);
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
        $filter = array(array('type', '=', $this->modelName));

        switch ($filter_type) {
        case Syncroton_Command_Sync::FILTER_2_WEEKS_BACK:
            $mod = '-2 weeks';
            break;
        case Syncroton_Command_Sync::FILTER_1_MONTH_BACK:
            $mod = '-1 month';
            break;
        case Syncroton_Command_Sync::FILTER_3_MONTHS_BACK:
            $mod = '-3 months';
            break;
        case Syncroton_Command_Sync::FILTER_6_MONTHS_BACK:
            $mod = '-6 months';
            break;
        }

        if (!empty($mod)) {
            $dt = new DateTime('now', new DateTimeZone('UTC'));
            $dt->modify($mod);
            $filter[] = array('dtend', '>', $dt);
        }

        return $filter;
    }

    /**
     * Converts libkolab alarms string into number of minutes
     */
    protected function from_kolab_alarm($value)
    {
        // e.g. '-15M:DISPLAY'
        // Ignore EMAIL alarms
        if (preg_match('/^-([0-9]+)([WDHMS]):(DISPLAY|AUDIO)$/', $value, $matches)) {
            $value = intval($matches[1]);

            switch ($matches[2]) {
            case 'S': $value = 1; break;
            case 'H': $value *= 60; break;
            case 'D': $value *= 24 * 60; break;
            case 'W': $value *= 7 * 24 * 60; break;
            }

            return $value;
        }
    }

    /**
     * Converts ActiveSync libkolab alarms string into number of minutes
     */
    protected function to_kolab_alarm($value, $event)
    {
        // Get alarm type from old event object if exists
        if (!empty($event['alarms']) && preg_match('/:(.*)$/', $event['alarms'], $matches)) {
            $type = $matches[1];
        }

        if ($value) {
            return sprintf('-%dM:%s', $value, $type ? $type : 'DISPLAY');
        }

        if ($type == 'DISPLAY' || $type == 'AUDIO') {
            return null;
        }

        return $event['alarms'];
    }

}
