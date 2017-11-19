<?php
/**
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2016, Kolab Systems AG                                |
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
 * Document editing sessions handling
 */
class file_document
{
    protected $api;
    protected $rc;
    protected $user;
    protected $sessions_table    = 'chwala_sessions';
    protected $invitations_table = 'chwala_invitations';
    protected $icache            = array();
    protected $file_meta_items   = array('type', 'name', 'size', 'modified');

    const STATUS_INVITED        = 'invited';
    const STATUS_REQUESTED      = 'requested';
    const STATUS_ACCEPTED       = 'accepted';
    const STATUS_DECLINED       = 'declined';
    const STATUS_DECLINED_OWNER = 'declined-owner'; // same as 'declined' but done by the session owner
    const STATUS_ACCEPTED_OWNER = 'accepted-owner'; // same as 'accepted' but done by the session owner

    const DB_DATE_FORMAT = 'Y-m-d H:i:s';


    /**
     * Class constructor
     *
     * @param file_api $api Chwala API app instance
     */
    public function __construct($api)
    {
        $this->rc   = rcube::get_instance();
        $this->api  = $api;
        $this->user = $_SESSION['user'];

        $db = $this->rc->get_dbh();
        $this->sessions_table    = $db->table_name($this->sessions_table);
        $this->invitations_table = $db->table_name($this->invitations_table);
    }

    /**
     * Detect type of file_document class to use for specified session
     *
     * @param file_api $api        Chwala API app instance
     * @param string   $session_id Document session ID
     *
     * @return file_document Document object
     */
    public static function get_handler($api, $session_id)
    {
        // we add "w-" prefix to wopi session identifiers,
        // so we can distinguish it from manticore sessions
        if (strpos($session_id, 'w-') === 0) {
            return new file_wopi($api);
        }

        return new file_manticore($api);
    }

    /**
     * Return viewer URI for specified file/session. This creates
     * a new collaborative editing session when needed.
     *
     * @param string $file        File path
     * @param array  &$file_info  File metadata (e.g. type)
     * @param string &$session_id Optional session ID to join to
     * @param string $readonly    Create readonly (one-time) session
     *
     * @return string An URI for specified file/session
     * @throws Exception
     */
    public function session_start($file, &$file_info, &$session_id = null, $readonly = false)
    {
        if ($file !== null) {
            $uri = $this->path2uri($file, $driver);
        }

        $backend = $this->api->get_backend();

        if ($session_id) {
            $session = $this->session_info($session_id);

            if (empty($session)) {
                throw new Exception("Document session not found.", file_api_core::ERROR_CODE);
            }

            // check session ownership
            if ($session['owner'] != $this->user) {
                // check if the user was invited
                $invitations = $this->invitations_find(array('session_id' => $session_id, 'user' => $this->user));
                $states      = array(self::STATUS_INVITED, self::STATUS_ACCEPTED, self::STATUS_ACCEPTED_OWNER);

                if (empty($invitations) || !in_array($invitations[0]['status'], $states)) {
                    throw new Exception("No permission to join the editing session.", file_api_core::ERROR_CODE);
                }

                // automatically accept the invitation, if not done yet
                if ($invitations[0]['status'] == self::STATUS_INVITED) {
                    $this->invitation_update($session_id, $this->user, self::STATUS_ACCEPTED);
                }
            }

            $file_info['type'] = $session['type'];
        }
        else if (!empty($uri)) {
            // To prevent from creating new sessions for the same file+user
            // (e.g. when user uses F5 to refresh the page), we check first
            // if such a session exist and continue with it
            $db = $this->rc->get_dbh();

            $res = $db->query("SELECT `id` FROM `{$this->sessions_table}`"
                . " WHERE `owner` = ? AND `uri` = ? AND `readonly` = ?",
                $this->user, $uri, intval($readonly));

            if ($row = $db->fetch_assoc($res)) {
                $session_id = $row['id'];
                $res = true;
            }
            else if (!$db->is_error($res)) {
                $session_id = rcube_utils::bin2ascii(md5(time() . $uri, true));
                $owner      = $this->user;
                $data       = array('origin' => $this->get_origin());

                // store some file data, they will be used
                // by invited users that has no access to the storage
                foreach ($this->file_meta_items as $item) {
                    if (isset($file_info[$item])) {
                        $data[$item] = $file_info[$item];
                    }
                }

                // bind the session ID with editor type (see file_document::get_handler())
                if ($this instanceof file_wopi) {
                    $session_id = 'w-' . $session_id;
                }

                // we'll store user credentials if the file comes from
                // an external source that requires authentication
                if ($backend != $driver) {
                    $auth = $driver->auth_info();
                    $auth['password']  = $this->rc->encrypt($auth['password']);
                    $data['auth_info'] = $auth;
                }

                $res = $this->session_create($session_id, $uri, $owner, $data, $readonly);
            }

            if (!$res) {
                throw new Exception("Failed creating document editing session", file_api_core::ERROR_CODE);
            }
        }
        else {
            throw new Exception("Failed creating document editing session (unknown file)", file_api_core::ERROR_CODE);
        }

        // Implementations should return real URI
        return '';
    }

