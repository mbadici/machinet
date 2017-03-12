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
 * Email data class for Syncroton
 */
class kolab_sync_data_email extends kolab_sync_data implements Syncroton_Data_IDataSearch
{
    const MAX_SEARCH_RESULT = 200;

    /**
     * Mapping from ActiveSync Email namespace fields
     */
    protected $mapping = array(
        'cc'                    => 'cc',
        //'contentClass'          => 'contentclass',
        'dateReceived'          => 'internaldate',
        //'displayTo'             => 'displayto', //?
        //'flag'                  => 'flag',
        'from'                  => 'from',
        //'importance'            => 'importance',
        'internetCPID'          => 'charset',
        //'messageClass'          => 'messageclass',
        'replyTo'               => 'replyto',
        //'read'                  => 'read',
        'subject'               => 'subject',
        //'threadTopic'           => 'threadtopic',
        'to'                    => 'to',
    );

    /**
     * Special folder type/name map
     *
     * @var array
     */
    protected $folder_types = array(
        2  => 'Inbox',
        3  => 'Drafts',
        4  => 'Deleted Items',
        5  => 'Sent Items',
        6  => 'Outbox',
    );

    /**
     * Kolab object type
     *
     * @var string
     */
    protected $modelName = 'mail';

    /**
     * Type of the default folder
     *
     * @var int
     */
    protected $defaultFolderType = Syncroton_Command_FolderSync::FOLDERTYPE_INBOX;

    /**
     * Default container for new entries
     *
     * @var string
     */
    protected $defaultFolder = 'INBOX';

    /**
     * Type of user created folders
     *
     * @var int
     */
    protected $folderType = Syncroton_Command_FolderSync::FOLDERTYPE_MAIL_USER_CREATED;


    /**
     * the constructor
     *
     * @param Syncroton_Model_IDevice $device
     * @param DateTime                $syncTimeStamp
     */
    public function __construct(Syncroton_Model_IDevice $device, DateTime $syncTimeStamp)
    {
        parent::__construct($device, $syncTimeStamp);

        $this->storage = rcube::get_instance()->get_storage();

        // Outlook 2013 support multi-folder
        $this->ext_devices[] = 'windowsoutlook15';
    }

