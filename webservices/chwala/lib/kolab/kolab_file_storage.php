<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2013, Kolab Systems AG                                |
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

class kolab_file_storage implements file_storage
{
    /**
     * @var rcube
     */
    protected $rc;

    /**
     * @var array
     */
    protected $folders;

    /**
     * @var array
     */
    protected $config;


    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->rc = rcube::get_instance();

        // Get list of plugins
        // WARNING: We can use only plugins that are prepared for this
        //          e.g. are not using output or rcmail objects or
        //          doesn't throw errors when using them
        $plugins  = (array)$this->rc->config->get('fileapi_plugins', array('kolab_auth'));
        $required = array('libkolab');

        // Kolab WebDAV server supports plugins, no need to overwrite object
        if (!is_a($this->rc->plugins, 'rcube_plugin_api')) {
            // Initialize/load plugins
            $this->rc->plugins = kolab_file_plugin_api::get_instance();
            $this->rc->plugins->init($this, '');
        }

        $this->rc->plugins->load_plugins($plugins, $required);

        $this->init();
    }

    /**
     * Authenticates a user
     *
     * @param string $username User name
     * @param string $password User password
     *
     * @param bool True on success, False on failure
     */
    public function authenticate($username, $password)
    {
        $auth = $this->rc->plugins->exec_hook('authenticate', array(
            'host'  => $this->select_host($username),
            'user'  => $username,
            'pass'  => $password,
            'valid' => true,
        ));

        // Authenticate - get Roundcube user ID
        if ($auth['valid'] && !$auth['abort']
            && ($this->login($auth['user'], $auth['pass'], $auth['host']))) {
            return true;
        }

        $this->rc->plugins->exec_hook('login_failed', array(
            'host' => $auth['host'],
            'user' => $auth['user'],
        ));
    }

    /**
     * Storage host selection
     */
    private function select_host($username)
    {
        // Get IMAP host
        $host = $this->rc->config->get('default_host');

        if (is_array($host)) {
            list($user, $domain) = explode('@', $username);

            // try to select host by mail domain
            if (!empty($domain)) {
                foreach ($host as $storage_host => $mail_domains) {
                    if (is_array($mail_domains) && in_array_nocase($domain, $mail_domains)) {
                        $host = $storage_host;
                        break;
                    }
                    else if (stripos($storage_host, $domain) !== false || stripos(strval($mail_domains), $domain) !== false) {
                        $host = is_numeric($storage_host) ? $mail_domains : $storage_host;
                        break;
                    }
                }
            }

            // take the first entry if $host is not found
            if (is_array($host)) {
                list($key, $val) = each($default_host);
                $host = is_numeric($key) ? $val : $key;
            }
        }

        return rcube_utils::parse_host($host);
    }

    /**
     * Authenticates a user in IMAP
     */
    private function login($username, $password, $host)
    {
        if (empty($username)) {
            return false;
        }

        $login_lc     = $this->rc->config->get('login_lc');
        $default_port = $this->rc->config->get('default_port', 143);

        // parse $host
        $a_host = parse_url($host);
        if ($a_host['host']) {
            $host = $a_host['host'];
            $ssl = (isset($a_host['scheme']) && in_array($a_host['scheme'], array('ssl','imaps','tls'))) ? $a_host['scheme'] : null;
            if (!empty($a_host['port'])) {
                $port = $a_host['port'];
            }
            else if ($ssl && $ssl != 'tls' && (!$default_port || $default_port == 143)) {
                $port = 993;
            }
        }

        if (!$port) {
            $port = $default_port;
        }

        // Convert username to lowercase. If storage backend
        // is case-insensitive we need to store always the same username
        if ($login_lc) {
            if ($login_lc == 2 || $login_lc === true) {
                $username = mb_strtolower($username);
            }
            else if (strpos($username, '@')) {
                // lowercase domain name
                list($local, $domain) = explode('@', $username);
                $username = $local . '@' . mb_strtolower($domain);
            }
        }

        // Here we need IDNA ASCII
        // Only rcube_contacts class is using domain names in Unicode
        $host     = rcube_utils::idn_to_ascii($host);
        $username = rcube_utils::idn_to_ascii($username);

        // user already registered?
        if ($user = rcube_user::query($username, $host)) {
            $username = $user->data['username'];
        }

        // authenticate user in IMAP
        $storage = $this->rc->get_storage();
        if (!$storage->connect($host, $username, $password, $port, $ssl)) {
            return false;
        }

        // No user in database, but IMAP auth works
        if (!is_object($user)) {
            if ($this->rc->config->get('auto_create_user')) {
                // create a new user record
                $user = rcube_user::create($username, $host);

                if (!$user) {
                    rcube::raise_error(array(
                        'code' => 620, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__,
                        'message' => "Failed to create a user record",
                    ), true, false);
                    return false;
                }
            }
            else {
                rcube::raise_error(array(
                    'code' => 620, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Access denied for new user $username. 'auto_create_user' is disabled",
                ), true, false);
                return false;
            }
        }
        // set session vars
        $_SESSION['user_id']      = $user->ID;
        $_SESSION['username']     = $user->data['username'];
        $_SESSION['storage_host'] = $host;
        $_SESSION['storage_port'] = $port;
        $_SESSION['storage_ssl']  = $ssl;
        $_SESSION['password']     = $this->rc->encrypt($password);

        $this->init($user);

        // force reloading of mailboxes list/data
        $storage->clear_cache('mailboxes', true);

        return true;
    }

    protected function init($user = null)
    {
        if ($_SESSION['user_id'] || $user) {
            // overwrite config with user preferences
            $this->rc->user = $user ? $user : new rcube_user($_SESSION['user_id']);
            $this->rc->config->set_user_prefs((array)$this->rc->user->get_prefs());

            $storage = $this->rc->get_storage();
            $storage->set_charset($this->rc->config->get('default_charset', RCUBE_CHARSET));

            setlocale(LC_ALL, 'en_US.utf8', 'en_US.UTF-8');
        }
    }

    /**
     * Configures environment
     *
     * @param array $config COnfiguration
     */
    public function configure($config)
    {
        $this->config = $config;
    }

    /**
     * Storage driver capabilities
     *
     * @return array List of capabilities
     */
    public function capabilities()
    {
        // find max filesize value
        $max_filesize = parse_bytes(ini_get('upload_max_filesize'));
        $max_postsize = parse_bytes(ini_get('post_max_size'));
        if ($max_postsize && $max_postsize < $max_filesize) {
            $max_filesize = $max_postsize;
        }

        $storage = $this->rc->get_storage();
        $quota   = $storage->get_capability('QUOTA');

        return array(
            file_storage::CAPS_MAX_UPLOAD => $max_filesize,
            file_storage::CAPS_QUOTA      => $quota,
            file_storage::CAPS_LOCKS      => true,
        );
    }

    /**
     * Create a file.
     *
     * @param string $file_name Name of a file (with folder path)
     * @param array  $file      File data (path, type)
     *
     * @throws Exception
     */
    public function file_create($file_name, $file)
    {
        $exists = $this->get_file_object($file_name, $folder);
        if (!empty($exists)) {
            throw new Exception("Storage error. File exists.", file_storage::ERROR);
        }

        $object = $this->to_file_object(array(
            'name'    => $file_name,
            'type'    => $file['type'],
            'path'    => $file['path'],
            'content' => $file['content'],
        ));

        // save the file object in IMAP
        $saved = $folder->save($object, 'file');
        if (!$saved) {
            rcube::raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error saving object to Kolab server"),
                true, false);

            throw new Exception("Storage error. Saving file failed.", file_storage::ERROR);
        }
    }

    /**
     * Update a file.
     *
     * @param string $file_name Name of a file (with folder path)
     * @param array  $file      File data (path, type)
     *
     * @throws Exception
     */
    public function file_update($file_name, $file)
    {
        $file_object = $this->get_file_object($file_name, $folder);
        if (empty($file_object)) {
            throw new Exception("Storage error. File not found.", file_storage::ERROR);
        }

        $key = key($file_object['_attachments']);
        $file_object['_attachments'] = array(
            0 => array(
                'name'     => $file_name,
                'path'     => $file['path'],
                'content'  => $file['content'],
                'mimetype' => $file['type'],
            ),
            $key => false,
        );

        // save the file object in IMAP
        $saved = $folder->save($file_object, 'file', $file_object['_msguid']);
        if (!$saved) {
            rcube::raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error saving object to Kolab server"),
                true, false);

            throw new Exception("Storage error. Saving file failed.", file_storage::ERROR);
        }
    }

    /**
     * Delete a file.
     *
     * @param string $file_name Name of a file (with folder path)
     *
     * @throws Exception
     */
    public function file_delete($file_name)
    {
        $file = $this->get_file_object($file_name, $folder);
        if (empty($file)) {
            throw new Exception("Storage error. File not found.", file_storage::ERROR);
        }

        $deleted = $folder->delete($file);
        if (!$deleted) {
            rcube::raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error deleting object from Kolab server"),
                true, false);

            throw new Exception("Storage error. Deleting file failed.", file_storage::ERROR);
        }
    }

    /**
     * Return file body.
     *
     * @param string   $file_name Name of a file (with folder path)
     * @param array    $params    Parameters (force-download)
     * @param resource $fp        Print to file pointer instead (send no headers)
     *
     * @throws Exception
     */
    public function file_get($file_name, $params = array(), $fp = null)
    {
        $file = $this->get_file_object($file_name, $folder);
        if (empty($file)) {
            throw new Exception("Storage error. File not found.", file_storage::ERROR);
        }

        $file = $this->from_file_object($file);

        // write to file pointer, send no headers
        if ($fp) {
            if ($file['size']) {
                $folder->get_attachment($file['_msguid'], $file['fileid'], $file['_mailbox'], false, $fp);
            }
            return;
        }

        if (!empty($params['force-download'])) {
            $disposition = 'attachment';
            header("Content-Type: application/octet-stream");
// @TODO
//            if ($browser->ie)
//                header("Content-Type: application/force-download");
        }
        else {
            $mimetype = file_utils::real_mimetype($params['force-type'] ? $params['force-type'] : $file['type']);
            $disposition = 'inline';

            header("Content-Transfer-Encoding: binary");
            header("Content-Type: $mimetype");
        }

        $filename = addcslashes($file['name'], '"');

        // Workaround for nasty IE bug (#1488844)
        // If Content-Disposition header contains string "attachment" e.g. in filename
        // IE handles data as attachment not inline
/*
@TODO
        if ($disposition == 'inline' && $browser->ie && $browser->ver < 9) {
            $filename = str_ireplace('attachment', 'attach', $filename);
        }
*/
        header("Content-Length: " . $file['size']);
        header("Content-Disposition: $disposition; filename=\"$filename\"");

        if ($file['size']) {
            $folder->get_attachment($file['_msguid'], $file['fileid'], $file['_mailbox'], true);
        }
    }

    /**
     * Returns file metadata.
     *
     * @param string $file_name Name of a file (with folder path)
     *
     * @throws Exception
     */
    public function file_info($file_name)
    {
        $file = $this->get_file_object($file_name, $folder);
        if (empty($file)) {
            throw new Exception("Storage error. File not found.", file_storage::ERROR);
        }

        $file = $this->from_file_object($file);

        return array(
            'name'     => $file['name'],
            'size'     => (int) $file['size'],
            'type'     => (string) $file['type'],
            'mtime'    => $file['changed'] ? $file['changed']->format($this->config['date_format']) : '',
            'ctime'    => $file['created'] ? $file['created']->format($this->config['date_format']) : '',
            'modified' => $file['changed'] ? $file['changed']->format('U') : 0,
            'created'  => $file['created'] ? $file['created']->format('U') : 0,
        );
    }

    /**
     * List files in a folder.
     *
     * @param string $folder_name Name of a folder with full path
     * @param array  $params      List parameters ('sort', 'reverse', 'search')
     *
     * @return array List of files (file properties array indexed by filename)
     * @throws Exception
     */
    public function file_list($folder_name, $params = array())
    {
        $filter = array(array('type', '=', 'file'));

        if (!empty($params['search'])) {
            foreach ($params['search'] as $idx => $value) {
                switch ($idx) {
                case 'name':
                    $filter[] = array('filename', '~', $value);
                    break;
                case 'class':
                    foreach (file_utils::class2mimetypes($value) as $tag) {
                        $for[] = array('tags', '~', ' ' . $tag);
                    }
                    $filter[] = array($for, 'OR');
                    break;
                }
            }
        }

        // get files list
        $folder = $this->get_folder_object($folder_name);
        $files  = $folder->select($filter);
        $result = array();

        // convert to kolab_storage files list data format
        foreach ($files as $idx => $file) {
            $file = $this->from_file_object($file);

            if (!isset($file['name'])) {
                continue;
            }

            $filename = $folder_name . file_storage::SEPARATOR . $file['name'];

            $result[$filename] = array(
                'name'     => $file['name'],
                'size'     => (int) $file['size'],
                'type'     => (string) $file['type'],
                'mtime'    => $file['changed'] ? $file['changed']->format($this->config['date_format']) : '',
                'ctime'    => $file['created'] ? $file['created']->format($this->config['date_format']) : '',
                'modified' => $file['changed'] ? $file['changed']->format('U') : 0,
                'created'  => $file['created'] ? $file['created']->format('U') : 0,
            );
            unset($files[$idx]);
        }

        // @TODO: pagination, search (by filename, mimetype)

        // Sorting
        $sort  = !empty($params['sort']) ? $params['sort'] : 'name';
        $index = array();

        if ($sort == 'mtime') {
            $sort = 'modified';
        }

        if (in_array($sort, array('name', 'size', 'modified'))) {
            foreach ($result as $key => $val) {
                $index[$key] = $val[$sort];
            }
            array_multisort($index, SORT_ASC, SORT_NUMERIC, $result);
        }

        if ($params['reverse']) {
            $result = array_reverse($result, true);
        }

        return $result;
    }

    /**
     * Copy a file.
     *
     * @param string $file_name Name of a file (with folder path)
     * @param string $new_name  New name of a file (with folder path)
     *
     * @throws Exception
     */
    public function file_copy($file_name, $new_name)
    {
        $file = $this->get_file_object($file_name, $folder);
        if (empty($file)) {
            throw new Exception("Storage error. File not found.", file_storage::ERROR);
        }

        $new = $this->get_file_object($new_name, $new_folder);
        if (!empty($new)) {
            throw new Exception("Storage error. File exists.", file_storage::ERROR_FILE_EXISTS);
        }

        $file = $this->from_file_object($file);

        // Save to temp file
        // @TODO: use IMAP CATENATE extension
        $temp_dir  = unslashify($this->rc->config->get('temp_dir'));
        $file_path = tempnam($temp_dir, 'rcmAttmnt');
        $fh        = fopen($file_path, 'w');

        if (!$fh) {
            throw new Exception("Storage error. File copying failed.", file_storage::ERROR);
        }

        if ($file['size']) {
            $folder->get_attachment($file['uid'], $file['fileid'], null, false, $fh, true);
        }
        fclose($fh);

        if (!file_exists($file_path)) {
            throw new Exception("Storage error. File copying failed.", file_storage::ERROR);
        }

        // Update object
        $file['_attachments'] = array(
            0 => array(
                'name'     => $file['name'],
                'path'     => $file_path,
                'mimetype' => $file['type'],
                'size'     => $file['size'],
        ));

        $fields = array('created', 'changed', '_attachments', 'notes', 'sensitivity', 'categories', 'x-custom');
        $file   = array_intersect_key($file, array_combine($fields, $fields));

        $saved = $new_folder->save($file, 'file');

        @unlink($file_path);

        if (!$saved) {
            rcube::raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error updating object on Kolab server"),
                true, false);

            throw new Exception("Storage error. File copying failed.", file_storage::ERROR);
        }
    }

    /**
     * Move (or rename) a file.
     *
     * @param string $file_name Name of a file (with folder path)
     * @param string $new_name  New name of a file (with folder path)
     *
     * @throws Exception
     */
    public function file_move($file_name, $new_name)
    {
        $file = $this->get_file_object($file_name, $folder);
        if (empty($file)) {
            throw new Exception("Storage error. File not found.", file_storage::ERROR);
        }

        $new = $this->get_file_object($new_name, $new_folder);
        if (!empty($new)) {
            throw new Exception("Storage error. File exists.", file_storage::ERROR_FILE_EXISTS);
        }

        // Move the file
        if ($folder->name != $new_folder->name) {
            $saved = $folder->move($file['uid'], $new_folder->name);
            if (!$saved) {
                rcube::raise_error(array(
                    'code' => 600, 'type' => 'php',
                    'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Error moving object on Kolab server"),
                    true, false);

                throw new Exception("Storage error. File move failed.", file_storage::ERROR);
            }

            $folder = $new_folder;
        }

        if ($file_name === $new_name) {
            return;
        }

        // Update object (changing the name)
        $cid = key($file['_attachments']);
        $file['_attachments'][$cid]['name'] = $new_name;
        $file['_attachments'][0] = $file['_attachments'][$cid];
        $file['_attachments'][$cid] = false;

        $saved = $folder->save($file, 'file');
        if (!$saved) {
            rcube::raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error updating object on Kolab server"),
                true, false);

            throw new Exception("Storage error. File rename failed.", file_storage::ERROR);
        }
    }

    /**
     * Create a folder.
     *
     * @param string $folder_name Name of a folder with full path
     *
     * @throws Exception on error
     */
    public function folder_create($folder_name)
    {
        $folder_name = rcube_charset::convert($folder_name, RCUBE_CHARSET, 'UTF7-IMAP');
        $success     = kolab_storage::folder_create($folder_name, 'file');

        if (!$success) {
            throw new Exception("Storage error. Unable to create folder", file_storage::ERROR);
        }
    }

    /**
     * Delete a folder.
     *
     * @param string $folder_name Name of a folder with full path
     *
     * @throws Exception on error
     */
    public function folder_delete($folder_name)
    {
        $folder_name = rcube_charset::convert($folder_name, RCUBE_CHARSET, 'UTF7-IMAP');
        $success     = kolab_storage::folder_delete($folder_name);

        if (!$success) {
            throw new Exception("Storage error. Unable to delete folder.", file_storage::ERROR);
        }
    }

    /**
     * Move/Rename a folder.
     *
     * @param string $folder_name Name of a folder with full path
     * @param string $new_name    New name of a folder with full path
     *
     * @throws Exception on error
     */
    public function folder_move($folder_name, $new_name)
    {
        $folder_name = rcube_charset::convert($folder_name, RCUBE_CHARSET, 'UTF7-IMAP');
        $new_name    = rcube_charset::convert($new_name, RCUBE_CHARSET, 'UTF7-IMAP');
        $success     = kolab_storage::folder_rename($folder_name, $new_name);

        if (!$success) {
            throw new Exception("Storage error. Unable to rename folder", file_storage::ERROR);
        }
    }

    /**
     * Returns list of folders.
     *
     * @return array List of folders
     * @throws Exception
     */
    public function folder_list()
    {
        $folders = kolab_storage::list_folders('', '*', 'file', false);

        if (!is_array($folders)) {
            throw new Exception("Storage error. Unable to get folders list.", file_storage::ERROR);
        }

        foreach ($folders as $folder) {
            $folder = rcube_charset::convert($folder_name, 'UTF7-IMAP', RCUBE_CHARSET);
        }

        // create 'Files' folder in case there's no folder of type 'file'
        if (empty($folders)) {
            if (kolab_storage::folder_create('Files', 'file')) {
                $folders[] = 'Files';
            }
        }

        return $folders;
    }

    /**
     * Returns a list of locks
     *
     * This method should return all the locks for a particular URI, including
     * locks that might be set on a parent URI.
     *
     * If child_locks is set to true, this method should also look for
     * any locks in the subtree of the URI for locks.
     *
     * @param string $uri         URI
     * @param bool   $child_locks Enables subtree checks
     *
     * @return array List of locks
     * @throws Exception
     */
    public function lock_list($uri, $child_locks = false)
    {
        $this->init_lock_db();

        // convert URI to global resource string
        $uri = $this->uri2resource($uri);

        // get locks list
        $list = $this->lock_db->lock_list($uri, $child_locks);

        // convert back resource string into URIs
        foreach ($list as $idx => $lock) {
            $list[$idx]['uri'] = $this->resource2uri($lock['uri']);
        }

        return $list;
    }

    /**
     * Locks a URI
     *
     * @param string $uri  URI
     * @param array  $lock Lock data
     *                     - depth: 0/'infinite'
     *                     - scope: 'shared'/'exclusive'
     *                     - owner: string
     *                     - token: string
     *                     - timeout: int
     *
     * @throws Exception
     */
    public function lock($uri, $lock)
    {
        $this->init_lock_db();

        // convert URI to global resource string
        $uri = $this->uri2resource($uri);

        if (!$this->lock_db->lock($uri, $lock)) {
            throw new Exception("Database error. Unable to create a lock.", file_storage::ERROR);
        }
    }

    /**
     * Removes a lock from a URI
     *
     * @param string $path URI
     * @param array  $lock Lock data
     *
     * @throws Exception
     */
    public function unlock($uri, $lock)
    {
        $this->init_lock_db();

        // convert URI to global resource string
        $uri = $this->uri2resource($uri);

        if (!$this->lock_db->unlock($uri, $lock)) {
            throw new Exception("Database error. Unable to remove a lock.", file_storage::ERROR);
        }
    }

    /**
     * Return disk quota information for specified folder.
     *
     * @param string $folder_name Name of a folder with full path
     *
     * @return array Quota
     * @throws Exception
     */
    public function quota($folder)
    {
        $storage = $this->rc->get_storage();
        $quota   = $storage->get_quota();
        $quota   = $this->rc->plugins->exec_hook('quota', $quota);

        unset($quota['abort']);

        return $quota;
    }

    /**
     * Get file object.
     *
     * @param string               $file_name Name of a file (with folder path)
     * @param kolab_storage_folder $folder    Reference to folder object
     *
     * @return array File data
     * @throws Exception
     */
    protected function get_file_object(&$file_name, &$folder = null)
    {
        // extract file path and file name
        $path        = explode(file_storage::SEPARATOR, $file_name);
        $file_name   = array_pop($path);
        $folder_name = implode(file_storage::SEPARATOR, $path);

        if ($folder_name === '') {
            throw new Exception("Missing folder name", file_storage::ERROR);
        }

        // get folder object
        $folder = $this->get_folder_object($folder_name);
        $files  = $folder->select(array(
            array('type', '=', 'file'),
            array('filename', '=', $file_name)
        ));

        return array_shift($files);
    }

    /**
     * Get folder object.
     *
     * @param string $folder_name Name of a folder with full path
     *
     * @return kolab_storage_folder Folder object
     * @throws Exception
     */
    protected function get_folder_object($folder_name)
    {
        if ($folder_name === null || $folder_name === '') {
            throw new Exception("Missing folder name", file_storage::ERROR);
        }

        if (empty($this->folders[$folder_name])) {
            $storage     = $this->rc->get_storage();
            $separator   = $storage->get_hierarchy_delimiter();
            $folder_name = str_replace(file_storage::SEPARATOR, $separator, $folder_name);
            $imap_name   = rcube_charset::convert($folder_name, RCUBE_CHARSET, 'UTF7-IMAP');
            $folder      = kolab_storage::get_folder($imap_name);

            if (!$folder) {
                throw new Exception("Storage error. Folder not found.", file_storage::ERROR);
            }

            $this->folders[$folder_name] = $folder;
        }

        return $this->folders[$folder_name];
    }

    /**
     * Simplify internal structure of the file object
     */
    protected function from_file_object($file)
    {
        if (empty($file['_attachments'])) {
            return $file;
        }

        $attachment = array_shift($file['_attachments']);

        $file['name']   = $attachment['name'];
        $file['size']   = $attachment['size'];
        $file['type']   = $attachment['mimetype'];
        $file['fileid'] = $attachment['id'];

        unset($file['_attachments']);

        return $file;
    }

    /**
     * Convert to kolab_format internal structure of the file object
     */
    protected function to_file_object($file)
    {
        // @TODO if path is empty and fileid exists it is an update
        // get attachment body and save it in path

        $file['_attachments'] = array(
            0 => array(
                'name'     => $file['name'],
                'path'     => $file['path'],
                'content'  => $file['content'],
                'mimetype' => $file['type'],
                'size'     => $file['size'],
        ));

        unset($file['name']);
        unset($file['size']);
        unset($file['type']);
        unset($file['path']);
        unset($file['fileid']);

        return $file;
    }

    protected function uri2resource($uri)
    {
        $storage   = $this->rc->get_storage();
        $namespace = $storage->get_namespace();
        $separator = $storage->get_hierarchy_delimiter();
        $uri       = str_replace(file_storage::SEPARATOR, $separator, $uri);
        $owner     = $this->rc->get_user_name();

        // find the owner and remove namespace prefix
        foreach ($namespace as $type => $ns) {
            foreach ($ns as $root) {
                if (is_array($root) && $root[0] && strpos($uri, $root[0]) === 0) {
                    $uri = substr($uri, strlen($root[0]));

                    switch ($type) {
                    case 'shared':
                        // in theory there can be more than one shared root
                        // we add it to dummy user name, so we can revert conversion
                        $owner = "shared({$root[0]})";
                        break;

                    case 'other':
                        list($user, $uri) = explode($separator, $uri, 2);

                        if (strpos($user, '@') === false) {
                            $domain = strstr($owner, '@');
                            if (!empty($domain)) {
                                $user .= $domain;
                            }
                        }

                        $owner = $user;
                        break;
                    }

                    break 2;
                }
            }
        }

        // convert to imap charset (to be safe to store in DB)
        $uri = rcube_charset::convert($uri, RCUBE_CHARSET, 'UTF7-IMAP');

        return 'imap://' . urlencode($owner) . '@' . $storage->options['host'] . '/' . $uri;
    }

    protected function resource2uri($resource)
    {
        if (!preg_match('|^imap://([^@]+)@([^/]+)/(.*)$|', $resource, $matches)) {
            throw new Exception("Internal storage error. Unexpected data format.", file_storage::ERROR);
        }

        $storage   = $this->rc->get_storage();
        $separator = $storage->get_hierarchy_delimiter();
        $owner     = $this->rc->get_user_name();

        $user = urldecode($matches[1]);
        $uri  = $matches[3];

        // convert from imap charset (to be safe to store in DB)
        $uri = rcube_charset::convert($uri, 'UTF7-IMAP', RCUBE_CHARSET);

        // personal namespace
        if ($user == $owner) {
            // do nothing
            // Note: that might not work if personal namespace uses e.g. INBOX/ prefix.
        }
        // shared namespace
        else if (preg_match('/^shared\((.*)\)$/', $user, $matches)) {
            $uri = $matches[1] . $uri;
        }
        // other users namespace
        else {
            $namespace = $storage->get_namespace('other');

            list($local, $domain) = explode('@', $user);

            // here we assume there's only one other users namespace root
            $uri = $namespace[0][0] . $local . $separator . $uri;
        }

        $uri = str_replace($separator, file_storage::SEPARATOR, $uri);

        return $uri;
    }

    /**
     * Initializes file_locks object
     */
    protected function init_lock_db()
    {
        if (!$this->lock_db) {
            $this->lock_db = new file_locks;
        }
    }
}