    /**
     * Get file path (not URI) from session.
     *
     * @param string $id        Session ID
     * @param bool   $join_mode Throw exception only if session does not exist
     *
     * @return array File info (file, type, size)
     * @throws Exception
     */
    public function session_file($id, $join_mode = false)
    {
        $session = $this->session_info($id);

        if (empty($session)) {
            throw new Exception("Document session not found.", file_api_core::ERROR_CODE);
        }

        $path = $this->uri2path($session['uri']);

        if (empty($path) && (!$join_mode || $session['owner'] == $this->user)) {
            throw new Exception("Document session not found.", file_api_core::ERROR_CODE);
        }

        // check permissions to the session
        if ($session['owner'] != $this->user) {
            $invitations = $this->invitations_find(array('session_id' => $id, 'user' => $this->user));
            $states      = array(self::STATUS_INVITED, self::STATUS_ACCEPTED, self::STATUS_ACCEPTED_OWNER);

            if (empty($invitations) || !in_array($invitations[0]['status'], $states)) {
                throw new Exception("No permission to join the editing session.", file_api_core::ERROR_CODE);
            }
        }

        $result = array('file' => $path);

        foreach ($this->file_meta_items as $item) {
            if (isset($session[$item])) {
                $result[$item] = $session[$item];
            }
        }

        return $result;
    }

    /**
     * Get editing session info
     *
     * @param string $id               Session identifier
     * @param bool   $with_invitations Return invitations list
     *
     * @return array Session data
     */
    public function session_info($id, $with_invitations = false)
    {
        $session = $this->icache["session:$id"];

        if (!$session) {
            $db     = $this->rc->get_dbh();
            $result = $db->query("SELECT * FROM `{$this->sessions_table}`"
                . " WHERE `id` = ?", $id);

            if ($row = $db->fetch_assoc($result)) {
                $session = $this->session_info_parse($row);

                $this->icache["session:$id"] = $session;
            }
        }

        if ($session) {
            if ($session['owner'] == $this->user) {
                $session['is_owner'] = true;
            }

            if ($with_invitations && $session['is_owner']) {
                $session['invitations'] = $this->invitations_find(array('session_id' => $id));
            }
        }

        return $session;
    }

    /**
     * Find editing sessions for specified path
     */
    public function session_find($path, $invitations = true)
    {
        // create an URI for specified path
        $uri = trim($this->path2uri($path), '/') . '/';

        // get existing sessions
        $sessions = array();
        $filter   = array('file', 'owner', 'owner_name', 'is_owner');
        $db       = $this->rc->get_dbh();
        $result   = $db->query("SELECT * FROM `{$this->sessions_table}`"
            . " WHERE `readonly` = 0 AND `uri` LIKE '" . $db->escape($uri) . "%'");

        while ($row = $db->fetch_assoc($result)) {
            if ($path = $this->uri2path($row['uri'])) {
                $sessions[$row['id']] = $this->session_info_parse($row, $path, $filter);
            }
        }

        // set 'is_invited' flag
        if ($invitations && !empty($sessions)) {
            $invitations = $this->invitations_find(array('user' => $this->user));
            $states      = array(self::STATUS_INVITED, self::STATUS_ACCEPTED, self::STATUS_ACCEPTED_OWNER);

            foreach ($invitations as $invitation) {
                if (!empty($sessions[$invitation['session_id']]) && in_array($invitation['status'], $states)) {
                    $sessions[$invitation['session_id']]['is_invited'] = true;
                }
            }
        }

        return $sessions;
    }