    /**
     * Creates model object
     *
     * @param Syncroton_Model_SyncCollection $collection Collection data
     * @param string                         $serverId   Local entry identifier
     *
     * @return Syncroton_Model_Email Email object
     */
    public function getEntry(Syncroton_Model_SyncCollection $collection, $serverId)
    {
        $message = $this->getObject($serverId);

        if (empty($message)) {
            // @TODO: exception
            return;
        }

        $msg     = $this->parseMessageId($serverId);
        $headers = $message->headers; // rcube_message_header

        // Calendar namespace fields
        foreach ($this->mapping as $key => $name) {
            $value = null;

            switch ($name) {
            case 'internaldate':
                $value = self::date_from_kolab(rcube_imap_generic::strToTime($headers->internaldate));
                break;

            case 'cc':
            case 'to':
            case 'replyto':
            case 'from':
                $addresses = rcube_mime::decode_address_list($headers->$name, null, true, $headers->charset);

                foreach ($addresses as $idx => $part) {
                    // @FIXME: set name + address or address only?
                    $addresses[$idx] = format_email_recipient($part['mailto'], $part['name']);
                    $addresses[$idx] = rcube_charset::clean($addresses[$idx]);
                }

                $value = implode(',', $addresses);
                break;

            case 'subject':
                $value = $headers->get('subject');
                break;

            case 'charset':
                $value = self::charset_to_cp($headers->charset);
                break;
            }

            if (empty($value) || is_array($value)) {
                continue;
            }

            $result[$key] = $value;
        }

//        $result['ConversationId'] = 'FF68022058BD485996BE15F6F6D99320';
//        $result['ConversationIndex'] = 'CA2CFA8A23';

        // Read flag
        $result['read'] = intval(!empty($headers->flags['SEEN']));

        // Flagged message
        if (!empty($headers->flags['FLAGGED'])) {
            // Use FollowUp flag which is used in Android when message is marked with a star
            $result['flag'] = new Syncroton_Model_EmailFlag(array(
                'flagType' => 'FollowUp',
                'status' => Syncroton_Model_EmailFlag::STATUS_ACTIVE,
            ));
        }

        // Importance/Priority
        if ($headers->priority) {
            if ($headers->priority < 3) {
                $result['importance'] = 2; // High
            }
            else if ($headers->priority > 3) {
                 $result['importance'] = 0; // Low
            }
        }

        // get truncation and body type
        $airSyncBaseType = Syncroton_Command_Sync::BODY_TYPE_PLAIN_TEXT;
        $truncateAt = null;
        $opts       = $collection->options;
        $prefs      = $opts['bodyPreferences'];

        if ($opts['mimeSupport'] == Syncroton_Command_Sync::MIMESUPPORT_SEND_MIME) {
            $airSyncBaseType = Syncroton_Command_Sync::BODY_TYPE_MIME;

            if (isset($prefs[Syncroton_Command_Sync::BODY_TYPE_MIME]['truncationSize'])) {
                $truncateAt = $prefs[Syncroton_Command_Sync::BODY_TYPE_MIME]['truncationSize'];
            }
            else if (isset($opts['mimeTruncation']) && $opts['mimeTruncation'] < Syncroton_Command_Sync::TRUNCATE_NOTHING) {
                switch ($opts['mimeTruncation']) {
                case Syncroton_Command_Sync::TRUNCATE_ALL:
                    $truncateAt = 0;
                    break;
                case Syncroton_Command_Sync::TRUNCATE_4096:
                    $truncateAt = 4096;
                    break;
                case Syncroton_Command_Sync::TRUNCATE_5120:
                    $truncateAt = 5120;
                    break;
                case Syncroton_Command_Sync::TRUNCATE_7168:
                    $truncateAt = 7168;
                    break;
                case Syncroton_Command_Sync::TRUNCATE_10240:
                    $truncateAt = 10240;
                    break;
                case Syncroton_Command_Sync::TRUNCATE_20480:
                    $truncateAt = 20480;
                    break;
                case Syncroton_Command_Sync::TRUNCATE_51200:
                    $truncateAt = 51200;
                    break;
                case Syncroton_Command_Sync::TRUNCATE_102400:
                    $truncateAt = 102400;
                    break;
                }
            }
        }
        else {
            // The spec is not very clear, but it looks that if MimeSupport is not set
            // we can't add Syncroton_Command_Sync::BODY_TYPE_MIME to the supported types
            // list below (Bug #1688)
            $types = array(
                Syncroton_Command_Sync::BODY_TYPE_HTML,
                Syncroton_Command_Sync::BODY_TYPE_PLAIN_TEXT,
            );

            // @TODO: if client can support both HTML and TEXT use one of
            // them which is better according to the real message body type

            foreach ($types as $type) {
                if (!empty($prefs[$type])) {
                    if (!empty($prefs[$type]['truncationSize'])) {
                        $truncateAt = $prefs[$type]['truncationSize'];
                    }

                    $preview         = (int) $prefs[$type]['preview'];
                    $airSyncBaseType = $type;

                    break;
                }
            }
        }

        $body_params = array('type' => $airSyncBaseType);

        // Message body
        // In Sync examples there's one in which bodyPreferences is not defined
        // in such case Truncated=1 and there's no body sent to the client
        // only it's estimated size
        if (empty($prefs)) {
            $messageBody = '';
            $real_length = $message->size;
            $truncateAt  = 0;
            $body_length = 0;
            $isTruncated = 1;
        }
        else if ($airSyncBaseType == Syncroton_Command_Sync::BODY_TYPE_MIME) {
            $messageBody = $this->storage->get_raw_body($message->uid);
            // make the source safe (Bug #2715, #2757)
            $messageBody = kolab_sync_message::recode_message($messageBody);
            // strip out any non utf-8 characters
            $messageBody = rcube_charset::clean($messageBody);
            $real_length = $body_length = strlen($messageBody);
        }
        else {
            $messageBody = $this->getMessageBody($message, $airSyncBaseType == Syncroton_Command_Sync::BODY_TYPE_HTML);
            // strip out any non utf-8 characters
            $messageBody = rcube_charset::clean($messageBody);
            $real_length = $body_length = strlen($messageBody);
        }

        // add Preview element to the Body result
        if (!empty($preview) && $body_length) {
            $body_params['preview'] = $this->getPreview($messageBody, $airSyncBaseType, $preview);
        }

        // truncate the body if needed
        if ($truncateAt && $body_length > $truncateAt) {
            $messageBody = mb_strcut($messageBody, 0, $truncateAt);
            $body_length = strlen($messageBody);
            $isTruncated = 1;
        }

        if ($isTruncated) {
            $body_params['truncated']         = 1;
            $body_params['estimatedDataSize'] = $real_length;
        }

        // add Body element to the result
        $result['body'] = $this->setBody($messageBody, $body_params);

        // original body type
        // @TODO: get this value from getMessageBody()
        $result['nativeBodyType'] = $message->has_html_part() ? 2 : 1;

        // Message class
        // @TODO: add messageClass suffix for encrypted messages
        $result['messageClass'] = 'IPM.Note';
        $result['contentClass'] = 'urn:content-classes:message';

        // attachments
        $attachments = array_merge($message->attachments, $message->inline_parts);
        if (!empty($attachments)) {
            $result['attachments'] = array();

            foreach ($attachments as $attachment) {
                $att = array();

                $filename = $attachment->filename;
                if (empty($filename) && $attachment->mimetype == 'text/html') {
                    $filename = 'HTML Part';
                }

                $att['displayName']   = $filename;
                $att['fileReference'] = $serverId . '::' . $attachment->mime_id;
                $att['method']        = 1;
                $att['estimatedDataSize'] = $attachment->size;

                if (!empty($attachment->content_id)) {
                    $att['contentId'] = $attachment->content_id;
                }
                if (!empty($attachment->content_location)) {
                    $att['contentLocation'] = $attachment->content_location;
                }

                if (in_array($attachment, $message->inline_parts)) {
                    $att['isInline'] = 1;
                }

                $result['attachments'][] = new Syncroton_Model_EmailAttachment($att);
            }
        }

        return new Syncroton_Model_Email($result);
    }

