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

/**
 * Class implementing access via SeaFile Web API v2
 */
class seafile_api
{
    const STATUS_OK                  = 200;
    const CREATED                    = 201;
    const ACCEPTED                   = 202;
    const MOVED_PERMANENTLY          = 301;
    const BAD_REQUEST                = 400;
    const FORBIDDEN                  = 403;
    const NOT_FOUND                  = 404;
    const CONFLICT                   = 409;
    const TOO_MANY_REQUESTS          = 429;
    const REPO_PASSWD_REQUIRED       = 440;
    const REPO_PASSWD_MAGIC_REQUIRED = 441;
    const INTERNAL_SERVER_ERROR      = 500;
    const OPERATION_FAILED           = 520;

    const CONNECTION_ERROR           = 550;

    /**
     * Specifies how long max. we'll wait and renew throttled request (in seconds)
     */
    const WAIT_LIMIT = 30;


    /**
     * Configuration
     *
     * @var array
     */
    protected $config = array();

    /**
     * HTTP request handle
     *
     * @var HTTP_Request
     */
    protected $request;

    /**
     * Web API URI prefix
     *
     * @var string
     */
    protected $url;

    /**
     * Session token
     *
     * @var string
     */
    protected $token;

    /**
     * API URL prefix (schema and host[:port])
     *
     * @var string
     */
    protected $url_prefix;


    public function __construct($config = array())
    {
        $this->config = $config;

        // set Web API URI
        $this->url = rtrim(trim($config['host']), '/') ?: 'localhost';

        if (!preg_match('|^https?://|i', $this->url)) {
            $this->url = 'https://' . $this->url;
        }

        if (!preg_match('|/api2$|', $this->url)) {
            $this->url .= '/api2/';
        }

        $this->url_prefix = preg_replace('|^(https?://[^/]+).*$|i', '\\1', $this->url);
    }

    /**
     *
     * @param array Configuration for this Request instance, that will be merged
     *              with default configuration
     *
     * @return HTTP_Request2 Request object
     */
    public static function http_request($config = array())
    {
        // load HTTP_Request2
        require_once 'HTTP/Request2.php';

        // remove unknown config, otherwise HTTP_Request will throw an error
        $config = array_intersect_key($config, array_flip(array(
            'connect_timeout', 'timeout', 'use_brackets', 'protocol_version',
            'buffer_size', 'store_body', 'follow_redirects', 'max_redirects',
            'strict_redirects', 'ssl_verify_peer', 'ssl_verify_host',
            'ssl_cafile', 'ssl_capath', 'ssl_local_cert', 'ssl_passphrase'
        )));

        // force CURL adapter, this allows to handle correctly
        // compressed responses with simple SplObserver registered
        $config['adapter'] = 'HTTP_Request2_Adapter_Curl';

        try {
            $request = new HTTP_Request2();
            $request->setConfig($config);
        }
        catch (Exception $e) {
            rcube::raise_error($e, true, false);
            return;
        }

        return $request;
    }

    /**
     * Send HTTP request
     *
     * @param string $method Request method ('OPTIONS','GET','HEAD','POST','PUT','DELETE','TRACE','CONNECT')
     * @param string $url    Request API URL
     * @param array  $get    GET parameters
     * @param array  $post   POST parameters
     * @param array  $upload Uploaded files data
     *
     * @return string|array Server response
     */
    protected function request($method, $url, $get = null, $post = null, $upload = null)
    {
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = $this->url . $url;
            // Note: It didn't work for me without the last backslash
            $url = rtrim($url, '/') . '/';
        }
        // If Seafile is behind a proxy and different port, it will return
        // wrong URL for file uploads. We force the original URL prefix here
        else if (stripos($url, $this->url_prefix) !== 0) {
            $url = $this->url_prefix . preg_replace('|^(https?://[^/]+)|i', '', $url);
        }

        if (!$this->request) {
            $this->config['store_body']       = true;
            // some methods respond with 301 redirect, we'll not follow them
            // also because of https://github.com/haiwen/seahub/issues/288
            $this->config['follow_redirects'] = false;

            $this->request = self::http_request($this->config);

            if (!$this->request) {
                $this->status = self::CONNECTION_ERROR;
                return;
            }
        }

        // cleanup
        try {
            $this->request->setBody('');
            $this->request->setUrl($url);
        }
        catch (Exception $e) {
            rcube::raise_error($e, true, false);
            $this->status = self::CONNECTION_ERROR;
            return;
        }

