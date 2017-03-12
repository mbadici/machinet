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

class file_api
{
    const ERROR_CODE = 500;
    const OUTPUT_JSON = 'application/json';
    const OUTPUT_HTML = 'text/html';

    public $session;
    public $api;

    private $app_name = 'Kolab File API';
    private $conf;
    private $browser;
    private $output_type = self::OUTPUT_JSON;
    private $config = array(
        'date_format' => 'Y-m-d H:i',
        'language'    => 'en_US',
    );


    public function __construct()
    {
        $rcube = rcube::get_instance();
        $rcube->add_shutdown_function(array($this, 'shutdown'));
        $this->conf = $rcube->config;
        $this->session_init();
    }

    /**
     * Initialise backend class
     */
    protected function api_init()
    {
        if ($this->api) {
            return;
        }

        $driver = $this->conf->get('fileapi_backend', 'kolab');
        $class  = $driver . '_file_storage';

        $include_path = RCUBE_INSTALL_PATH . '/lib/' . $driver . PATH_SEPARATOR;
        $include_path .= ini_get('include_path');
        set_include_path($include_path);

        $this->api = new $class;

        // configure api
        $this->api->configure(!empty($_SESSION['config']) ? $_SESSION['config'] : $this->config);
    }

    /**
     * Process the request and dispatch it to the requested service
     */
    public function run()
    {
        $this->request = strtolower($_GET['method']);

        // Check the session, authenticate the user
        if (!$this->session_validate()) {
            $this->session->destroy(session_id());

            if ($this->request == 'authenticate') {
                $this->session->regenerate_id(false);

                if ($username = $this->authenticate()) {
                    $_SESSION['user']   = $username;
                    $_SESSION['time']   = time();
                    $_SESSION['config'] = $this->config;

                    $this->output_success(array(
                        'token'        => session_id(),
                        'capabilities' => $this->capabilities(),
                    ));
                }
            }

            throw new Exception("Invalid session", 403);
        }

        // Call service method
        $result = $this->request_handler($this->request);

        // Send success response, errors should be handled by driver class
        // by throwing exceptions or sending output by itself
        $this->output_success($result);
    }

    /**
     * Session validation check and session start
     */
    private function session_validate()
    {
        $sess_id = rcube_utils::request_header('X-Session-Token') ?: $_REQUEST['token'];

        if (empty($sess_id)) {
            session_start();
            return false;
        }

        session_id($sess_id);
        session_start();

        if (empty($_SESSION['user'])) {
            return false;
        }

        $timeout = $this->conf->get('session_lifetime', 0) * 60;
        if ($timeout && $_SESSION['time'] && $_SESSION['time'] < time() - $timeout) {
            return false;
        }
        // update session time
        $_SESSION['time'] = time();

        return true;
    }

    /**
     * Initializes session
     */
    private function session_init()
    {
        $rcube     = rcube::get_instance();
        $sess_name = $this->conf->get('session_name');
        $lifetime  = $this->conf->get('session_lifetime', 0) * 60;

        if ($lifetime) {
            ini_set('session.gc_maxlifetime', $lifetime * 2);
        }

        ini_set('session.name', $sess_name ? $sess_name : 'file_api_sessid');
        ini_set('session.use_cookies', 0);
        ini_set('session.serialize_handler', 'php');

        // use database for storing session data
        $this->session = new rcube_session($rcube->get_dbh(), $this->conf);

        $this->session->register_gc_handler(array($rcube, 'gc'));

        $this->session->set_secret($this->conf->get('des_key') . dirname($_SERVER['SCRIPT_NAME']));
        $this->session->set_ip_check($this->conf->get('ip_check'));

        // this is needed to correctly close session in shutdown function
        $rcube->session = $this->session;
    }

    /**
     * Script shutdown handler
     */
    public function shutdown()
    {
        // write performance stats to logs/console
        if ($this->conf->get('devel_mode')) {
            if (function_exists('memory_get_peak_usage'))
                $mem = memory_get_peak_usage();
            else if (function_exists('memory_get_usage'))
                $mem = memory_get_usage();

            $log = trim($this->request . ($mem ? sprintf(' [%.1f MB]', $mem/1024/1024) : ''));
            if (defined('FILE_API_START')) {
                rcube::print_timer(FILE_API_START, $log);
            }
            else {
                rcube::console($log);
            }
        }
    }

