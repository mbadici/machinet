<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG                                |
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
 | Author: Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                     |
 +--------------------------------------------------------------------------+
*/

class file_ui
{
    /**
     * @var kolab_client_output
     */
    protected $output;

    /**
     * @var kolab_client_api
     */
    public $api;

    /**
     * @var Conf
     */
    protected $config;

    protected $ajax_only = false;
    protected $page_title = 'Kolab File API';
    protected $menu = array();
    protected $cache = array();
    protected $devel_mode = false;
    protected $object_types = array();

    protected static $translation = array();


    /**
     * Class constructor.
     *
     * @param file_ui_output $output Optional output object
     */
    public function __construct($output = null)
    {
        $rcube = rcube::get_instance();
        $rcube->add_shutdown_function(array($this, 'shutdown'));

        $this->config_init();

        $this->devel_mode = $this->config->get('devel_mode', false);

        $this->output_init($output);
        $this->api_init();

        ini_set('session.use_cookies', 'On');
        session_start();

        // Initialize locales
        $this->locale_init();

        $this->auth();
    }

    /**
     * Localization initialization.
     */
    protected function locale_init()
    {
        $language = $this->get_language();
        $LANG     = array();

        if (!$language) {
            $language = 'en_US';
        }

        @include RCUBE_INSTALL_PATH . '/lib/locale/en_US.php';

        if ($language != 'en_US') {
            @include RCUBE_INSTALL_PATH . "/lib/locale/$language.php";
        }

        setlocale(LC_ALL, $language . '.utf8', $language . 'UTF-8', 'en_US.utf8', 'en_US.UTF-8');

        self::$translation = $LANG;
    }

    /**
     * Configuration initialization.
     */
    private function config_init()
    {
        $this->config = rcube::get_instance()->config;
    }

    /**
     * Output initialization.
     */
    private function output_init($output = null)
    {
        if ($output) {
            $this->output = $output;
            return;
        }

        $skin = $this->config->get('file_api_skin', 'default');
        $this->output = new file_ui_output($skin);

        // Assign self to template variable
        $this->output->assign('engine', $this);
    }

    /**
     * API initialization
     */
    private function api_init()
    {
        $url = $this->config->get('file_api_url', '');

        if (!$url) {
            $url = rcube_utils::https_check() ? 'https://' : 'http://';
            $url .= $_SERVER['SERVER_NAME'];
            $url .= preg_replace('/\/?\?.*$/', '', $_SERVER['REQUEST_URI']);
            $url .= '/api/';
        }

        $this->api = new file_ui_api($url);
    }

    /**
     * Initializes User Interface
     */
    protected function ui_init()
    {
        // assign token
        $this->output->set_env('token', $_SESSION['user']['token']);

        // assign capabilities
        $this->output->set_env('capabilities', $_SESSION['caps']);

        // add watermark content
        $this->output->set_env('watermark', $this->output->get_template('watermark'));
//        $this->watermark('taskcontent');

        // assign default set of translations
        $this->output->add_translation('loading', 'servererror');

//        $this->output->assign('tasks', $this->menu);
//        $this->output->assign('main_menu', $this->menu());
        $this->output->assign('user', $_SESSION['user']);

        if ($_SESSION['caps']['MAX_UPLOAD']) {
            $this->output->assign('max_upload', $this->show_bytes($_SESSION['caps']['MAX_UPLOAD']));
        }
    }

    /**
     * Returns system language (locale) setting.
     *
     * @return string Language code
     */
    private function get_language()
    {
        $aliases = array(
            'de' => 'de_DE',
            'en' => 'en_US',
            'pl' => 'pl_PL',
        );

        // UI language
        $langs = !empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '';
        $langs = explode(',', $langs);

        if (!empty($_SESSION['user']) && !empty($_SESSION['user']['language'])) {
            array_unshift($langs, $_SESSION['user']['language']);
        }

        while ($lang = array_shift($langs)) {
            $lang = explode(';', $lang);
            $lang = $lang[0];
            $lang = str_replace('-', '_', $lang);

            if (file_exists(RCUBE_INSTALL_PATH . "/lib/locale/$lang.php")) {
                return $lang;
            }

            if (isset($aliases[$lang]) && ($alias = $aliases[$lang])
                && file_exists(RCUBE_INSTALL_PATH . "/lib/locale/$alias.php")
            ) {
                return $alias;
            }
        }

        return null;
    }