        if ($this->config['debug']) {
            $log_line = "SeaFile $method: $url";
            $json_opt = PHP_VERSION_ID >= 50400 ? JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE : 0;

            if (!empty($get)) {
                $log_line .= ", GET: " . @json_encode($get, $json_opt);
            }

            if (!empty($post)) {
                $log_line .= ", POST: " . preg_replace('/("password":)[^\},]+/', '\\1"*"', @json_encode($post, $json_opt));
            }

            if (!empty($upload)) {
                $log_line .= ", Files: " . @json_encode(array_keys($upload), $json_opt);
            }

            rcube::write_log('console', $log_line);
        }

        $this->request->setMethod($method ?: HTTP_Request2::METHOD_GET);

        if (!empty($get)) {
            $_url = $this->request->getUrl();
            $_url->setQueryVariables($get);
            $this->request->setUrl($_url);
        }

        if (!empty($post)) {
            $this->request->addPostParameter($post);
        }

        if (!empty($upload)) {
            foreach ($upload as $field_name => $file) {
                $this->request->addUpload($field_name, $file['data'], $file['name'], $file['type']);
            }
        }

        if ($this->token) {
            $this->request->setHeader('Authorization', "Token " . $this->token);
        }

        // some HTTP server configurations require this header
        $this->request->setHeader('Accept', "application/json,text/javascript,*/*");

        // proxy User-Agent string
        $this->request->setHeader('User-Agent', $_SERVER['HTTP_USER_AGENT']);

        // send request to the SeaFile API server
        try {
            $response     = $this->request->send();
            $this->status = $response->getStatus();
            $body         = $response->getBody();
        }
        catch (Exception $e) {
            rcube::raise_error($e, true, false);
            $this->status = self::CONNECTION_ERROR;
        }

        if ($this->config['debug']) {
            rcube::write_log('console', "SeaFile Response [$this->status]: " . trim($body));
        }

        // request throttled, try again
        if ($this->status == self::TOO_MANY_REQUESTS) {
            if (preg_match('/([0-9]+) second/', $body, $m) && ($seconds = $m[1]) < self::WAIT_LIMIT) {
                sleep($seconds);
                return $this->request($method, $url, $get, $post, $upload);
            }
        }