    /**
     * Delete editing session (only owner can do that)
     *
     * @param string $id Session identifier
     */
    public function session_delete($id)
    {
        $db     = $this->rc->get_dbh();
        $result = $db->query("DELETE FROM `{$this->sessions_table}`"
            . " WHERE `id` = ? AND `owner` = ?",
            $id, $this->user);

        return $db->affected_rows($result) > 0;
    }

    /**
     * Update editing session
     *
     * @param string $id   Session ID
     * @param array  $data Session metadata
     */
    public function session_update($id, $data)
    {
       $db     = $this->rc->get_dbh();
       $result = $db->query("SELECT `data` FROM `{$this->sessions_table}`"
           . " WHERE `id` = ?", $id);

       if ($row = $db->fetch_assoc($result)) {
            // merge only relevant information
            $data = array_intersect_key($data, array_flip($this->file_meta_items));
            if (empty($data)) {
                return true;
            }

            $sess_data = json_decode($row['data'], true);
            $sess_data = array_merge($sess_data, $data);

            $result = $db->query("UPDATE `{$this->sessions_table}`"
                . " SET `data` = ? WHERE `id` = ?",
                json_encode($sess_data), $id);

            return $db->affected_rows($result) > 0;
        }

        return false;
    }

    /**
     * Create editing session
     */
    protected function session_create($id, $uri, $owner, $data, $readonly = false)
    {
        // get user name
        $owner_name = $this->api->resolve_user($owner) ?: '';

        $db     = $this->rc->get_dbh();
        $result = $db->query("INSERT INTO `{$this->sessions_table}`"
            . " (`id`, `uri`, `owner`, `owner_name`, `data`, `readonly`)"
            . " VALUES (?, ?, ?, ?, ?, ?)",
            $id, $uri, $owner, $owner_name, json_encode($data), intval($readonly));

        return $db->affected_rows($result) > 0;
    }

    /**
     * Find sessions, including:
     *   1. to which the user has access (is a creator or has been invited)
     *   2. to which the user is considered eligible to request authorization
     *     to participate in the session by already having access to the file
     * Note: Readonly sessions are ignored here.
     *
     * @param array $param List parameters
     *
     * @return array Sessions list
     */
    public function sessions_list($param = array())
    {
        $db       = $this->rc->get_dbh();
        $sessions = array();

        // 1. Get sessions user has access to
        $result = $db->query("SELECT * FROM `{$this->sessions_table}` s"
            . " WHERE s.`readonly` = 0 AND (s.`owner` = ? OR s.`id` IN ("
                . "SELECT i.`session_id` FROM `{$this->invitations_table}` i"
                . " WHERE i.`user` = ?"
            . "))",
            $this->user, $this->user);

        if ($db->is_error($result)) {
            throw new Exception("Internal error.", file_api_core::ERROR_CODE);
        }

        while ($row = $db->fetch_assoc($result)) {
            if ($path = $this->uri2path($row['uri'], true)) {
                $sessions[$row['id']] = $this->session_info_parse($row, $path);
            }
        }

        // 2. Get sessions user is eligible
        // - get list of all folder URIs and find sessions for files in these locations
        // @FIXME: in corner cases (user has many folders) this may produce a big query,
        // maybe fetching all sessions and then comparing with list of locations would be faster?
        $uris = $this->all_folder_locations();

        if (!empty($uris)) {
            $where = array_map(function($uri) use ($db) {
                    return 's.`uri` LIKE ' . $db->quote(str_replace('%', '_', $uri) . '/%');
                }, $uris);

            $result = $db->query("SELECT * FROM `{$this->sessions_table}` s"
                . " WHERE s.`readonly` = 0 AND (" . join(' OR ', $where) . ")");

            if ($db->is_error($result)) {
                throw new Exception("Internal error.", file_api_core::ERROR_CODE);
            }

            while ($row = $db->fetch_assoc($result)) {
                if (empty($sessions[$row['id']])) {
                    // remove filename (and anything after it) so we have the folder URI
                    // to check if it's on the folders list we have
                    $uri = substr($row['uri'], 0, strrpos($row['uri'], '/'));
                    if (in_array($uri, $uris) && ($path = $this->uri2path($row['uri'], true))) {
                        $sessions[$row['id']] = $this->session_info_parse($row, $path);
                    }
                }
            }
        }

        // set 'is_invited' flag
        if (!empty($sessions)) {
            $invitations = $this->invitations_find(array('user' => $this->user));
            $states      = array(self::STATUS_INVITED, self::STATUS_ACCEPTED, self::STATUS_ACCEPTED_OWNER);

            foreach ($invitations as $invitation) {
                if (!empty($sessions[$invitation['session_id']]) && in_array($invitation['status'], $states)) {
                    $sessions[$invitation['session_id']]['is_invited'] = true;
                }
            }
        }

        // Sorting
        $sort  = !empty($params['sort']) ? $params['sort'] : 'name';
        $index = array();

        if (in_array($sort, array('name', 'file', 'owner'))) {
            foreach ($sessions as $key => $val) {
                if ($sort == 'name' || $sort == 'file') {
                    $path        = explode(file_storage::SEPARATOR, $val['file']);
                    $index[$key] = $path[count($path) - 1];
                    continue;
                }

                $index[$key] = $val[$sort];
            }
            array_multisort($index, SORT_ASC, SORT_LOCALE_STRING, $sessions);
        }

        if ($params['reverse']) {
            $sessions = array_reverse($sessions, true);
        }

        return $sessions;
    }