    /**
     * Returns properties of a message for Search response
     *
     * @param string $longId  Message identifier
     * @param array  $options Search options
     *
     * @return Syncroton_Model_Email Email object
     */
    public function getSearchEntry($longId, $options)
    {
        $collection = new Syncroton_Model_SyncCollection(array(
            'options' => $options,
        ));

        return $this->getEntry($collection, $longId);
    }

    /**
     * convert contact from xml to libkolab array
     *
     * @param Syncroton_Model_IEntry $data Contact to convert
     * @param string           $folderid   Folder identifier
     * @param array            $entry      Existing entry
     *
     * @return array
     */
    public function toKolab(Syncroton_Model_IEntry $data, $folderid, $entry = null)
    {
        // does nothing => you can't add emails via ActiveSync
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
        $filter = array();

        switch ($filter_type) {
        case Syncroton_Command_Sync::FILTER_1_DAY_BACK:
            $mod = '-1 day';
            break;
        case Syncroton_Command_Sync::FILTER_3_DAYS_BACK:
            $mod = '-3 days';
            break;
        case Syncroton_Command_Sync::FILTER_1_WEEK_BACK:
            $mod = '-1 week';
            break;
        case Syncroton_Command_Sync::FILTER_2_WEEKS_BACK:
            $mod = '-2 weeks';
            break;
        case Syncroton_Command_Sync::FILTER_1_MONTH_BACK:
            $mod = '-1 month';
            break;
        }

        if (!empty($mod)) {
            $dt = new DateTime('now', new DateTimeZone('UTC'));
            $dt->modify($mod);
            // RFC3501: IMAP SEARCH
            $filter[] = 'SINCE ' . $dt->format('d-M-Y');
        }

        return $filter;
    }

    /**
     * Return list of supported folders for this backend
     *
     * @return array
     */
    public function getAllFolders()
    {
        $list = $this->listFolders();

        if (!is_array($list)) {
            throw new Syncroton_Exception_Status_FolderSync(Syncroton_Exception_Status_FolderSync::FOLDER_SERVER_ERROR);
        }

        // device doesn't support multiple folders
        if (!in_array(strtolower($this->device->devicetype), $this->ext_devices)) {
            // We'll return max. one folder of supported type
            $result = array();
            $types  = $this->folder_types;

            foreach ($list as $idx => $folder) {
                $type = $folder['type'] == 12 ? 2 : $folder['type']; // unknown to Inbox
                if ($folder_id = $types[$type]) {
                    $result[$folder_id] = array(
                        'displayName' => $folder_id,
                        'serverId'    => $folder_id,
                        'parentId'    => 0,
                        'type'        => $type,
                    );
                }
            }

            $list = $result;
        }

        foreach ($list as $idx => $folder) {
            $list[$idx] = new Syncroton_Model_Folder($folder);
        }

        return $list;
    }

    /**
     * Return list of folders for specified folder ID
     *
     * @return array Folder identifiers list
     */
    protected function extractFolders($folder_id)
    {
        $list   = $this->listFolders();
        $result = array();

        if (!is_array($list)) {
            throw new Syncroton_Exception_NotFound('Folder not found');
        }

        // device supports multiple folders?
        if (in_array(strtolower($this->device->devicetype), $this->ext_devices)) {
            if ($list[$folder_id]) {
                $result[] = $folder_id;
            }
        }
        else if ($type = array_search($folder_id, $this->folder_types)) {
            foreach ($list as $id => $folder) {
                if ($folder['type'] == $type || ($folder_id == 'Inbox' && $folder['type'] == 12)) {
                    $result[] = $id;
                }
            }
        }

        if (empty($result)) {
            throw new Syncroton_Exception_NotFound('Folder not found');
        }

        return $result;
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
        $msg       = $this->parseMessageId($serverId);
        $dest      = $this->extractFolders($dstFolderId);
        $dest_id   = array_shift($dest);
        $dest_name = $this->backend->folder_id2name($dest_id, $this->device->deviceid);

        if (empty($msg)) {
            throw new Syncroton_Exception_Status_MoveItems(Syncroton_Exception_Status_MoveItems::INVALID_SOURCE);
        }

        if ($dest_name === null) {
            throw new Syncroton_Exception_Status_MoveItems(Syncroton_Exception_Status_MoveItems::INVALID_DESTINATION);
        }

        if (!$this->storage->move_message($msg['uid'], $dest_name, $msg['foldername'])) {
            throw new Syncroton_Exception_Status_MoveItems(Syncroton_Exception_Status_MoveItems::INVALID_SOURCE);
        }

        // Use COPYUID feature (RFC2359) to get the new UID of the copied message
        $copyuid = $this->storage->conn->data['COPYUID'];

        if (is_array($copyuid) && ($uid = $copyuid[1])) {
            return $this->createMessageId($dest_id, $uid);
        }
    }