        // decode response
        return $this->status >= 400 ? false : @json_decode($body, true);
    }

    /**
     * Return error code of last operation
     */
    public function is_error()
    {
        return $this->status >= 400 ? $this->status : false;
    }

    /**
     * Authenticate to SeaFile API and get auth token
     *
     * @param string $username User name (email)
     * @param string $password User password
     *
     * @return string Authentication token
     */
    public function authenticate($username, $password)
    {
        // sanity checks
        if ($username === '' || !is_string($username) || $password === '' || !is_string($password)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        $result = $this->request('POST', 'auth-token', null, array(
                'username' => $username,
                'password' => $password,
        ));

        if ($result['token']) {
            return $this->token = $result['token'];
        }
    }

    /**
     * Get account information
     *
     * @return array Account info (usage, total, email)
     */
    public function account_info()
    {
        return $this->request('GET', "account/info");
    }

    /**
     * Delete a directory
     *
     * @param string $repo_id Library identifier
     * @param string $dir     Directory name (with path)
     *
     * @return bool True on success, False on failure
     */
    public function directory_delete($repo_id, $dir)
    {
        // sanity checks
        if ($dir === '' || $dir === '/' || !is_string($dir)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        $this->request('DELETE', "repos/$repo_id/dir", array('p' => $dir));

        return $this->is_error() === false;
    }

    /**
     * Rename a directory
     *
     * @param string $repo_id  Library identifier
     * @param string $src_dir  Directory name (with path)
     * @param string $dest_dir New directory name (with path)
     *
     * @return bool True on success, False on failure
     */
    public function directory_rename($repo_id, $src_dir, $dest_dir)
    {
        // sanity checks
        if ($src_dir === '' || $src_dir === '/' || !is_string($src_dir)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($dest_dir === '' || $dest_dir === '/' || !is_string($dest_dir) || $dest_dir === $src_dir) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        $result = $this->request('POST', "repos/$repo_id/dir", array('p' => $src_dir), array(
            'operation' => 'rename',
            'newname'   => $dest_dir,
        ));

        return $this->is_error() === false;
    }

    /**
     * Rename a directory
     *
     * @param string $repo_id Library identifier
     * @param string $dir     Directory name (with path)
     *
     * @return bool True on success, False on failure
     */
    public function directory_create($repo_id, $dir)
    {
        // sanity checks
        if ($dir === '' || $dir === '/' || !is_string($dir)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        $result = $this->request('POST', "repos/$repo_id/dir", array('p' => $dir), array(
            'operation' => 'mkdir',
        ));

        return $this->is_error() === false;
    }

    /**
     * List directory entries (files and directories)
     *
     * @param string $repo_id  Library identifier
     * @param string $dir      Directory name (with path)
     * @param string $type     Entry type ('dir' or 'file')
     *
     * @return bool|array List of directories/files on success, False on failure
     */
    public function directory_entries($repo_id, $dir, $type = null)
    {
        // sanity checks
        if (!is_string($dir)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($dir === '') {
            $dir = '/';
        }

        // args: p=<$name> ('/' is a root, default), oid=?
        // sample result
        // [{
        //    "id": "0000000000000000000000000000000000000000",
        //    "type": "file",
        //    "name": "test1.c",
        //    "size": 0
        // },{
        //    "id": "e4fe14c8cda2206bb9606907cf4fca6b30221cf9",
        //    "type": "dir",
        //    "name": "test_dir"
        // }]

        $params = array('p' => $dir);

        if ($type) {
            $params['t'] = $type == 'dir' ? 'd' : 'f';
        }

        return $this->request('GET', "repos/$repo_id/dir", $params);
    }

    /**
     * Update a file
     *
     * @param string $repo_id  Library identifier
     * @param string $filename File name (with path)
     * @param array  $file     File data (data, type, name)
     *
     * @return bool True on success, False on failure
     */
    public function file_update($repo_id, $filename, $file)
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        // first get the update link
        $result = $this->request('GET', "repos/$repo_id/update-link");

        if ($this->is_error() || empty($result)) {
            return false;
        }

        $path = explode('/', $filename);
        $fn   = array_pop($path);

        // then update file
        $result = $this->request('POST', $result, null, array(
                'filename'    => $fn,
                'target_file' => $filename,
            ),
            array('file' => $file)
        );

        return $this->is_error() === false;
    }

    /**
     * Upload a file
     *
     * @param string $repo_id  Library identifier
     * @param string $filename File name (with path)
     * @param array  $file     File data (data, type, name)
     *
     * @return bool True on success, False on failure
     */
    public function file_upload($repo_id, $filename, $file)
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        // first get upload link
        $result = $this->request('GET', "repos/$repo_id/upload-link");

        if ($this->is_error() || empty($result)) {
            return false;
        }

        $path     = explode('/', $filename);
        $filename = array_pop($path);
        $dir      = '/' . ltrim(implode('/', $path), '/');

        $file['name'] = $filename;

        // then update file
        $result = $this->request('POST', $result, null, array(
                'parent_dir' => $dir
            ),
            array('file' => $file)
        );

        return $this->is_error() === false;
    }

    /**
     * Delete a file
     *
     * @param string $repo_id  Library identifier
     * @param string $filename File name (with path)
     *
     * @return bool True on success, False on failure
     */
    public function file_delete($repo_id, $filename)
    {
        // sanity check
        if ($filename === '' || $filename === '/' || !is_string($filename)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        $this->request('DELETE', "repos/$repo_id/file", array('p' => $filename));

        return $this->is_error() === false;
    }

    /**
     * Copy file(s) (no rename here)
     *
     * @param string       $repo_id   Library identifier
     * @param string|array $files     List of files (without path)
     * @param string       $src_dir   Source directory
     * @param string       $dest_dir  Destination directory
     * @param string       $dest_repo Destination library (optional)
     *
     * @return bool True on success, False on failure
     */
    public function file_copy($repo_id, $files, $src_dir, $dest_dir, $dest_repo)
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($src_dir === '' || !is_string($src_dir)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($dest_dir === '' || !is_string($dest_dir)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ((!is_array($files) && !strlen($files)) || (is_array($files) && empty($files))) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if (empty($dest_repo)) {
            $dest_repo = $repo_id;
        }

        $result = $this->request('POST', "repos/$repo_id/fileops/copy", array('p' => $src_dir), array(
                'file_names' => implode(':', (array) $files),
                'dst_dir'   => $dest_dir,
                'dst_repo'  => $dest_repo,
        ));

        return $this->is_error() === false;
    }

    /**
     * Move a file (no rename here)
     *
     * @param string $repo_id   Library identifier
     * @param string $filename  File name (with path)
     * @param string $dst_dir   Destination directory
     * @param string $dst_repo  Destination library (optional)
     *
     * @return bool True on success, False on failure
     */
    public function file_move($repo_id, $filename, $dst_dir, $dst_repo = null)
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($filename === '' || !is_string($filename)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($dst_dir === '' || !is_string($dst_dir)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if (empty($dst_repo)) {
            $dst_repo = $repo_id;
        }

        $result = $this->request('POST', "repos/$repo_id/file", array('p' => $filename), array(
                'operation' => 'move',
                'dst_dir'   => $dst_dir,
                'dst_repo'  => $dst_repo,
        ));

        return $this->is_error() === false;
    }

    /**
     * Rename a file
     *
     * @param string $repo_id  Library identifier
     * @param string $filename File name (with path)
     * @param string $new_name New file name (without path)
     *
     * @return bool True on success, False on failure
     */
    public function file_rename($repo_id, $filename, $new_name)
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($filename === '' || !is_string($filename)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($new_name === '' || !is_string($new_name)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        $result = $this->request('POST', "repos/$repo_id/file", array('p' => $filename), array(
                'operation' => 'rename',
                'newname'   => $new_name,
        ));

        return $this->is_error() === false;
    }

    /**
     * Create an empty file
     *
     * @param string $repo_id  Library identifier
     * @param string $filename File name (with path)
     *
     * @return bool True on success, False on failure
     */
    public function file_create($repo_id, $filename)
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($filename === '' || !is_string($filename)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        $result = $this->request('POST', "repos/$repo_id/file", array('p' => $filename), array(
                'operation' => 'create',
        ));

        return $this->is_error() === false;
    }

    /**
     * Get file info
     *
     * @param string $repo_id  Library identifier
     * @param string $filename File name (with path)
     *
     * @return bool|array File info on success, False on failure
     */
    public function file_info($repo_id, $filename)
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($filename === '' || !is_string($filename)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        // sample result:
        //  "id":    "013d3d38fed38b3e8e26b21bb3463eab6831194f",
        //  "mtime": 1398148877,
        //  "type":  "file",
        //  "name":  "foo.py",
        //  "size":  22

        return $this->request('GET', "repos/$repo_id/file/detail", array('p' => $filename));
    }

    /**
     * Get file content
     *
     * @param string $repo_id  Library identifier
     * @param string $filename File name (with path)
     *
     * @return bool|string File download URI on success, False on failure
     */
    public function file_get($repo_id, $filename)
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($filename === '' || !is_string($filename)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        return $this->request('GET', "repos/$repo_id/file", array('p' => $filename));
    }

    /**
     * List libraries (repositories)
     *
     * @return array|bool List of libraries on success, False on failure
     */
    public function library_list()
    {
        $result = $this->request('GET', "repos");

        // sample result
        // [{
        //    "permission": "rw",
        //    "encrypted": false,
        //    "mtime": 1400054900,
        //    "owner": "user@mail.com",
        //    "id": "f158d1dd-cc19-412c-b143-2ac83f352290",
        //    "size": 0,
        //    "name": "foo",
        //    "type": "repo",
        //    "virtual": false,
        //    "desc": "new library",
        //    "root": "0000000000000000000000000000000000000000"
        // }]

        return $result;
    }

    /**
     * Get library info
     *
     * @param string $repo_id Library identifier
     *
     * @return array|bool Library info on success, False on failure
     */
    public function library_info($repo_id)
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        return $this->request('GET', "repos/$repo_id");
    }

    /**
     * Create library
     *
     * @param string $name        Library name
     * @param string $description Library description
     *
     * @return bool|array Library info on success, False on failure
     */
    public function library_create($name, $description = '')
    {
        if ($name === '' || !is_string($name)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        return $this->request('POST', "repos", null, array(
                'name' => $name,
                'desc' => $description,
        ));
    }

    /**
     * Rename library
     *
     * @param string $repo_id  Library identifier
     * @param string $new_name Library description
     *
     * @return bool True on success, False on failure
     */
    public function library_rename($repo_id, $name, $description = '')
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        if ($name === '' || !is_string($name)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        // Note: probably by mistake the 'op' is a GET parameter
        // maybe changed in future to be consistent with other methods
        $this->request('POST', "repos/$repo_id", array('op' => 'rename'), array(
                'repo_name' => $name,
                'repo_desc' => $description,
        ));

        return $this->is_error() === false;
    }

    /**
     * Delete library
     *
     * @param string $repo_id Library identifier
     *
     * @return bool True on success, False on failure
     */
    public function library_delete($repo_id)
    {
        if ($repo_id === '' || !is_string($repo_id)) {
            $this->status = self::BAD_REQUEST;
            return false;
        }

        $this->request('DELETE', "repos/$repo_id");

        return $this->is_error() === false;
    }

    /**
     * Ping the API server
     *
     * @param string $token If set, auth token will be used
     *
     * @param bool True on success, False on failure
     */
    public function ping($token = null)
    {
        // can be used to check if token is still valid
        if ($token) {
            $this->token = $token;

            $result = $this->request('GET', 'auth/ping', null, null);
        }
        // or if api works
        else {
            $result = $this->request('GET', 'ping', null, null);
        }

        return $this->is_error() === false;
    }
}