    /**
     * Authentication request handler (HTTP Auth)
     */
    private function authenticate()
    {
        if (isset($_POST['username'])) {
            $username = $_POST['username'];
            $password = $_POST['password'];
        }
        else if (!empty($_SERVER['PHP_AUTH_USER'])) {
            $username = $_SERVER['PHP_AUTH_USER'];
            $password = $_SERVER['PHP_AUTH_PW'];
        }
        // when used with (f)cgi no PHP_AUTH* variables are available without defining a special rewrite rule
        else if (!isset($_SERVER['PHP_AUTH_USER'])) {
            // "Basic didhfiefdhfu4fjfjdsa34drsdfterrde..."
            if (isset($_SERVER["REMOTE_USER"])) {
                $basicAuthData = base64_decode(substr($_SERVER["REMOTE_USER"], 6));
            }
            else if (isset($_SERVER["REDIRECT_REMOTE_USER"])) {
                $basicAuthData = base64_decode(substr($_SERVER["REDIRECT_REMOTE_USER"], 6));
            }
            else if (isset($_SERVER["Authorization"])) {
                $basicAuthData = base64_decode(substr($_SERVER["Authorization"], 6));
            }
            else if (isset($_SERVER["HTTP_AUTHORIZATION"])) {
                $basicAuthData = base64_decode(substr($_SERVER["HTTP_AUTHORIZATION"], 6));
            }

            if (isset($basicAuthData) && !empty($basicAuthData)) {
                list($username, $password) = explode(":", $basicAuthData);
            }
        }

        if (!empty($username)) {
            $this->api_init();
            $result = $this->api->authenticate($username, $password);
        }

        if (empty($result)) {
/*
            header('WWW-Authenticate: Basic realm="' . $this->app_name .'"');
            header('HTTP/1.1 401 Unauthorized');
            exit;
*/
            throw new Exception("Invalid password or username", file_api::ERROR_CODE);
        }

        return $username;
    }

    /**
     * Storage/System method handler
     */
    private function request_handler($request)
    {
        // handle "global" requests that don't require api driver
        switch ($request) {
            case 'ping':
                return array();

            case 'quit':
                $this->session->destroy(session_id());
                return array();

            case 'configure':
                foreach (array_keys($this->config) as $name) {
                    if (isset($_GET[$name])) {
                        $this->config[$name] = $_GET[$name];
                    }
                }
                $_SESSION['config'] = $this->config;

                return $this->config;

            case 'upload_progress':
                return $this->upload_progress();

            case 'mimetypes':
                return $this->supported_mimetypes();

            case 'capabilities':
                // this one actually uses api driver, but we put it here
                // because we'd need session for the api driver
                return $this->capabilities();
        }

        // init API driver
        $this->api_init();

        // GET arguments
        $args = &$_GET;

        // POST arguments (JSON)
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $post = file_get_contents('php://input');
            $args += (array) json_decode($post, true);
            unset($post);
        }

        // disable script execution time limit, so we can handle big files
        @set_time_limit(0);