    /**
     * add entry from xml data
     *
     * @param string                 $folderId Folder identifier
     * @param Syncroton_Model_IEntry $entry    Entry
     *
     * @return array
     */
    public function createEntry($folderId, Syncroton_Model_IEntry $entry)
    {
        // Throw exception here for better handling of unsupported
        // entry creation, it can be object of class Email or SMS here
        throw new Syncroton_Exception_Status_Sync(Syncroton_Exception_Status_Sync::SYNC_SERVER_ERROR);
    }

    /**
     * update existing entry
     *
     * @param string                 $folderId Folder identifier
     * @param string                 $serverId Entry identifier
     * @param Syncroton_Model_IEntry $entry    Entry
     *
     * @return array
     */
    public function updateEntry($folderId, $serverId, Syncroton_Model_IEntry $entry)
    {
        $msg     = $this->parseMessageId($serverId);
        $message = $this->getObject($serverId);

        if (empty($message)) {
            throw new Syncroton_Exception_Status_Sync(Syncroton_Exception_Status_Sync::SYNC_SERVER_ERROR);
        }

        $is_flagged = !empty($message->headers->flags['FLAGGED']);

        // Read status change
        if (isset($entry->read)) {
            // here we update only Read flag
            $flag = (((int)$entry->read != 1) ? 'UN' : '') . 'SEEN';
            $this->storage->set_flag($msg['uid'], $flag, $msg['foldername']);
        }

        // Flag change
        if (empty($entry->flag)) {
            if ($is_flagged) {
                $this->storage->set_flag($msg['uid'], 'UNFLAGGED', $msg['foldername']);
            }
        }
        else if (!$is_flagged && !empty($entry->flag)) {
            if ($entry->flag->flagType && preg_match('/^follow\s*up/i', $entry->flag->flagType)) {
                $this->storage->set_flag($msg['uid'], 'FLAGGED', $msg['foldername']);
            }
        }
    }

    /**
     * delete entry
     *
     * @param string                         $folderId
     * @param string                         $serverId
     * @param Syncroton_Model_SyncCollection $collection
     */
    public function deleteEntry($folderId, $serverId, $collection)
    {
        $trash = kolab_sync::get_instance()->config->get('trash_mbox');
        $msg   = $this->parseMessageId($serverId);

        if (empty($msg)) {
            throw new Syncroton_Exception_Status_Sync(Syncroton_Exception_Status_Sync::SYNC_SERVER_ERROR);
        }

        // move message to trash folder
        if ($collection->deletesAsMoves
            && strlen($trash)
            && $trash != $msg['foldername']
            && $this->storage->folder_exists($trash)
        ) {
            $this->storage->move_message($msg['uid'], $trash, $msg['foldername']);
        }
        // set delete flag
        else {
            $this->storage->set_flag($msg['uid'], 'DELETED', $msg['foldername']);
        }
    }

    /**
     * Send an email
     *
     * @param mixed   $message    MIME message
     * @param boolean $saveInSent Enables saving the sent message in Sent folder
     *
     * @param throws Syncroton_Exception_Status
     */
    public function sendEmail($message, $saveInSent)
    {
        if (!($message instanceof kolab_sync_message)) {
            $message = new kolab_sync_message($message);
        }

        $sent = $message->send($smtp_error);

        if (!$sent) {
            throw new Syncroton_Exception_Status(Syncroton_Exception_Status::MAIL_SUBMISSION_FAILED);
        }

        // Save sent message in Sent folder
        if ($saveInSent) {
            $sent_folder = kolab_sync::get_instance()->config->get('sent_mbox');

            if (strlen($sent_folder) && $this->storage->folder_exists($sent_folder)) {
                return $this->storage->save_message($sent_folder, $message->source(), '', false, array('SEEN'));
            }
        }
    }

    /**
     * Forward an email
     *
     * @param array|string    $itemId      A string LongId or an array with following properties:
     *                                     collectionId, itemId and instanceId
     * @param resource|string $body        MIME message
     * @param boolean         $saveInSent  Enables saving the sent message in Sent folder
     * @param boolean         $replaceMime If enabled, original message would be appended
     *
     * @param throws Syncroton_Exception_Status
     */
    public function forwardEmail($itemId, $body, $saveInSent, $replaceMime)
    {
        /*
        @TODO:
        The SmartForward command can be applied to a meeting. When SmartForward is applied to a recurring meeting,
        the InstanceId element (section 2.2.3.83.2) specifies the ID of a particular occurrence in the recurring meeting.
        If SmartForward is applied to a recurring meeting and the InstanceId element is absent, the server SHOULD
        forward the entire recurring meeting. If the value of the InstanceId element is invalid, the server responds
        with Status element (section 2.2.3.162.15) value 104, as specified in section 2.2.4.

        When the SmartForward command is used for an appointment, the original message is included by the server
        as an attachment to the outgoing message. When the SmartForward command is used for a normal message
        or a meeting, the behavior of the SmartForward command is the same as that of the SmartReply command (section 2.2.2.18).
        */

        $msg = $this->parseMessageId($itemId);

        if (empty($msg)) {
            throw new Syncroton_Exception_Status(Syncroton_Exception_Status::ITEM_NOT_FOUND);
        }

        // Parse message
        $sync_msg = new kolab_sync_message($body);

        // forward original message as attachment
        if (!$replaceMime) {
            $this->storage->set_folder($msg['foldername']);
            $attachment = $this->storage->get_raw_body($msg['uid']);

            if (empty($attachment)) {
                throw new Syncroton_Exception_Status(Syncroton_Exception_Status::ITEM_NOT_FOUND);
            }

            $sync_msg->add_attachment($attachment, array(
                'encoding'     => '8bit',
                'content_type' => 'message/rfc822',
                'disposition'  => 'inline',
                //'name'         => 'message.eml',
            ));
        }

        // Send message
        $sent = $this->sendEmail($sync_msg, $saveInSent);

        // Set FORWARDED flag on the replied message
        if (empty($message->headers->flags['FORWARDED'])) {
            $this->storage->set_flag($msg['uid'], 'FORWARDED', $msg['foldername']);
        }
    }