    /**
     * User authentication (and authorization).
     */
    private function auth()
    {
        if (isset($_POST['login'])) {
            $login = $this->get_input('login', 'POST');

            if ($login['username']) {
                $result = $this->api->login($login['username'], $login['password']);

                if ($token = $result->get('token')) {
                    $user = array(
                        'token'    => $token,
                        'username' => $login['username'],
                    );

                    $this->api->set_session_token($user['token']);
/*
                    // Find user settings
                    // Don't call API user.info for non-existing users (#1025)
                    if (preg_match('/^cn=([a-z ]+)/i', $login['username'], $m)) {
                        $user['fullname'] = ucwords($m[1]);
                    }
                    else {
                        $res = $this->api->get('user.info', array('user' => $user['id']));
                        $res = $res->get();

                        if (is_array($res) && !empty($res)) {
                            $user['language'] = $res['preferredlanguage'];
                            $user['fullname'] = $res['cn'];
                        }
                    }
*/
                    // Save capabilities
                    $_SESSION['caps'] = $result->get('capabilities');
                    // Save user data
                    $_SESSION['user'] = $user;

                    if (($language = $this->get_language()) && $language != 'en_US') {
                        $_SESSION['user']['language'] = $language;
                        $session_config['language']   = $language;
                    }
/*
                    // Configure API session
                    if (!empty($session_config)) {
                        $this->api->post('system.configure', null, $session_config);
                    }
*/
                    header('Location: ?');
                    die;
                }
                else {
                    $code  = $result->get_error_code();
                    $str   = $result->get_error_str();
                    $label = 'loginerror';

                    if ($code == file_ui_api::ERROR_INTERNAL
                        || $code == file_ui_api::ERROR_CONNECTION
                    ) {
                        $label = 'internalerror';
                        $this->raise_error(500, 'Login failed. ' . $str);
                    }
                    $this->output->command('display_message', $label, 'error');
                }
            }
        }
        else if (!empty($_SESSION['user']) && !empty($_SESSION['user']['token'])) {
            // Validate session
            $timeout = $this->config->get('session_timeout', 3600);
            if ($timeout && $_SESSION['time'] && $_SESSION['time'] < time() - $timeout) {
                $this->action_logout(true);
            }

            // update session time
            $_SESSION['time'] = time();

            // Set API session key
            $this->api->set_session_token($_SESSION['user']['token']);
        }
    }

    /**
     * Main execution.
     */
    public function run()
    {
        // Session check
        if (empty($_SESSION['user']) || empty($_SESSION['user']['token'])) {
            $this->action_logout();
        }

        // Run security checks
        $this->input_checks();

        $this->action = $this->get_input('action', 'GET');

        if ($this->action) {
            $method = 'action_' . $this->action;
            if (method_exists($this, $method)) {
                $this->$method();
            }
        }
        else if (method_exists($this, 'action_default')) {
            $this->action_default();
        }
    }

    /**
     * Security checks and input validation.
     */
    public function input_checks()
    {
        $ajax = $this->output->is_ajax();

        // Check AJAX-only tasks
        if ($this->ajax_only && !$ajax) {
            $this->raise_error(500, 'Invalid request type!', null, true);
        }

        // CSRF prevention
        $token  = $ajax ? rcube_utils::request_header('X-Session-Token') : $this->get_input('token');
        $task   = $this->get_task();

        if ($task != 'main' && $token != $_SESSION['user']['token']) {
            $this->raise_error(403, 'Invalid request data!', null, true);
        }
    }

