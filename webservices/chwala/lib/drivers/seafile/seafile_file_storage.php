<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2014, Kolab Systems AG                                |
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

class seafile_file_storage implements file_storage
{
    /**
     * @var rcube
     */
    protected $rc;

    /**
     * @var array
     */
    protected $config = array();

    /**
     * @var seafile_api
     */
    protected $api;

    /**
     * List of SeaFile libraries
     *
     * @var array
     */
    protected $libraries;

    /**
     * Instance title (mount point)
     *
     * @var string
     */
    protected $title;


    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->rc = rcube::get_instance();
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
        $this->init(true);

        $token = $this->api->authenticate($username, $password);

        if ($token) {
            $_SESSION[$this->title . 'seafile_user']  = $username;
            $_SESSION[$this->title . 'seafile_token'] = $this->rc->encrypt($token);
            $_SESSION[$this->title . 'seafile_pass']  = $this->rc->encrypt($password);

            return true;
        }

        $this->api = false;

        return false;
    }

    /**
     * Get password and name of authenticated user
     *
     * @return array Authenticated user data
     */
    public function auth_info()
    {
        return array(
            'username' => $_SESSION[$this->title . 'seafile_user'],
            'password' => $this->rc->decrypt($_SESSION[$this->title . 'seafile_pass']),
        );
    }

    /**
     * Initialize SeaFile Web API connection
     */
    protected function init($skip_auth = false)
    {
        if ($this->api !== null) {
            return $this->api !== false;
        }

        // read configuration
        $config = array(
            'host'            => $this->rc->config->get('fileapi_seafile_host', 'localhost'),
            'ssl_verify_peer' => $this->rc->config->get('fileapi_seafile_ssl_verify_peer', true),
            'ssl_verify_host' => $this->rc->config->get('fileapi_seafile_ssl_verify_host', true),
            'cache'           => $this->rc->config->get('fileapi_seafile_cache'),
            'cache_ttl'       => $this->rc->config->get('fileapi_seafile_cache_ttl', '14d'),
            'debug'           => $this->rc->config->get('fileapi_seafile_debug', false),
        );

        $this->config = array_merge($config, $this->config);

        // initialize Web API
        $this->api = new seafile_api($this->config);

        if ($skip_auth) {
            return true;
        }

        // try session token
        if ($_SESSION[$this->title . 'seafile_token']
            && ($token = $this->rc->decrypt($_SESSION[$this->title . 'seafile_token']))
        ) {
            $valid = $this->api->ping($token);
        }

        if (!$valid) {
            // already authenticated in session
            if ($_SESSION[$this->title . 'seafile_user']) {
                $user = $_SESSION[$this->title . 'seafile_user'];
                $pass = $this->rc->decrypt($_SESSION[$this->title . 'seafile_pass']);
            }
            // try user/pass of the main driver
            else {
                $user = $this->config['username'];
                $pass = $this->config['password'];
            }

            if ($user) {
                $valid = $this->authenticate($user, $pass);
            }
        }

        // throw special exception, so we can ask user for the credentials
        if (!$valid && empty($_SESSION[$this->title . 'seafile_user'])) {
            throw new Exception("User credentials not provided", file_storage::ERROR_NOAUTH);
        }
        else if (!$valid && $this->api->is_error() == seafile_api::TOO_MANY_REQUESTS) {
            throw new Exception("SeaFile storage temporarily unavailable (too many requests)", file_storage::ERROR);
        }

        return $valid;
    }

    /**
     * Configures environment
     *
     * @param array  $config Configuration
     * @param string $title  Source identifier
     */
    public function configure($config, $title = null)
    {
        $this->config = array_merge($this->config, $config);
        $this->title  = $title;
    }

    /**
     * Returns current instance title
     *
     * @return string Instance title (mount point)
     */
    public function title()
    {
        return $this->title;
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

        return array(
            file_storage::CAPS_MAX_UPLOAD => $max_filesize,
            file_storage::CAPS_QUOTA      => true,
            file_storage::CAPS_LOCKS      => true,
        );
    }

    /**
     * Save configuration of external driver (mount point)
     *
     * @param array $driver Driver data
     *
     * @throws Exception
     */
    public function driver_create($driver)
    {
        throw new Exception("Not implemented", file_storage::ERROR_UNSUPPORTED);
    }

    /**
     * Delete configuration of external driver (mount point)
     *
     * @param string $title Driver instance name
     *
     * @throws Exception
     */
    public function driver_delete($title)
    {
        throw new Exception("Not implemented", file_storage::ERROR_UNSUPPORTED);
    }

    /**
     * Return list of registered drivers (mount points)
     *
     * @return array List of drivers data
     * @throws Exception
     */
    public function driver_list()
    {
        throw new Exception("Not implemented", file_storage::ERROR_UNSUPPORTED);
    }

    /**
     * Update configuration of external driver (mount point)
     *
     * @param string $title  Driver instance name
     * @param array  $driver Driver data
     *
     * @throws Exception
     */
    public function driver_update($title, $driver)
    {
        throw new Exception("Not implemented", file_storage::ERROR_UNSUPPORTED);
    }

    /**
     * Returns metadata of the driver
     *
     * @return array Driver meta data (image, name, form)
     */
    public function driver_metadata()
    {
        $image_content = file_get_contents(__DIR__ . '/seafile.png');

        $metadata = array(
            'image' => 'data:image/png;base64,' . base64_encode($image_content),
            'name'  => 'SeaFile',
            'ref'   => 'http://seafile.com',
            'description' => 'Storage implementing SeaFile API access',
            'form'  => array(
                'host'     => 'hostname',
                'username' => 'username',
                'password' => 'password',
            ),
        );

        // these are returned when authentication on folders list fails
        if ($this->config['username']) {
            $metadata['form_values'] = array(
                'host'     => $this->config['host'],
                'username' => $this->config['username'],
            );
        }

        return $metadata;
    }

    /**
     * Validate metadata (config) of the driver
     *
     * @param array $metadata Driver metadata
     *
     * @return array Driver meta data to be stored in configuration
     * @throws Exception
     */
    public function driver_validate($metadata)
    {
        if (!is_string($metadata['username']) || !strlen($metadata['username'])) {
            throw new Exception("Missing user name.", file_storage::ERROR);
        }

        if (!is_string($metadata['password']) || !strlen($metadata['password'])) {
            throw new Exception("Missing user password.", file_storage::ERROR);
        }

        if (!is_string($metadata['host']) || !strlen($metadata['host'])) {
            throw new Exception("Missing host name.", file_storage::ERROR);
        }

        $this->config['host'] = $metadata['host'];

        if (!$this->authenticate($metadata['username'], $metadata['password'])) {
            throw new Exception("Unable to authenticate user", file_storage::ERROR_NOAUTH);
        }

        return array(
            'host'     => $metadata['host'],
            'username' => $metadata['username'],
            'password' => $metadata['password'],
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
        list($fn, $repo_id) = $this->find_library($file_name);

        if (empty($repo_id)) {
            throw new Exception("Storage error. Folder not found.", file_storage::ERROR);
        }

        if ($file['path']) {
            $file['data'] = $file['path'];
        }
        else if (is_resource($file['content'])) {
            $file['data'] = $file['content'];
        }
        else {
            $fp = fopen('php://temp', 'wb');
            fwrite($fp, $file['content'], strlen($file['content']));
            $file['data'] = $fp;
            unset($file['content']);
        }

        $created = $this->api->file_upload($repo_id, $fn, $file);

        if ($fp) {
            fclose($fp);
        }

        if (!$created) {
            rcube::raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error saving file to SeaFile server"),
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
        list($fn, $repo_id) = $this->find_library($file_name);

        if (empty($repo_id)) {
            throw new Exception("Storage error. Folder not found.", file_storage::ERROR);
        }

        if ($file['path']) {
            $file['data'] = $file['path'];
        }
        else if (is_resource($file['content'])) {
            $file['data'] = $file['content'];
        }
        else {
            $fp = fopen('php://temp', 'wb');
            fwrite($fp, $file['content'], strlen($file['content']));
            $file['data'] = $fp;
            unset($file['content']);
        }

        $saved = $this->api->file_update($repo_id, $fn, $file);

        if ($fp) {
            fclose($fp);
        }

        if (!$saved) {
            rcube::raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error saving file to SeaFile server"),
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
        list($file_name, $repo_id) = $this->find_library($file_name);

        if ($repo_id && $file_name != '/') {
            $deleted = $this->api->file_delete($repo_id, $file_name);
        }

        if (!$deleted) {
            rcube::raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error deleting object from SeaFile server"),
                true, false);

            throw new Exception("Storage error. Deleting file failed.", file_storage::ERROR);
        }
    }

    /**
     * Return file body.
     *
     * @param string   $file_name Name of a file (with folder path)
     * @param array    $params    Parameters (force-download, force-type, head)
     * @param resource $fp        Print to file pointer instead (send no headers)
     *
     * @throws Exception
     */
    public function file_get($file_name, $params = array(), $fp = null)
    {
        list($fn, $repo_id) = $this->find_library($file_name);

        $file = $this->api->file_info($repo_id, $fn);

        if (empty($file)) {
            throw new Exception("Storage error. File not found.", file_storage::ERROR);
        }

        $file = $this->from_file_object($file);

        // get file location on SeaFile server for download
        if ($file['size'] && empty($params['head'])) {
            $link = $this->api->file_get($repo_id, $fn);
        }

        // write to file pointer, send no headers
        if ($fp) {
            if ($file['size']) {
                $this->save_file_content($link, $fp);
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
            $mimetype    = file_utils::real_mimetype($params['force-type'] ? $params['force-type'] : $file['type']);
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

        // just send redirect to SeaFile server
        if ($file['size'] && empty($params['head'])) {
            $allow_redirects = $this->rc->config->get('fileapi_seafile_allow_redirects');
            // In view-mode we can't redirect to SeaFile server because:
            // - it responds with Content-Disposition: attachment, which causes that
            //   e.g. previewing images is not possible
            // - pdf/odf viewers can't follow redirects for some reason (#4590)
            if ($allow_redirects && !empty($params['force-download'])) {
                header("Location: $link");
            }
            else if ($fp = fopen('php://output', 'wb')) {
                $this->save_file_content($link, $fp);
                fclose($fp);
            }
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
        list($file, $repo_id) = $this->find_library($file_name);

        $file = $this->api->file_info($repo_id, $file);

        if (empty($file)) {
            throw new Exception("Storage error. File not found.", file_storage::ERROR);
        }

        $file = $this->from_file_object($file);

        return array(
            'name'     => $file['name'],
            'size'     => (int) $file['size'],
            'type'     => (string) $file['type'],
            'mtime'    => file_utils::date_format($file['changed'], $this->config['date_format'], $this->config['timezone']),
            'ctime'    => file_utils::date_format($file['created'], $this->config['date_format'], $this->config['timezone']),
            'modified' => $file['changed'] ? $file['changed']->format('U') : 0,
            'created'  => $file['created'] ? $file['created']->format('U') : 0,
        );
    }

    /**
     * List files in a folder.
     *
     * @param string $folder_name Name of a folder with full path
     * @param array  $params      List parameters ('sort', 'reverse', 'search', 'prefix')
     *
     * @return array List of files (file properties array indexed by filename)
     * @throws Exception
     */
    public function file_list($folder_name, $params = array())
    {
        list($folder, $repo_id) = $this->find_library($folder_name);

        // prepare search filter
        if (!empty($params['search'])) {
            foreach ($params['search'] as $idx => $value) {
                if ($idx == 'name') {
                    $params['search'][$idx] = mb_strtoupper($value);
                }
                else if ($idx == 'class') {
                    $params['search'][$idx] = file_utils::class2mimetypes($value);
                }
            }
        }

        // get directory entries
        $entries = $this->api->directory_entries($repo_id, $folder, 'file');
        $result  = array();

        foreach ((array) $entries as $idx => $file) {
            if ($file['type'] != 'file') {
                continue;
            }

            $file = $this->from_file_object($file);

            // search filter
            if (!empty($params['search'])) {
                foreach ($params['search'] as $idx => $value) {
                    if ($idx == 'name') {
                        if (strpos(mb_strtoupper($file['name']), $value) === false) {
                            continue 2;
                        }
                    }
                    else if ($idx == 'class') {
                        foreach ($value as $v) {
                            if (stripos($file['type'], $v) !== false) {
                                continue 2;
                            }
                        }

                        continue 2;
                    }
                }
            }

            $filename = $params['prefix'] . $folder_name . file_storage::SEPARATOR . $file['name'];

            $result[$filename] = array(
                'name'     => $file['name'],
                'size'     => (int) $file['size'],
                'type'     => (string) $file['type'],
                'mtime'    => file_utils::date_format($file['changed'], $this->config['date_format'], $this->config['timezone']),
                'ctime'    => file_utils::date_format($file['created'], $this->config['date_format'], $this->config['timezone']),
                'modified' => $file['changed'] ? $file['changed']->format('U') : 0,
                'created'  => $file['created'] ? $file['created']->format('U') : 0,
            );

            unset($entries[$idx]);
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
        list($src_name, $repo_id)     = $this->find_library($file_name);
        list($dst_name, $dst_repo_id) = $this->find_library($new_name);

        if ($repo_id && $dst_repo_id) {
            $path_src = explode('/', $src_name);
            $path_dst = explode('/', $dst_name);
            $f_src    = array_pop($path_src);
            $f_dst    = array_pop($path_dst);
            $src_dir  = '/' . ltrim(implode('/', $path_src), '/');
            $dst_dir  = '/' . ltrim(implode('/', $path_dst), '/');

            $success = $this->api->file_copy($repo_id, $f_src, $src_dir, $dst_dir, $dst_repo_id);

            // now rename the file if needed
            if ($success && $f_src != $f_dst) {
                $success = $this->api->file_rename($dst_repo_id, rtrim($dst_dir, '/') . '/' . $f_src, $f_dst);
            }
        }

        if (!$success) {
            rcube::raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error copying file on SeaFile server"),
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
        list($src_name, $repo_id)     = $this->find_library($file_name);
        list($dst_name, $dst_repo_id) = $this->find_library($new_name);

        if ($repo_id && $dst_repo_id) {
            $path_src = explode('/', $src_name);
            $path_dst = explode('/', $dst_name);
            $f_src    = array_pop($path_src);
            $f_dst    = array_pop($path_dst);
            $src_dir  = '/' . ltrim(implode('/', $path_src), '/');
            $dst_dir  = '/' . ltrim(implode('/', $path_dst), '/');

            if ($src_dir == $dst_dir && $repo_id == $dst_repo_id) {
                $success = true;
            }
            else {
                $success = $this->api->file_move($repo_id, $src_name, $dst_dir, $dst_repo_id);
            }

            // now rename the file if needed
            if ($success && $f_src != $f_dst) {
                $success = $this->api->file_rename($dst_repo_id, rtrim($dst_dir, '/') . '/' . $f_src, $f_dst);
            }
        }

        if (!$success) {
            rcube::raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error moving file on SeaFile server"),
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
        list($folder, $repo_id) = $this->find_library($folder_name, true);

        if (empty($repo_id)) {
            $success = $this->api->library_create($folder_name);
        }
        else if ($folder != '/') {
            $success = $this->api->directory_create($repo_id, $folder);
        }

        if (!$success) {
            throw new Exception("Storage error. Unable to create folder", file_storage::ERROR);
        }

        // clear the cache
        if (empty($repo_id)) {
            $this->libraries = null;
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
        list($folder, $repo_id) = $this->find_library($folder_name, true);

        if ($repo_id && $folder == '/') {
            $success = $this->api->library_delete($repo_id);
        }
        else if ($repo_id) {
            $success = $this->api->directory_delete($repo_id, $folder);
        }

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
        list($folder, $repo_id, $library) = $this->find_library($folder_name, true);
        list($dest_folder, $dest_repo_id) = $this->find_library($new_name, true);

        // folders rename/move is possible only in the same library and folder
        // @TODO: support folder move between libraries and folders
        // @TODO: support converting library into a folder and vice-versa

        // library rename
        if ($repo_id && !$dest_repo_id && $folder == '/' && strpos($new_name, '/') === false) {
            $success = $this->api->library_rename($repo_id, $new_name, $library['desc']);
        }
        // folder rename
        else if ($folder != '/' && $dest_folder != '/' && $repo_id && $repo_id == $dest_repo_id) {
            $path_src = explode('/', $folder);
            $path_dst = explode('/', $dest_folder);
            $f_src    = array_pop($path_src);
            $f_dst    = array_pop($path_dst);
            $src_dir  = implode('/', $path_src);
            $dst_dir  = implode('/', $path_dst);

            if ($src_dir == $dst_dir) {
                $success = $this->api->directory_rename($repo_id, $folder, $f_dst);
            }
        }

        if (!$success) {
            throw new Exception("Storage error. Unable to rename/move folder", file_storage::ERROR);
        }
    }

    /**
     * Subscribe a folder.
     *
     * @param string $folder_name Name of a folder with full path
     *
     * @throws Exception
     */
    public function folder_subscribe($folder_name)
    {
        throw new Exception("Not implemented", file_storage::ERROR_UNSUPPORTED);
    }

    /**
     * Unsubscribe a folder.
     *
     * @param string $folder_name Name of a folder with full path
     *
     * @throws Exception
     */
    public function folder_unsubscribe($folder_name)
    {
        throw new Exception("Not implemented", file_storage::ERROR_UNSUPPORTED);
    }

    /**
     * Returns list of folders.
     *
     * @param array $params List parameters ('type', 'search')
     *
     * @return array List of folders
     * @throws Exception
     */
    public function folder_list($params = array())
    {
        $writable  = ($params['type'] & file_storage::FILTER_WRITABLE) ? true : false;
        $libraries = $this->libraries();
        $folders   = array();

        if ($this->config['cache']) {
            $cache = $this->rc->get_cache('seafile_' . $this->title,
                $this->config['cache'], $this->config['cache_ttl'], true);

            if ($cache) {
                $cached = $cache->get('folders');
            }
        }

        foreach ($libraries as $library) {
            if ($library['virtual'] || $library['encrypted']) {
                continue;
            }

            if (strpos($library['permission'], 'w') === false) {
                $readonly_prefixes[] = $library['name'];
            }

            $folders[$library['name']] = array(
                'mtime'      => $library['mtime'],
                'permission' => $library['permission'],
            );

            if ($folder_tree = $this->folders_tree($library, '', $library, $cached)) {
                $folders = array_merge($folders, $folder_tree);
            }
        }

        if (empty($folders)) {
            throw new Exception("Storage error. Unable to get folders list.", file_storage::ERROR);
        }

        if ($cache && $cached != $folders) {
            $cache->set('folders', $folders);
        }

        // remove read-only folders when requested
        if ($writable) {
            foreach ($folders as $folder_name => $folder) {
                if (strpos($folder['permission'], 'w') === false) {
                    unset($folders[$folder_name]);
                }
            }
        }

        // In extended format we return array of arrays
        if (!empty($params['extended'])) {
            foreach ($folders as $folder_name => $folder) {
                $item = array('folder' => $folder_name);

                // check if folder is readonly
                if (!$writable && $params['permissions']) {
                    if (strpos($folder['permission'], 'w') === false) {
                        $item['readonly'] = true;
                    }
                }

                $folders[$folder_name] = $item;
            }
        }
        else {
            $folders = array_keys($folders);
        }

        // sort folders
        usort($folders, array('file_utils', 'sort_folder_comparator'));

        return $folders;
    }

    /**
     * Check folder rights.
     *
     * @param string $folder_name Name of a folder with full path
     *
     * @return int Folder rights (sum of file_storage::ACL_*)
     */
    public function folder_rights($folder_name)
    {
        // It is not possible (yet) to assign a specified library/folder
        // to the mount point. So, it is a "virtual" folder.
        if (!strlen($folder_name)) {
            return 0;
        }

        list($folder, $repo_id, $library) = $this->find_library($folder_name);

        // @TODO: we should check directory permission not library
        // However, there's no API for this, we'd need to get a list
        // of directories of a parent folder/library
/*
        if (strpos($folder, '/')) {
            // @TODO
        }
        else {
            $acl = $library['permission'];
        }
*/
        $acl    = $library['permission'];
        $rights = 0;
        $map    = array(
            'r' => file_storage::ACL_READ,
            'w' => file_storage::ACL_WRITE,
        );

        foreach ($map as $key => $value) {
            if (strpos($acl, $key) !== false) {
                $rights |= $value;
            }
        }

        return $rights;
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
     * @param string $path        File/folder path
     * @param bool   $child_locks Enables subtree checks
     *
     * @return array List of locks
     * @throws Exception
     */
    public function lock_list($path, $child_locks = false)
    {
        $this->init_lock_db();

        // convert URI to global resource string
        $uri = $this->path2uri($path);

        // get locks list
        $list = $this->lock_db->lock_list($uri, $child_locks);

        // convert back resource string into URIs
        foreach ($list as $idx => $lock) {
            $list[$idx]['uri'] = $this->uri2path($lock['uri']);
        }

        return $list;
    }

    /**
     * Locks a URI
     *
     * @param string $path File/folder path
     * @param array  $lock Lock data
     *                     - depth: 0/'infinite'
     *                     - scope: 'shared'/'exclusive'
     *                     - owner: string
     *                     - token: string
     *                     - timeout: int
     *
     * @throws Exception
     */
    public function lock($path, $lock)
    {
        $this->init_lock_db();

        // convert URI to global resource string
        $uri = $this->uri2resource($path);

        if (!$this->lock_db->lock($uri, $lock)) {
            throw new Exception("Database error. Unable to create a lock.", file_storage::ERROR);
        }
    }

    /**
     * Removes a lock from a URI
     *
     * @param string $path File/folder path
     * @param array  $lock Lock data
     *
     * @throws Exception
     */
    public function unlock($path, $lock)
    {
        $this->init_lock_db();

        // convert URI to global resource string
        $uri = $this->path2uri($path);

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
        if (!$this->init()) {
            throw new Exception("Storage error. Unable to get SeaFile account info.", file_storage::ERROR);
        }

        $account_info = $this->api->account_info();

        if (empty($account_info)) {
            throw new Exception("Storage error. Unable to get SeaFile account info.", file_storage::ERROR);
        }

        $quota = array(
            // expected values in kB
            'total' => intval($account_info['total'] / 1024),
            'used'  => intval($account_info['usage'] / 1024),
        );

        return $quota;
    }

    /**
     * Recursively builds folders list
     */
    protected function folders_tree($library, $path, $folder, $cached)
    {
        $folders = array();
        $fname  = strlen($path) ? $path . $folder['name'] : '/';
        $root   = $library['name'] . ($fname != '/' ? $fname : '');

        // nothing changed, use cached folders tree of this folder
        if ($cached && is_array($cached[$root]) && $cached[$root]['mtime'] == $folder['mtime']) {
            foreach ($cached as $folder_name => $f) {
                if (strpos($folder_name, $root . '/') === 0) {
                    $folders[$folder_name] = array(
                        'mtime'      => $f['mtime'],
                        'permission' => $f['permission'],
                    );
                }
            }
        }
        // get folder content (files and sub-folders)
        // there's no API method to get only folders
        else if ($content = $this->api->directory_entries($library['id'], $fname, 'dir')) {
            if ($fname != '/') {
                $fname .= '/';
            }

            foreach ($content as $item) {
                if ($item['type'] == 'dir' && strlen($item['name'])) {
                    $folders[$root . '/' . $item['name']] = array(
                        'mtime'      => $item['mtime'],
                        'permission' => $item['permission'],
                    );

                    // get subfolders recursively
                    $folders_tree = $this->folders_tree($library, $fname, $item, $cached);
                    if (!empty($folders_tree)) {
                        $folders = array_merge($folders, $folders_tree);
                    }
                }
            }
        }

        return $folders;
    }

    /**
     * Get list of SeaFile libraries
     */
    protected function libraries()
    {
        // get from memory, @TODO: cache in rcube_cache?
        if ($this->libraries !== null) {
            return $this->libraries;
        }

        if (!$this->init()) {
            throw new Exception("Storage error. Unable to get list of SeaFile libraries.", file_storage::ERROR);
        }

        if ($list = $this->api->library_list()) {
            $this->libraries = $list;
        }
        else {
            $this->libraries = array();
        }

        return $this->libraries;
    }

    /**
     * Find library ID from folder name
     */
    protected function find_library($folder_name, $no_exception = false)
    {
        $libraries = $this->libraries();

        foreach ($libraries as $lib) {
            $path = $lib['name'] . '/';

            if ($folder_name == $lib['name'] || strpos($folder_name, $path) === 0) {
                if (empty($library) || strlen($library['name']) < strlen($lib['name'])) {
                    $library = $lib;
                }
            }
        }

        if (empty($library)) {
            if (!$no_exception) {
                throw new Exception("Storage error. Library not found.", file_storage::ERROR);
            }
        }
        else {
            $folder = substr($folder_name, strlen($library['name']) + 1);
        }

        return array(
            '/' . ($folder ? $folder : ''),
            $library['id'],
            $library
        );
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

        return $files[0];
    }

    /**
     * Simplify internal structure of the file object
     */
    protected function from_file_object($file)
    {
        if ($file['type'] != 'file') {
            return null;
        }

        // file modification time
        if ($file['mtime']) {
            try {
                $file['changed'] = new DateTime('@' . $file['mtime']);
            }
            catch (Exception $e) { }
        }

        // find file mimetype from extension
        $file['type'] = file_utils::ext_to_type($file['name']);

        unset($file['id']);
        unset($file['mtime']);

        return $file;
    }

    /**
     * Save remote file into file pointer
     */
    protected function save_file_content($location, $fp)
    {
        if (!$fp || !$location) {
            return false;
        }

        $config  = array_merge($this->config, array('store_bodies' => true));
        $request = seafile_api::http_request($config);

        if (!$request) {
            return false;
        }

        $observer = new seafile_request_observer();
        $observer->set_fp($fp);

        try {
            $request->setUrl($location);
            $request->attach($observer);

            $response = $request->send();
            $status   = $response->getStatus();

            $response->getBody(); // returns nothing
            $request->detach($observer);

            if ($status != 200) {
                throw new Exception("Unable to save file. Status $status.");
            }
        }
        catch (Exception $e) {
            rcube::raise_error($e, true, false);
            return false;
        }

        return true;
    }

    /**
     * Convert file/folder path into a global URI.
     *
     * @param string $path File/folder path
     *
     * @return string URI
     * @throws Exception
     */
    public function path2uri($path)
    {
        list($file, $repo_id, $library) = $this->find_library($path);

        $host = preg_replace('|https?://|i', '', $this->config['host']);

        return 'seafile://' . rawurlencode($library['owner']) . '@' . $host . '/' . file_utils::encode_path($path);
    }

    /**
     * Convert global URI into file/folder path.
     *
     * @param string $uri URI
     *
     * @return string File/folder path
     * @throws Exception
     */
    public function uri2path($uri)
    {
        if (!preg_match('|^seafile://([^@]+)@([^/]+)/(.*)$|', $uri, $matches)) {
            throw new Exception("Internal storage error. Unexpected data format.", file_storage::ERROR);
        }

        $user   = rawurldecode($matches[1]);
        $host   = $matches[2];
        $path   = file_utils::decode_path($matches[3]);
        $c_host = preg_replace('|https?://|i', '', $this->config['host']);

        list($file, $repo_id, $library) = $this->find_library($path, true);

        if (empty($library) || $host != $c_host || $user != $library['owner']) {
            throw new Exception("Internal storage error. Unresolvable URI.", file_storage::ERROR);
        }

        return $path;
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