    /**
     * Reply to an email
     *
     * @param array|string    $itemId      A string LongId or an array with following properties:
     *                                     collectionId, itemId and instanceId
     * @param resource|string $body        MIME message
     * @param boolean         $saveInSent  Enables saving the sent message in Sent folder
     * @param boolean         $replaceMime If enabled, original message would be appended
     *
     * @param throws Syncroton_Exception_Status
     */
    public function replyEmail($itemId, $body, $saveInSent, $replaceMime)
    {
        $msg     = $this->parseMessageId($itemId);
        $message = $this->getObject($itemId);

        if (!$message) {
            throw new Syncroton_Exception_Status(Syncroton_Exception_Status::ITEM_NOT_FOUND);
        }

        $sync_msg = new kolab_sync_message($body);
        $headers = $sync_msg->headers();

        // Add References header
        if (empty($headers['References'])) {
            $sync_msg->set_header('References', trim($message->headers->references . ' ' . $message->headers->messageID));
        }

        // Get original message body
        if (!$replaceMime) {
            // @TODO: here we're assuming that reply message is in text/plain format
            // So, original message will be converted to plain text if needed
            $message_body = $this->getMessageBody($message, false);

            // Quote original message body
            $message_body = self::wrap_and_quote(trim($message_body), 72);

            // Join bodies
            $sync_msg->append("\n" . ltrim($message_body));
        }

        // Send message
        $sent = $this->sendEmail($sync_msg, $saveInSent);

        // Set ANSWERED flag on the replied message
        if (empty($message->headers->flags['ANSWERED'])) {
            $this->storage->set_flag($msg['uid'], 'ANSWERED', $msg['foldername']);
        }
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
        $folders    = $this->extractFolders($folderid);
        $filter_str = 'ALL UNDELETED';

        // convert filter into one IMAP search string
        foreach ($filter as $idx => $filter_item) {
            if (is_array($filter_item)) {
                // This is a request for changes since list time
                // we'll use HIGHESTMODSEQ value from the last Sync
                if ($filter_item[0] == 'changed' && $filter_item[1] == '>') {
                    $modseq = (array) $this->backend->modseq_get($this->device->id, $folderid, $filter_item[2]);
                    $modseq_data = array();
                }
            }
            else {
                $filter_str .= ' ' . $filter_item;
            }
        }

        $result = $result_type == self::RESULT_COUNT ? 0 : array();
        // no sorting for best performance
        $sort_by = null;
        $found   = 0;

        foreach ($folders as $folder_id) {
            $foldername = $this->backend->folder_id2name($folder_id, $this->device->deviceid);

            if ($foldername === null) {
                continue;
            }

            $found++;

            $this->storage->set_folder($foldername);

            // Syncronize folder (if it wasn't synced in this request already)
            if ($this->lastsync_folder != $foldername
                || $this->lastsync_time < time() - Syncroton_Registry::getPingTimeout()
            ) {
                $this->storage->folder_sync($foldername);
            }

            $this->lastsync_folder = $foldername;
            $this->lastsync_time   = time();

            // We're in "get changes" mode
            if (isset($modseq_data)) {
                $folder_data = $this->storage->folder_data($foldername);
                $got_changes = true;

                if ($folder_data['HIGHESTMODSEQ']) {
                    $modseq_data[$foldername] = $folder_data['HIGHESTMODSEQ'];
                    if ($modseq_data[$foldername] != $modseq[$foldername]) {
                        $modseq_update = true;
                    }
                    else {
                        $got_changes = false;
                    }
                }

                // If previous HIGHESTMODSEQ doesn't exist we can't get changes
                // We can only get folder's HIGHESTMODSEQ value and store it for the next try
                // Skip search if HIGHESTMODSEQ didn't change
                if (!$got_changes || empty($modseq) || empty($modseq[$foldername])) {
                    continue;
                }

                $filter_str .= " MODSEQ " . ($modseq[$foldername] + 1);
            }

            // We could use messages cache by replacing search() with index()
            // in some cases. This however is possible only if user has skip_deleted=true,
            // in his Roundcube preferences, otherwise we'd make often cache re-initialization,
            // because Roundcube message cache can work only with one skip_deleted
            // setting at a time. We'd also need to make sure folder_sync() was called
            // before (see above).
            //
            // if ($filter_str == 'ALL UNDELETED')
            //     $search = $this->storage->index($foldername, null, null, true, true);
            // else

            $search = $this->storage->search_once($foldername, $filter_str);

            if (!($search instanceof rcube_result_index) || $search->is_error()) {
                throw new Syncroton_Exception_Status(Syncroton_Exception_Status::SERVER_ERROR);
            }

            switch ($result_type) {
            case self::RESULT_COUNT:
                $result += (int) $search->count();
                break;

            case self::RESULT_UID:
                if ($uids = $search->get()) {
                    foreach ($uids as $idx => $uid) {
                        $uids[$idx] = $this->createMessageId($folder_id, $uid);
                    }
                    $result = array_merge($result, $uids);
                }
                break;
/*
            case self::RESULT_OBJECT:
            default:
                if ($objects = $folder->select($filter)) {
                    $result = array_merge($result, $objects);
                }
*/
            }
        }

        if (!$found) {
            throw new Syncroton_Exception_Status(Syncroton_Exception_Status::SERVER_ERROR);
        }

        if (!empty($modseq_update)) {
            $this->backend->modseq_set($this->device->id, $folderid,
                $this->syncTimeStamp, $modseq_data);
        }

        return $result;
    }