    /**
     * Retern extra editor parameters to post the the viewer iframe
     *
     * @param array $info File info
     *
     * @return array POST parameters
     */
    public function editor_post_params($info)
    {
        return array();
    }

    /**
     * Find invitations for current user. This will return all
     * invitations related to the user including his sessions.
     *
     * @param array $filter Search filter (see self::invitations_find())
     *
     * @return array Invitations list
     */
    public function invitations_list($filter = array())
    {
        $filter['user'] = $this->user;

        // list of invitations to the user or requested by him
        $result = $this->invitations_find($filter, true);

        unset($filter['user']);
        $filter['owner'] = $this->user;

        // other invitations that belong to the sessions owned by the user
        if ($other = $this->invitations_find($filter, true)) {
            $result = array_merge($result, $other);
        }

        return $result;
    }

    /**
     * Find invitations for specified filter
     *
     * @param array $filter Search filter (see self::invitations_find())
     *                      - session_id: session identifier
     *                      - timestamp: "changed > ?" filter
     *                      - user: Invitation user identifier
     *                      - owner: Session owner identifier
     * @param bool $extended Return session file names
     *
     * @return array Invitations list
     */
    public function invitations_find($filter, $extended = false)
    {
        $db     = $this->rc->get_dbh();
        $query  = '';
        $select = "i.*";

        foreach ($filter as $column => $value) {
            if ($column == 'timestamp') {
                $where[] = "i.`changed` > " . $db->quote($this->db_datetime($value));
            }
            else if ($column == 'owner') {
                $join[] = "`{$this->sessions_table}` s ON (i.`session_id` = s.`id`)";
                $where[] = "s.`owner` = " . $db->quote($value);
            }
            else {
                $where[] = "i.`$column` = " . $db->quote($value);
            }
        }

        if ($extended) {
            $select .= ", s.`uri`, s.`owner`, s.`owner_name`";
            $join[]  = "`{$this->sessions_table}` s ON (i.`session_id` = s.`id`)";
        }

        if (!empty($join)) {
            $query .= ' JOIN ' . implode(' JOIN ', array_unique($join));
        }

        if (!empty($where)) {
            $query .= ' WHERE ' . implode(' AND ', array_unique($where));
        }

        $result = $db->query("SELECT $select FROM `{$this->invitations_table}` i"
            . "$query ORDER BY i.`changed`");

        if ($db->is_error($result)) {
            throw new Exception("Internal error.", file_api_core::ERROR_CODE);
        }

        $invitations = array();

        while ($row = $db->fetch_assoc($result)) {
            if ($extended) {
                try {
                    // add unix-timestamp of the `changed` date to the result
                    $dt = new DateTime($row['changed']);
                    $row['timestamp'] = $dt->format('U');
                }
                catch(Exception $e) { }

                // add filename to the result
                $filename = parse_url($row['uri'], PHP_URL_PATH);
                $filename = pathinfo($filename, PATHINFO_BASENAME);
                $filename = rawurldecode($filename);

                $row['filename'] = $filename;

                if ($path = $this->uri2path($row['uri'])) {
                    $row['file'] = $path;
                }

                unset($row['uri']);
            }

            $invitations[] = $row;
        }

        return $invitations;
    }