        // handle request
        switch ($request) {
            case 'file_list':
                $params = array('reverse' => !empty($args['reverse']) && rcube_utils::get_boolean($args['reverse']));
                if (!empty($args['sort'])) {
                    $params['sort'] = strtolower($args['sort']);
                }

                if (!empty($args['search'])) {
                    $params['search'] = $args['search'];
                    if (!is_array($params['search'])) {
                        $params['search'] = array('name' => $params['search']);
                    }
                }

                return $this->api->file_list($args['folder'], $params);

            case 'file_upload':
                // for Opera upload frame response cannot be application/json
                $this->output_type = self::OUTPUT_HTML;

                if (!isset($args['folder']) || $args['folder'] === '') {
                    throw new Exception("Missing folder name", file_api::ERROR_CODE);
                }

                $uploads = $this->upload();
                $result  = array();

                foreach ($uploads as $file) {
                    $this->api->file_create($args['folder'] . file_storage::SEPARATOR . $file['name'], $file);
                    unset($file['path']);
                    $result[$file['name']] = array(
                        'type' => $file['type'],
                        'size' => $file['size'],
                    );
                }

                return $result;

            case 'file_create':
            case 'file_update':
                if (!isset($args['file']) || $args['file'] === '') {
                    throw new Exception("Missing file name", file_api::ERROR_CODE);
                }
                if (!isset($args['content'])) {
                    throw new Exception("Missing file content", file_api::ERROR_CODE);
                }

                $file = array(
                    'content' => $args['content'],
                    'type'    => rcube_mime::file_content_type($args['content'], $args['file'], $args['content-type'], true),
                );

                $this->api->$request($args['file'], $file);

                if (!empty($args['info']) && rcube_utils::get_boolean($args['info'])) {
                    return $this->api->file_info($args['file']);
                }

                return;

            case 'file_delete':
                $files = (array) $args['file'];

                if (empty($files)) {
                    throw new Exception("Missing file name", file_api::ERROR_CODE);
                }

                foreach ($files as $file) {
                    $this->api->file_delete($file);
                }
                return;

            case 'file_info':
                if (!isset($args['file']) || $args['file'] === '') {
                    throw new Exception("Missing file name", file_api::ERROR_CODE);
                }

                $info = $this->api->file_info($args['file']);

                if (!empty($args['viewer']) && rcube_utils::get_boolean($args['viewer'])) {
                    $this->file_viewer_info($args['file'], $info);
                }

                return $info;

            case 'file_get':
                $this->output_type = self::OUTPUT_HTML;

                if (!isset($args['file']) || $args['file'] === '') {
                    header("HTTP/1.0 ".file_api::ERROR_CODE." Missing file name");
                }

                $params = array(
                    'force-download' => !empty($args['force-download']) && rcube_utils::get_boolean($args['force-download']),
                    'force-type'     => $args['force-type'],
                );

                if (!empty($args['viewer'])) {
                    $this->file_view($args['file'], $args['viewer'], $args, $params);
                }

                try {
                    $this->api->file_get($args['file'], $params);
                }
                catch (Exception $e) {
                    header("HTTP/1.0 " . file_api::ERROR_CODE . " " . $e->getMessage());
                }
                exit;

            case 'file_move':
            case 'file_copy':
                if (!isset($args['file']) || $args['file'] === '') {
                    throw new Exception("Missing file name", file_api::ERROR_CODE);
                }

                if (is_array($args['file'])) {
                    if (empty($args['file'])) {
                        throw new Exception("Missing file name", file_api::ERROR_CODE);
                    }
                }
                else {
                    if (!isset($args['new']) || $args['new'] === '') {
                        throw new Exception("Missing new file name", file_api::ERROR_CODE);
                    }
                    $args['file'] = array($args['file'] => $args['new']);
                }

                $overwrite = !empty($args['overwrite']) && rcube_utils::get_boolean($args['overwrite']);
                $files     = (array) $args['file'];
                $errors    = array();

                foreach ($files as $file => $new_file) {
                    if ($new_file === '') {
                        throw new Exception("Missing new file name", file_api::ERROR_CODE);
                    }
                    if ($new_file === $file) {
                        throw new Exception("Old and new file name is the same", file_api::ERROR_CODE);
                    }

                    try {
                        $this->api->{$request}($file, $new_file);
                    }
                    catch (Exception $e) {
                        if ($e->getCode() == file_storage::ERROR_FILE_EXISTS) {
                            // delete existing file and do copy/move again
                            if ($overwrite) {
                                $this->api->file_delete($new_file);
                                $this->api->{$request}($file, $new_file);
                            }
                            // collect file-exists errors, so the client can ask a user
                            // what to do and skip or replace file(s)
                            else {
                                $errors[] = array(
                                    'src' => $file,
                                    'dst' => $new_file,
                                );
                            }
                        }
                        else {
                            throw $e;
                        }
                    }
                }

                if (!empty($errors)) {
                    return array('already_exist' => $errors);
                }

                return;

            case 'folder_create':
                if (!isset($args['folder']) || $args['folder'] === '') {
                    throw new Exception("Missing folder name", file_api::ERROR_CODE);
                }
                return $this->api->folder_create($args['folder']);

            case 'folder_delete':
                if (!isset($args['folder']) || $args['folder'] === '') {
                    throw new Exception("Missing folder name", file_api::ERROR_CODE);
                }
                return $this->api->folder_delete($args['folder']);

            case 'folder_rename':
                if (!isset($args['folder']) || $args['folder'] === '') {
                    throw new Exception("Missing source folder name", file_api::ERROR_CODE);
                }
                if (!isset($args['new']) || $args['new'] === '') {
                    throw new Exception("Missing destination folder name", file_api::ERROR_CODE);
                }
                if ($args['new'] === $args['folder']) {
                    return;
                }
                return $this->api->folder_rename($args['folder'], $args['new']);

            case 'folder_list':
                return $this->api->folder_list();

            case 'quota':
                $quota = $this->api->quota($args['folder']);

                if (!$quota['total']) {
                    $quota_result['percent'] = 0;
                }
                else if ($quota['total']) {
                    if (!isset($quota['percent'])) {
                        $quota_result['percent'] = min(100, round(($quota['used']/max(1,$quota['total']))*100));
                    }
                }

                return $quota;

            case 'lock':
                // arguments: uri, owner, timeout, scope, depth, token
                foreach (array('uri', 'token') as $arg) {
                    if (!isset($args[$arg]) || $args[$arg] === '') {
                        throw new Exception("Missing lock $arg", file_api::ERROR_CODE);
                    }
                }

                $this->api->lock($args['uri'], $args);
                return;

            case 'unlock':
                foreach (array('uri', 'token') as $arg) {
                    if (!isset($args[$arg]) || $args[$arg] === '') {
                        throw new Exception("Missing lock $arg", file_api::ERROR_CODE);
                    }
                }

                $this->api->unlock($args['uri'], $args);
                return;

            case 'lock_list':
                $child_locks = !empty($args['child_locks']) && rcube_utils::get_boolean($args['child_locks']);

                return $this->api->lock_list($args['uri'], $child_locks);
        }