    /**
     * ActiveSync Search handler
     *
     * @param Syncroton_Model_StoreRequest $store Search query
     *
     * @return Syncroton_Model_StoreResponse Complete Search response
     */
    public function search(Syncroton_Model_StoreRequest $store)
    {
        list($folders, $search_str) = $this->parse_search_query($store);

        if (empty($search_str)) {
            throw new Exception('Empty/invalid search request');
        }

        if (!is_array($folders)) {
            throw new Syncroton_Exception_Status(Syncroton_Exception_Status::SERVER_ERROR);
        }

        $result = array();
        // no sorting for best performance
        $sort_by = null;

        // @TODO: caching with Options->RebuildResults support

        foreach ($folders as $folderid) {
            $foldername = $this->backend->folder_id2name($folderid, $this->device->deviceid);

            if ($foldername === null) {
                continue;
            }

//            $this->storage->set_folder($foldername);
//            $this->storage->folder_sync($foldername);

            $search = $this->storage->search_once($foldername, $search_str);

            if (!($search instanceof rcube_result_index)) {
                continue;
            }

            $uids = $search->get();
            foreach ($uids as $idx => $uid) {
                $uids[$idx] = new Syncroton_Model_StoreResponseResult(array(
                    'longId'       => $this->createMessageId($folderid, $uid),
                    'collectionId' => $folderid,
                    'class'        => 'Email',
                ));
            }
            $result = array_merge($result, $uids);

            // We don't want to search all folders if we've got already a lot messages
            if (count($result) >= self::MAX_SEARCH_RESULT) {
                break;
            }
        }

        $result   = array_values($result);
        $response = new Syncroton_Model_StoreResponse();

        // Calculate requested range
        $start = (int) $store->options['range'][0];
        $limit = (int) $store->options['range'][1] + 1;
        $total = count($result);
        $response->total = $total;

        // Get requested chunk of data set
        if ($total) {
            if ($start > $total) {
                $start = $total;
            }
            if ($limit > $total) {
                $limit = max($start+1, $total);
            }
            if ($start > 0 || $limit < $total) {
                $result = array_slice($result, $start, $limit-$start);
            }

            $response->range = array($start, $start + count($result) - 1);
        }

        // Build result array, convert to ActiveSync format
        foreach ($result as $idx => $rec) {
            $rec->properties    = $this->getSearchEntry($rec->longId, $store->options);
            $response->result[] = $rec;
            unset($result[$idx]);
        }

        return $response;
    }

    /**
     * Converts ActiveSync search parameters into IMAP search string
     */
    protected function parse_search_query($store)
    {
        $options    = $store->options;
        $query      = $store->query;
        $search_str = '';
        $folders    = array();

        if (empty($query) || !is_array($query)) {
            return array();
        }

        if (isset($query['and']['freeText']) && strlen($query['and']['freeText'])) {
            $search = $query['and']['freeText'];
        }

        if (!empty($query['and']['collections'])) {
            foreach ($query['and']['collections'] as $collection) {
                $folders = array_merge($folders, $this->extractFolders($collection));
            }
        }

        if (!empty($query['and']['greaterThan'])
            && !empty($query['and']['greaterThan']['dateReceived'])
            && !empty($query['and']['greaterThan']['value'])
        ) {
            $search_str .= ' SINCE ' . $query['and']['greaterThan']['value']->format('d-M-Y');
        }

        if (!empty($query['and']['lessThan'])
            && !empty($query['and']['lessThan']['dateReceived'])
            && !empty($query['and']['lessThan']['value'])
        ) {
            $search_str .= ' BEFORE ' . $query['and']['lessThan']['value']->format('d-M-Y');
        }

        if ($search !== null) {
            // @FIXME: should we use TEXT/BODY search?
            // ActiveSync protocol specification says "indexed fields"
            $search_keys = array('SUBJECT', 'TO', 'FROM', 'CC');
            $search_str .= str_repeat(' OR', count($search_keys)-1);
            foreach ($search_keys as $key) {
                $search_str .= sprintf(" %s {%d}\r\n%s", $key, strlen($search), $search);
            }
        }

        if (empty($search_str)) {
            return array();
        }

        $search_str = 'ALL UNDELETED ' . trim($search_str);

        // @TODO: DeepTraversal
        if (empty($folders)) {
            $folders = $this->listFolders();

            if (is_array($folders)) {
                $folders = array_keys($folders);
            }
        }

        return array($folders, $search_str);
    }