    /**
     * Create an invitation
     *
     * @param string $session_id Document session identifier
     * @param string $user       User identifier (use null for current user)
     * @param string $status     Invitation status (invited, requested)
     * @param string $comment    Invitation description/comment
     * @param string &$user_name Optional user name
     *
     * @throws Exception
     */
    public function invitation_create($session_id, $user, $status = 'invited', $comment = '', &$user_name = '')
    {
        if (empty($user)) {
            $user = $this->user;
        }

        if ($status != self::STATUS_INVITED && $status != self::STATUS_REQUESTED) {
            throw new Exception("Invalid invitation status.", file_api_core::ERROR_CODE);
        }

        // get session information
        $session = $this->session_info($session_id);

        if (empty($session)) {
            throw new Exception("Document session not found.", file_api_core::ERROR_CODE);
        }

        // check session ownership, only owner can create 'new' invitations
        if ($status == self::STATUS_INVITED && $session['owner'] != $this->user) {
            throw new Exception("No permission to create an invitation.", file_api_core::ERROR_CODE);
        }

        if ($session['owner'] == $user) {
            throw new Exception("Not possible to create an invitation for the session creator.", file_api_core::ERROR_CODE);
        }

        // get user name
        if (empty($user_name)) {
            $user_name = $this->api->resolve_user($user) ?: '';
        }

        // insert invitation
        $db     = $this->rc->get_dbh();
        $result = $db->query("INSERT INTO `{$this->invitations_table}`"
            . " (`session_id`, `user`, `user_name`, `status`, `comment`, `changed`)"
            . " VALUES (?, ?, ?, ?, ?, ?)",
            $session_id, $user, $user_name, $status, $comment ?: '', $this->db_datetime());

        if (!$db->affected_rows($result)) {
            throw new Exception("Failed to create an invitation.", file_api_core::ERROR_CODE);
        }
    }

    /**
     * Delete an invitation (only session owner can do that)
     *
     * @param string $session_id Session identifier
     * @param string $user       User identifier
     * @param bool   $local      Remove invitation only from local database
     *
     * @throws Exception
     */
    public function invitation_delete($session_id, $user, $local = false)
    {
        $db     = $this->rc->get_dbh();
        $result = $db->query("DELETE FROM `{$this->invitations_table}`"
            . " WHERE `session_id` = ? AND `user` = ?"
                . " AND EXISTS (SELECT 1 FROM `{$this->sessions_table}` WHERE `id` = ? AND `owner` = ?)",
            $session_id, $user, $session_id, $this->user);

        if (!$db->affected_rows($result)) {
            throw new Exception("Failed to delete an invitation.", file_api_core::ERROR_CODE);
        }
    }

    /**
     * Update an invitation status
     *
     * @param string $session_id Session identifier
     * @param string $user       User identifier (use null for current user)
     * @param string $status     Invitation status (accepted, declined)
     * @param string $comment    Invitation description/comment
     *
     * @throws Exception
     */
    public function invitation_update($session_id, $user, $status, $comment = '')
    {
        if (empty($user)) {
            $user = $this->user;
        }

        if ($status != self::STATUS_ACCEPTED && $status != self::STATUS_DECLINED) {
            throw new Exception("Invalid invitation status.", file_api_core::ERROR_CODE);
        }

        // get session information
        $session = $this->session_info($session_id);

        if (empty($session)) {
            throw new Exception("Document session not found.", file_api_core::ERROR_CODE);
        }

        // check session ownership
        if ($user != $this->user && $session['owner'] != $this->user) {
            throw new Exception("No permission to update an invitation.", file_api_core::ERROR_CODE);
        }

        if ($session['owner'] == $this->user) {
            $status = $status . '-owner';
        }

        $db     = $this->rc->get_dbh();
        $result = $db->query("UPDATE `{$this->invitations_table}`"
            . " SET `status` = ?, `comment` = ?, `changed` = ?"
            . " WHERE `session_id` = ? AND `user` = ?",
            $status, $comment ?: '', $this->db_datetime(), $session_id, $user);

        if (!$db->affected_rows($result)) {
            throw new Exception("Failed to update an invitation status.", file_api_core::ERROR_CODE);
        }
    }