        if ($request) {
            throw new Exception("Unknown method", 501);
        }
    }

    /**
     * File uploads handler
     */
    protected function upload()
    {
        $files = array();

        if (is_array($_FILES['file']['tmp_name'])) {
            foreach ($_FILES['file']['tmp_name'] as $i => $filepath) {
                if ($err = $_FILES['file']['error'][$i]) {
                    if ($err == UPLOAD_ERR_INI_SIZE || $err == UPLOAD_ERR_FORM_SIZE) {
                        throw new Exception("Maximum file size exceeded", file_api::ERROR_CODE);
                    }
                    throw new Exception("File upload failed", file_api::ERROR_CODE);
                }

                $files[] = array(
                    'path' => $filepath,
                    'name' => $_FILES['file']['name'][$i],
                    'size' => filesize($filepath),
                    'type' => rcube_mime::file_content_type($filepath, $_FILES['file']['name'][$i], $_FILES['file']['type']),
                );
            }
        }
        else if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            throw new Exception("File upload failed", file_api::ERROR_CODE);
        }

        return $files;
    }

    /**
     * File upload progress handler
     */
    protected function upload_progress()
    {
        if (function_exists('apc_fetch')) {
            $prefix   = ini_get('apc.rfc1867_prefix');
            $uploadid = rcube_utils::get_input_value('id', rcube_utils::INPUT_GET);
            $status   = apc_fetch($prefix . $uploadid);

            if (!empty($status)) {
                $status['percent'] = round($status['current']/$status['total']*100);
                if ($status['percent'] < 100) {
                    $diff = time() - intval($status['start_time']);
                    // calculate time to end of uploading (in seconds)
                    $status['eta'] = intval($diff * (100 - $status['percent']) / $status['percent']);
                    // average speed (bytes per second)
                    $status['rate'] = intval($status['current'] / $diff);
                }
            }

            $status['id'] = $uploadid;

            return $status; // id, done, total, current, percent, start_time, eta, rate
        }

        throw new Exception("Not supported", file_api::ERROR_CODE);
    }

    /*
     * Returns API capabilities
     */
    protected function capabilities()
    {
        $this->api_init();

        $caps = array();

        // check support for upload progress
        if (($progress_sec = $this->conf->get('upload_progress'))
            && ini_get('apc.rfc1867') && function_exists('apc_fetch')
        ) {
            $caps[file_storage::CAPS_PROGRESS_NAME] = ini_get('apc.rfc1867_name');
            $caps[file_storage::CAPS_PROGRESS_TIME] = $progress_sec;
        }

        foreach ($this->api->capabilities() as $name => $value) {
            // skip disabled capabilities
            if ($value !== false) {
                $caps[$name] = $value;
            }
        }

        return $caps;
    }

    /**
     * Return mimetypes list supported by built-in viewers
     *
     * @return array List of mimetypes
     */
    protected function supported_mimetypes()
    {
        $mimetypes = array();
        $dir       = RCUBE_INSTALL_PATH . 'lib/viewers';

        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match('/^([a-z0-9_]+)\.php$/i', $file, $matches)) {
                    include_once $dir . '/' . $file;
                    $class  = 'file_viewer_' . $matches[1];
                    $viewer = new $class($this);

                    $mimetypes = array_merge($mimetypes, $viewer->supported_mimetypes());
                }
            }
            closedir($handle);
        }

        return $mimetypes;
    }

    /**
     * Merge file viewer data into file info
     */
    protected function file_viewer_info($file, &$info)
    {
        if ($viewer = $this->find_viewer($info['type'])) {
            $info['viewer'] = array();
            if ($frame = $viewer->frame($file, $info['type'])) {
                $info['viewer']['frame'] = $frame;
            }
            else if ($href = $viewer->href($file, $info['type'])) {
                $info['viewer']['href'] = $href;
            }
        }
    }

    /**
     * File vieweing request handler
     */
    protected function file_view($file, $viewer, &$args, &$params)
    {
        $path  = RCUBE_INSTALL_PATH . "lib/viewers/$viewer.php";
        $class = "file_viewer_$viewer";

        if (!file_exists($path)) {
            return;
        }

        // get file info
        try {
            $info = $this->api->file_info($file);
        }
        catch (Exception $e) {
            header("HTTP/1.0 " . file_api::ERROR_CODE . " " . $e->getMessage());
            exit;
        }

        include_once $path;
        $viewer = new $class($this);

        // check if specified viewer supports file type
        // otherwise return (fallback to file_get action)
        if (!$viewer->supports($info['type'])) {
            return;
        }

        $viewer->output($file, $info['type']);
        exit;
    }

    /**
     * Return built-in viewer opbject for specified mimetype
     *
     * @return object Viewer object
     */
    protected function find_viewer($mimetype)
    {
        $dir = RCUBE_INSTALL_PATH . 'lib/viewers';

        if ($handle = opendir($dir)) {
            while (false !== ($file = readdir($handle))) {
                if (preg_match('/^([a-z0-9_]+)\.php$/i', $file, $matches)) {
                    include_once $dir . '/' . $file;
                    $class  = 'file_viewer_' . $matches[1];
                    $viewer = new $class($this);

                    if ($viewer->supports($mimetype)) {
                        return $viewer;
                    }
                }
            }
            closedir($handle);
        }
    }

    /**
     * Returns complete File URL
     *
     * @param string $file File name (with path)
     *
     * @return string File URL
     */
    public function file_url($file)
    {
        return file_utils::script_uri(). '?method=file_get'
            . '&file=' . urlencode($file)
            . '&token=' . urlencode(session_id());
    }

    /**
     * Returns web browser object
     *
     * @return rcube_browser Web browser object
     */
    public function get_browser()
    {
        if ($this->browser === null) {
            $this->browser = new rcube_browser;
        }

        return $this->browser;
    }

    /**
     * Send success response
     *
     * @param mixed $data Data
     */
    public function output_success($data)
    {
        if (!is_array($data)) {
            $data = array();
        }

        $response = array('status' => 'OK', 'result' => $data);

        if (!empty($_REQUEST['req_id'])) {
            $response['req_id'] = $_REQUEST['req_id'];
        }

        $this->output_send($response);
    }

    /**
     * Send error response
     *
     * @param mixed $response Response data
     * @param int   $code     Error code
     */
    public function output_error($response, $code = null)
    {
        if (is_string($response)) {
            $response = array('reason' => $response);
        }

        $response['status'] = 'ERROR';

        if ($code) {
            $response['code'] = $code;
        }

        if (!empty($_REQUEST['req_id'])) {
            $response['req_id'] = $_REQUEST['req_id'];
        }

        if (empty($response['code'])) {
            $response['code'] = file_api::ERROR_CODE;
        }

        $this->output_send($response);
    }

    /**
     * Send response
     *
     * @param mixed $data Data
     */
    protected function output_send($data)
    {
        // Send response
        header("Content-Type: {$this->output_type}; charset=utf-8");
        echo json_encode($data);
        exit;
    }
}