    /**
     * Fetches the entry from the backend
     */
    protected function getObject($entryid, &$folder = null)
    {
        $message = $this->parseMessageId($entryid);

        if (empty($message)) {
            // @TODO: exception?
            return null;
        }

        // set current folder
        $this->storage->set_folder($message['foldername']);

        // get message
        $message = new rcube_message($message['uid']);

        return $message;
    }

    /**
     * @return Syncroton_Model_FileReference
     */
    public function getFileReference($fileReference)
    {
        list($folderid, $uid, $part_id) = explode('::', $fileReference);

        $message = $this->getObject($fileReference);

        if (!$message) {
            throw new Syncroton_Exception_NotFound('Message not found');
        }

        $part         = $message->mime_parts[$part_id];
        $body         = $message->get_part_content($part_id);
        $content_type = $part->mimetype;

        return new Syncroton_Model_FileReference(array(
            'contentType' => $content_type,
            'data'        => $body,
        ));
    }

    /**
     * Parses entry ID to get folder name and UID of the message
     */
    protected function parseMessageId($entryid)
    {
        // replyEmail/forwardEmail
        if (is_array($entryid)) {
            $entryid = $entryid['itemId'];
        }

        list($folderid, $uid) = explode('::', $entryid);
        $foldername = $this->backend->folder_id2name($folderid, $this->device->deviceid);

        if ($foldername === null || $foldername === false) {
            // @TODO exception?
            return null;
        }

        return array(
            'uid'        => $uid,
            'folderid'   => $folderid,
            'foldername' => $foldername,
        );
    }

    /**
     * Creates entry ID of the message
     */
    public function createMessageId($folderid, $uid)
    {
        return $folderid . '::' . $uid;
    }

    /**
     * Returns body of the message in specified format
     */
    protected function getMessageBody($message, $html = false)
    {
        if (!is_array($message->parts) && empty($message->body)) {
            return '';
        }

        if (!empty($message->parts)) {
            foreach ($message->parts as $part) {
                // skip no-content and attachment parts (#1488557)
                if ($part->type != 'content' || !$part->size || $message->is_attachment($part)) {
                    continue;
                }

                return $this->getMessagePartBody($message, $part, $html);
            }
        }

        return $this->getMessagePartBody($message, $message, $html);
    }

    /**
     * Returns body of the message part in specified format
     */
    protected function getMessagePartBody($message, $part, $html = false)
    {
        // Check if we have enough memory to handle the message in it
        // @FIXME: we need up to 5x more memory than the body
        if (!rcube_utils::mem_check($part->size * 5)) {
            return '';
        }

        if (empty($part->ctype_parameters) || empty($part->ctype_parameters['charset'])) {
            $part->ctype_parameters['charset'] = $message->headers->charset;
        }

        // fetch part if not available
        if (!isset($part->body)) {
            $part->body = $message->get_part_content($part->mime_id);
        }

        // message is cached but not exists, or other error
        if ($part->body === false) {
            return '';
        }

        $body = $part->body;

        if ($html) {
            if ($part->ctype_secondary == 'html') {
                // charset was converted to UTF-8 in rcube_storage::get_message_part(),
                // change/add charset specification in HTML accordingly
                $meta = '<meta http-equiv="Content-Type" content="text/html; charset='.RCUBE_CHARSET.'" />';

                // remove old meta tag and add the new one, making sure
                // that it is placed in the head
                $body = preg_replace('/<meta[^>]+charset=[a-z0-9-_]+[^>]*>/Ui', '', $body);
                $body = preg_replace('/(<head[^>]*>)/Ui', '\\1'.$meta, $body, -1, $rcount);
                if (!$rcount) {
                    $body = '<head>' . $meta . '</head>' . $body;
                }
            }
            else if ($part->ctype_secondary == 'enriched') {
                $body = rcube_enriched::to_html($body);
            }
            else {
                $body = '<pre>' . $body . '</pre>';
            }
        }
        else {
            if ($part->ctype_secondary == 'enriched') {
                $body = rcube_enriched::to_html($body);
                $part->ctype_secondary = 'html';
            }

            if ($part->ctype_secondary == 'html') {
                $txt = new rcube_html2text($body, false, true);
                $body = $txt->get_text();
            }
            else {
                if ($part->ctype_secondary == 'plain' && $part->ctype_parameters['format'] == 'flowed') {
                    $body = rcube_mime::unfold_flowed($body);
                }
            }
        }

        return $body;
    }