    /**
     * Logout action.
     */
    private function action_logout($sess_expired = false, $stop_sess = true)
    {
        if (!empty($_SESSION['user']) && !empty($_SESSION['user']['token']) && $stop_sess) {
            $this->api->logout();
        }
        $_SESSION = array();

        if ($this->output->is_ajax()) {
            if ($sess_expired) {
                $args = array('error' => 'session.expired');
            }
            $this->output->command('main_logout', $args);

            if ($sess_expired) {
                $this->output->send();
                exit;
            }
        }
        else {
            $this->output->add_translation('loginerror', 'internalerror', 'session.expired');
        }

        if ($sess_expired) {
            $error = 'session.expired';
        }
        else {
            $error = $this->get_input('error', 'GET');
        }

        if ($error) {
            $this->output->command('display_message', $error, 'error', 60000);
        }

        $this->output->send('login');
        exit;
    }

    /**
     * Error action (with error logging).
     *
     * @param int    $code   Error code
     * @param string $msg    Error message
     * @param array  $args   Optional arguments (type, file, line)
     * @param bool   $output Enable to send output and finish
     */
    public function raise_error($code, $msg, $args = array(), $output = false)
    {
        $log_line = sprintf("%s Error: %s (%s)",
            isset($args['type']) ? $args['type'] : 'PHP',
            $msg . (isset($args['file']) ? sprintf(' in %s on line %d', $args['file'], $args['line']) : ''),
            $_SERVER['REQUEST_METHOD']);

        rcube::write_log('errors', $log_line);

        if (!$output) {
            return;
        }

        if ($this->output->is_ajax()) {
            header("HTTP/1.0 $code $msg");
            die;
        }

        $this->output->assign('error_code', $code);
        $this->output->assign('error_message', $msg);
        $this->output->send('error');
        exit;
    }

    /**
     * Script shutdown handler
     */
    public function shutdown()
    {
        // write performance stats to logs/console
        if ($this->devel_mode) {
            if (function_exists('memory_get_peak_usage'))
                $mem = memory_get_peak_usage();
            else if (function_exists('memory_get_usage'))
                $mem = memory_get_usage();

            $log = 'ui:' . $this->get_task() . ($this->action ? '/' . $this->action : '');
            $log .= ($mem ? sprintf(' [%.1f MB]', $mem/1024/1024) : '');

            if (defined('FILE_API_START')) {
                rcube::print_timer(FILE_API_START, $log);
            }
            else {
                rcube::console($log);
            }
        }
    }

    /**
     * Output sending.
     */
    public function send()
    {
        $task = $this->get_task();

        if ($this->page_title) {
            $this->output->assign('pagetitle', $this->page_title);
        }

        $this->output->set_env('task', $task);

        $this->output->send($this->task_template ? $this->task_template : $task);
        exit;
    }

    /**
     * Returns name of the current task.
     *
     * @return string Task name
     */
    public function get_task()
    {
        $class_name = get_class($this);

        if (preg_match('/^file_ui_client_([a-z]+)$/', $class_name, $m)) {
            return $m[1];
        }
    }

    /**
     * Returns translation of defined label/message.
     *
     * @return string Translated string.
     */
    public static function translate()
    {
        $args = func_get_args();

        if (is_array($args[0])) {
            $args = $args[0];
        }

        $label = $args[0];

        if (isset(self::$translation[$label])) {
            $content = trim(self::$translation[$label]);
        }
        else {
            $content = $label;
        }

        for ($i = 1, $len = count($args); $i < $len; $i++) {
            $content = str_replace('$'.$i, $args[$i], $content);
        }

        return $content;
    }