    /**
     * Update a session URI (e.g. on file/folder move)
     *
     * @param string $from      Source file/folder path
     * @param string $to        Destination file/folder path
     * @param bool   $is_folder True if the path is a folder
     */
    public function session_uri_update($from, $to, $is_folder = false)
    {
        $db = $this->rc->get_dbh();

        // Resolve paths
        $from = $this->path2uri($from);
        $to   = $this->path2uri($to);

        if ($is_folder) {
            $set = "`uri` = REPLACE(`uri`, " . $db->quote($from . '/') . ", " . $db->quote($to .'/') . ")";
            $where = "`uri` LIKE " . $db->quote(str_replace('%', '_', $from) . '/%');
        }
        else {
            $set = "`uri` = " . $db->quote($to);
            $where = "`uri` = " . $db->quote($from);
        }

        $db->query("UPDATE `{$this->sessions_table}` SET $set WHERE $where");
    }

    /**
     * Parse session info data
     */
    protected function session_info_parse($record, $path = null, $filter = array())
    {
        $session = array();
        $fields  = array('id', 'uri', 'owner', 'owner_name', 'readonly');

        foreach ($fields as $field) {
            if (isset($record[$field])) {
                $session[$field] = $record[$field];
            }
        }

        if ($path) {
            $session['file'] = $path;
        }

        if (!empty($record['data'])) {
            $data   = json_decode($record['data'], true);
            $fields = array_merge($this->file_meta_items, array('origin'));

            foreach ($fields as $field) {
                if (empty($filter) || in_array($field, $filter)) {
                    $session[$field] = $data[$field];
                }
            }
        }

        // @TODO: is_invited?, last_modified?

        if ($session['owner'] == $this->user) {
            $session['is_owner'] = true;
        }

        if (!empty($filter)) {
            $session = array_intersect_key($session, array_flip($filter));
        }

        return $session;
    }

    /**
     * Get file URI from path
     */
    protected function path2uri($path, &$driver = null)
    {
        list($driver, $path) = $this->api->get_driver($path);

        return $driver->path2uri($path);
    }

    /**
     * Get file path from the URI
     */
    protected function uri2path($uri, $use_fallback = false)
    {
        $backend = $this->api->get_backend();

        try {
            return $backend->uri2path($uri);
        }
        catch (Exception $e) {
                // do nothing
        }

        foreach ($this->api->get_drivers(true) as $driver) {
            try {
                $path  = $driver->uri2path($uri);
                $title = $driver->title();

                if ($title) {
                    $path = $title . file_storage::SEPARATOR . $path;
                }

                return $path;
            }
            catch (Exception $e) {
                // do nothing
            }
        }

        // likely user has no access to the file, but has been invited,
        // extract filename from the URI
        if ($use_fallback && $uri) {
            $path = parse_url($uri, PHP_URL_PATH);
            $path = explode('/', $path);
            $path = $path[count($path) - 1];

            return $path;
        }
    }

    /**
     * Get URI of all user folders (with shared locations)
     */
    protected function all_folder_locations()
    {
        $locations = array();

        foreach (array_merge(array($this->api->get_backend()), $this->api->get_drivers(true)) as $driver) {
            // Performance optimization: We're interested here in shared folders,
            // Kolab is the only driver that currently supports them, ignore others
            if (get_class($driver) != 'kolab_file_storage') {
                continue;
            }

            try {
                foreach ($driver->folder_list() as $folder) {
                    if ($uri = $driver->path2uri($folder)) {
                        $locations[] = $uri;
                    }
                }
            }
            catch (Exception $e) {
               // do nothing
            }
        }

        return $locations;
    }

    /**
     * Get request origin, use Referer header if specified
     */
    protected function get_origin()
    {
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $url = parse_url($_SERVER['HTTP_REFERER']);

            return $url['scheme'] . '://' . $url['host'] . ($url['port'] ?: '');
        }

        return $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];
    }

    /**
     * Return datetime in UTC timezone in SQL format
     */
    protected function db_datetime($dt = null)
    {
        $timezone = new DateTimeZone('UTC');
        $datetime = new DateTime($dt ? '@'.$dt : 'now', $timezone);

        return $datetime->format(self::DB_DATE_FORMAT);
    }
}