    /**
     * Converts and truncates message body for use in <Preview>
     *
     * @return string Truncated plain text message
     */
    protected function getPreview($body, $type, $size)
    {
        if ($type == Syncroton_Command_Sync::BODY_TYPE_HTML) {
            $txt  = new rcube_html2text($body, false, true);
            $body = $txt->get_text();
        }

        // size limit defined in ActiveSync protocol
        if ($size > 255) {
            $size = 255;
        }

        return mb_strcut(trim($body), 0, $size);
    }

    public static function charset_to_cp($charset)
    {
        // @TODO: ?????
        // The body is converted to utf-8 in get_part_content(), what about headers?
        return 65001; // UTF-8

        $aliases = array(
            'asmo708' => 708,
            'shiftjis' => 932,
            'gb2312' => 936,
            'ksc56011987' => 949,
            'big5' => 950,
            'utf16' => 1200,
            'utf16le' => 1200,
            'unicodefffe' => 1201,
            'utf16be' => 1201,
            'johab' => 1361,
            'macintosh' => 10000,
            'macjapanese' => 10001,
            'macchinesetrad' => 10002,
            'mackorean' => 10003,
            'macarabic' => 10004,
            'machebrew' => 10005,
            'macgreek' => 10006,
            'maccyrillic' => 10007,
            'macchinesesimp' => 10008,
            'macromanian' => 10010,
            'macukrainian' => 10017,
            'macthai' => 10021,
            'macce' => 10029,
            'macicelandic' => 10079,
            'macturkish' => 10081,
            'maccroatian' => 10082,
            'utf32' => 12000,
            'utf32be' => 12001,
            'chinesecns' => 20000,
            'chineseeten' => 20002,
            'ia5' => 20105,
            'ia5german' => 20106,
            'ia5swedish' => 20107,
            'ia5norwegian' => 20108,
            'usascii' => 20127,
            'ibm273' => 20273,
            'ibm277' => 20277,
            'ibm278' => 20278,
            'ibm280' => 20280,
            'ibm284' => 20284,
            'ibm285' => 20285,
            'ibm290' => 20290,
            'ibm297' => 20297,
            'ibm420' => 20420,
            'ibm423' => 20423,
            'ibm424' => 20424,
            'ebcdickoreanextended' => 20833,
            'ibmthai' => 20838,
            'koi8r' => 20866,
            'ibm871' => 20871,
            'ibm880' => 20880,
            'ibm905' => 20905,
            'ibm00924' => 20924,
            'cp1025' => 21025,
            'koi8u' => 21866,
            'iso88591' => 28591,
            'iso88592' => 28592,
            'iso88593' => 28593,
            'iso88594' => 28594,
            'iso88595' => 28595,
            'iso88596' => 28596,
            'iso88597' => 28597,
            'iso88598' => 28598,
            'iso88599' => 28599,
            'iso885913' => 28603,
            'iso885915' => 28605,
            'xeuropa' => 29001,
            'iso88598i' => 38598,
            'iso2022jp' => 50220,
            'csiso2022jp' => 50221,
            'iso2022jp' => 50222,
            'iso2022kr' => 50225,
            'eucjp' => 51932,
            'euccn' => 51936,
            'euckr' => 51949,
            'hzgb2312' => 52936,
            'gb18030' => 54936,
            'isciide' => 57002,
            'isciibe' => 57003,
            'isciita' => 57004,
            'isciite' => 57005,
            'isciias' => 57006,
            'isciior' => 57007,
            'isciika' => 57008,
            'isciima' => 57009,
            'isciigu' => 57010,
            'isciipa' => 57011,
            'utf7' => 65000,
            'utf8' => 65001,
        );

        $charset = strtolower($charset);
        $charset = preg_replace(array('/^x-/', '/[^a-z0-9]/'), '', $charset);

        if (isset($aliases[$charset])) {
            return $aliases[$charset];
        }

        if (preg_match('/^(ibm|dos|cp|windows|win)[0-9]+/', $charset, $m)) {
            return substr($charset, strlen($m[1]) + 1);
        }
    }

    /**
     * Wrap text to a given number of characters per line
     * but respect the mail quotation of replies messages (>).
     * Finally add another quotation level by prepending the lines
     * with >
     *
     * @param string $text   Text to wrap
     * @param int    $length The line width
     *
     * @return string The wrapped text
     */
    protected static function wrap_and_quote($text, $length = 72)
    {
        // Function stolen from Roundcube ;)
        // Rebuild the message body with a maximum of $max chars, while keeping quoted message.
        $max = min(77, $length + 8);
        $lines = preg_split('/\r?\n/', trim($text));
        $out = '';

        foreach ($lines as $line) {
            // don't wrap already quoted lines
            if ($line[0] == '>') {
                $line = '>' . rtrim($line);
            }
            else if (mb_strlen($line) > $max) {
                $newline = '';
                foreach (explode("\n", rcube_mime::wordwrap($line, $length - 2)) as $l) {
                    if (strlen($l)) {
                        $newline .= '> ' . $l . "\n";
                    }
                    else {
                        $newline .= ">\n";
                    }
                }
                $line = rtrim($newline);
            }
            else {
                $line = '> ' . $line;
            }

            // Append the line
            $out .= $line . "\n";
        }

        return $out;
    }
}