    /**
     * Returns input parameter value.
     *
     * @param string $name       Parameter name
     * @param string $type       Parameter type (GET|POST|NULL)
     * @param bool   $allow_html Disables stripping of insecure content (HTML tags)
     *
     * @see rcube_utils::get_input_value
     * @return mixed Input value.
     */
    public static function get_input($name, $type = null, $allow_html = false)
    {
        if ($type == 'GET') {
            $type = rcube_utils::INPUT_GET;
        }
        else if ($type == 'POST') {
            $type = rcube_utils::INPUT_POST;
        }
        else {
            $type = rcube_utils::INPUT_GPC;
        }

        $result = rcube_utils::get_input_value($name, $type, $allow_html);
        return $result;
    }

    /**
     * Returns task menu output.
     *
     * @return string HTML output
     */
    protected function menu()
    {
    }

    /**
     * Adds watermark page definition into main page.
     */
    protected function watermark($name)
    {
        $this->output->command('set_watermark', $name);
    }

    /**
     * API GET request wrapper
     */
    protected function api_get($action, $get = array())
    {
        return $this->api_call('get', $action, $get);
    }

    /**
     * API POST request wrapper
     */
    protected function api_post($action, $get = array(), $post = array())
    {
        return $this->api_call('post', $action, $get, $post);
    }

    /**
     * API request wrapper with error handling
     */
    protected function api_call($type, $action, $get = array(), $post = array())
    {
        if ($type == 'post') {
            $result = $this->api->post($action, $get, $post);
        }
        else {
            $result = $this->api->get($action, $get);
        }

        // error handling
        if ($code = $result->get_error_code()) {
            // Invalid session, do logout
            if ($code == 403) {
                $this->action_logout(true, false);
            }

            // Log communication errors, other should be logged on API side
            if ($code < 400) {
                $this->raise_error($code, 'API Error: ' . $result->get_error_str());
            }
        }

        return $result;
    }

    /**
     * Returns execution time in seconds
     *
     * @param string Execution time
     */
    public function gentime()
    {
        return sprintf('%.4f', microtime(true) - FILE_API_START);
    }

    /**
     * Returns HTML output of login form
     *
     * @param string HTML output
     */
    public function login_form()
    {
        $post = $this->get_input('login', 'POST');

        $user_input = new html_inputfield(array(
            'type'  => 'text',
            'id'    => 'login_name',
            'name'  => 'login[username]',
            'autofocus' => true,
        ));

        $pass_input = new html_inputfield(array(
            'type'  => 'password',
            'id'    => 'login_pass',
            'name'  => 'login[password]',
        ));

        $button = new html_inputfield(array(
            'type'  => 'submit',
            'id'    => 'login_submit',
            'value' => $this->translate('login.login'),
        ));

        $username = html::label(array('for' => 'login_name'), $this->translate('login.username'))
            . $user_input->show($post['username']);
        $password = html::label(array('for' => 'login_pass'), $this->translate('login.password'))
            . $pass_input->show('');

        $form = html::tag('form', array(
            'id'     => 'login_form',
            'name'   => 'login',
            'method' => 'post',
            'action' => '?'),
            html::span(null, $username) . html::span(null, $password) . $button->show());

        return $form;
    }

    /**
     * Create a human readable string for a number of bytes
     *
     * @param int Number of bytes
     *
     * @return string Byte string
     */
    protected function show_bytes($bytes)
    {
        if ($bytes >= 1073741824) {
            $gb  = $bytes/1073741824;
            $str = sprintf($gb>=10 ? "%d " : "%.1f ", $gb) . $this->translate('size.GB');
        }
        else if ($bytes >= 1048576) {
            $mb  = $bytes/1048576;
            $str = sprintf($mb>=10 ? "%d " : "%.1f ", $mb) . $this->translate('size.MB');
        }
        else if ($bytes >= 1024) {
            $str = sprintf("%d ",  round($bytes/1024)) . $this->translate('size.KB');
        }
        else {
            $str = sprintf("%d ", $bytes) . $this->translate('size.B');
        }

        return $str;
    }
}
