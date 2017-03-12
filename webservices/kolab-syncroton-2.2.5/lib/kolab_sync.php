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
 * Main application class (based on Roundcube Framework)
 */
class kolab_sync extends rcube
{
    /**
     * Application name
     *
     * @var string
     */
    public $app_name = 'ActiveSync for Kolab'; // no double quotes inside

    /**
     * Current user
     *
     * @var rcube_user
     */
    public $user;

    const CHARSET = 'UTF-8';
    const VERSION = "2.2.5";


    /**
     * This implements the 'singleton' design pattern
     *
     * @return kolab_sync The one and only instance
     */
    static function get_instance()
    {
        if (!self::$instance || !is_a(self::$instance, 'kolab_sync')) {
            self::$instance = new kolab_sync();
            self::$instance->startup();  // init AFTER object was linked with self::$instance
        }

        return self::$instance;
    }


    /**
     * Initialization of class instance
     */
    public function startup()
    {
        // Initialize Syncroton Logger
        $debug_mode   = $this->config->get('activesync_debug') ? kolab_sync_logger::DEBUG : kolab_sync_logger::WARN;
        $this->logger = new kolab_sync_logger($debug_mode);

        // Get list of plugins
        // WARNING: We can use only plugins that are prepared for this
        //          e.g. are not using output or rcmail objects or
        //          doesn't throw errors when using them
        $plugins = (array)$this->config->get('activesync_plugins', array('kolab_auth'));
        $required = array('libkolab');

        // Initialize/load plugins
        $this->plugins = kolab_sync_plugin_api::get_instance();
        $this->plugins->init($this, $this->task);
        $this->plugins->load_plugins($plugins, $required);
    }


    /**
     * Application execution (authentication and ActiveSync)
     */
    public function run()
    {
        $this->plugins->exec_hook('startup', array('task' => 'login'));

        // when used with (f)cgi no PHP_AUTH* variables are available without defining a special rewrite rule
        if (!isset($_SERVER['PHP_AUTH_USER'])) {
            // "Basic didhfiefdhfu4fjfjdsa34drsdfterrde..."
            if (isset($_SERVER["REMOTE_USER"])) {
                $basicAuthData = base64_decode(substr($_SERVER["REMOTE_USER"], 6));
            } elseif (isset($_SERVER["REDIRECT_REMOTE_USER"])) {
                $basicAuthData = base64_decode(substr($_SERVER["REDIRECT_REMOTE_USER"], 6));
            } elseif (isset($_SERVER["Authorization"])) {
                $basicAuthData = base64_decode(substr($_SERVER["Authorization"], 6));
            } elseif (isset($_SERVER["HTTP_AUTHORIZATION"])) {
                $basicAuthData = base64_decode(substr($_SERVER["HTTP_AUTHORIZATION"], 6));
            }

            if (isset($basicAuthData) && !empty($basicAuthData)) {
                list($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']) = explode(":", $basicAuthData);
            }
        }

        if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
            // Convert domain.tld\username into username@domain (?)
            $username = explode("\\", $_SERVER['PHP_AUTH_USER']);
            if (count($username) == 2) {
                $_SERVER['PHP_AUTH_USER'] = $username[1];
                if (!strpos($_SERVER['PHP_AUTH_USER'], '@') && !empty($username[0])) {
                    $_SERVER['PHP_AUTH_USER'] .= '@' . $username[0];
                }
            }

            // Authenticate the user
            $userid = $this->authenticate($_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW']);
        }

        if (empty($userid)) {
            header('WWW-Authenticate: Basic realm="' . $this->app_name .'"');
            header('HTTP/1.1 401 Unauthorized');
            exit;
        }

        // Set log directory per-user
        $this->set_log_dir($this->username ?: $_SERVER['PHP_AUTH_USER']);

        // Save user password for Roundcube Framework
        $this->password = $_SERVER['PHP_AUTH_PW'];

        // Register Syncroton backends
        Syncroton_Registry::set('loggerBackend',                         $this->logger);
        Syncroton_Registry::set(Syncroton_Registry::DATABASE,            new kolab_sync_db);
        Syncroton_Registry::set(Syncroton_Registry::TRANSACTIONMANAGER,  kolab_sync_transaction_manager::getInstance());
        Syncroton_Registry::set(Syncroton_Registry::DEVICEBACKEND,       new kolab_sync_backend_device);
        Syncroton_Registry::set(Syncroton_Registry::FOLDERBACKEND,       new kolab_sync_backend_folder);
        Syncroton_Registry::set(Syncroton_Registry::SYNCSTATEBACKEND,    new kolab_sync_backend_state);
        Syncroton_Registry::set(Syncroton_Registry::CONTENTSTATEBACKEND, new kolab_sync_backend_content);
        Syncroton_Registry::set(Syncroton_Registry::POLICYBACKEND,       new kolab_sync_backend_policy);

        Syncroton_Registry::setContactsDataClass('kolab_sync_data_contacts');
        Syncroton_Registry::setCalendarDataClass('kolab_sync_data_calendar');
        Syncroton_Registry::setEmailDataClass('kolab_sync_data_email');
        Syncroton_Registry::setTasksDataClass('kolab_sync_data_tasks');
        Syncroton_Registry::setGALDataClass('kolab_sync_data_gal');

        // Configuration
        Syncroton_Registry::set(Syncroton_Registry::PING_TIMEOUT, $this->config->get('activesync_ping_timeout', 60));
        Syncroton_Registry::set(Syncroton_Registry::QUIET_TIME,   $this->config->get('activesync_quiet_time', 180));

        // Run Syncroton
        $syncroton = new Syncroton_Server($userid);
        $syncroton->handle();
    }


    /**
     * Authenticates a user
     *
     * @param string $username User name
     * @param string $password User password
     *
     * @param int User ID
     */
    public function authenticate($username, $password)
    {
        // use shared cache for kolab_auth plugin result (username canonification)
        $cache     = $this->get_cache_shared('activesync_auth');
        $host      = $this->select_host($username);
        $cache_key = sha1($username . '::' . $host);

        if (!$cache || !($auth = $cache->get($cache_key))) {
            $auth = $this->plugins->exec_hook('authenticate', array(
                'host'  => $host,
                'user'  => $username,
                'pass'  => $password,
            ));

            if (!$auth['abort'] && $cache) {
                $cache->set($cache_key, array(
                    'user'  => $auth['user'],
                    'host'  => $auth['host'],
                ));
            }

            // LDAP server failure... send 503 error
            if ($auth['kolab_ldap_error']) {
                self::server_error();
            }
        }
        else {
            $auth['pass'] = $password;
        }

        // Authenticate - get Roundcube user ID
        if (!$auth['abort'] && ($userid = $this->login($auth['user'], $auth['pass'], $auth['host'], $err))) {
            // set real username
            $this->username = $auth['user'];
            return $userid;
        }

        $this->plugins->exec_hook('login_failed', array(
            'host' => $auth['host'],
            'user' => $auth['user'],
        ));

        // IMAP server failure... send 503 error
        if ($err == rcube_imap_generic::ERROR_BAD) {
            self::server_error();
        }
    }


    /**
     * Storage host selection
     */
    private function select_host($username)
    {
        // Get IMAP host
        $host = $this->config->get('default_host');

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
     * Authenticates a user in IMAP and returns Roundcube user ID.
     */
    private function login($username, $password, $host, &$error = null)
    {
        if (empty($username)) {
            return null;
        }

        $login_lc     = $this->config->get('login_lc');
        $default_port = $this->config->get('default_port', 143);

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
        $storage = $this->get_storage();
        if (!$storage->connect($host, $username, $password, $port, $ssl)) {
            $error = $storage->get_error_code();
            return null;
        }

        // No user in database, but IMAP auth works
        if (!is_object($user)) {
            if ($this->config->get('auto_create_user')) {
                // create a new user record
                $user = rcube_user::create($username, $host);

                if (!$user) {
                    self::raise_error(array(
                        'code' => 620, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__,
                        'message' => "Failed to create a user record",
                    ), true, false);
                    return null;
                }
            }
            else {
                self::raise_error(array(
                    'code' => 620, 'type' => 'php', 'file' => __FILE__, 'line' => __LINE__,
                    'message' => "Access denied for new user $username. 'auto_create_user' is disabled",
                ), true, false);
                return null;
            }
        }

        // overwrite config with user preferences
        $this->user = $user;
        $this->config->set_user_prefs((array)$this->user->get_prefs());
        $this->set_storage_prop();

        setlocale(LC_ALL, 'en_US.utf8', 'en_US.UTF-8');

        // force reloading of mailboxes list/data
        //$storage->clear_cache('mailboxes', true);

        return $user->ID;
    }


    /**
     * Set logging directory per-user
     */
    protected function set_log_dir($username)
    {
        if (empty($username)) {
            return;
        }

        $this->logger->set_username($username);

        $user_debug = $this->config->get('activesync_user_debug');
        $user_log   = $user_debug || $this->config->get('activesync_user_log');

        if (!$user_log) {
            return;
        }

        $log_dir  = $this->config->get('log_dir');
        $log_dir .= DIRECTORY_SEPARATOR . $username;

        // in user_debug mode enable logging only if user directory exists
        if ($user_debug) {
            if (!is_dir($log_dir)) {
                return;
            }
        }
        else if (!is_dir($log_dir)) {
            if (!mkdir($log_dir, 0770)) {
                return;
            }
        }

        if (!empty($_GET['DeviceId'])) {
            $log_dir .= DIRECTORY_SEPARATOR . $_GET['DeviceId'];
        }

        if (!is_dir($log_dir)) {
            if (!mkdir($log_dir, 0770)) {
                return;
            }
        }

        // make sure we're using debug mode where possible,
        if ($user_debug) {
            $this->config->set('debug_level', 1);
            $this->config->set('memcache_debug', true);
            $this->config->set('imap_debug', true);
            $this->config->set('ldap_debug', true);
            $this->config->set('smtp_debug', true);
            $this->config->set('sql_debug', true);

            // SQL/IMAP debug need to be set directly on the object instance
            // it's already initialized/configured
            if ($db = $this->get_dbh()) {
                $db->set_debug(true);
            }
            if ($storage = $this->get_storage()) {
                $storage->set_debug(true);
            }

            $this->logger->mode = kolab_sync_logger::DEBUG;
        }

        $this->config->set('log_dir', $log_dir);

        // re-set PHP error logging
        if (($this->config->get('debug_level') & 1) && $this->config->get('log_driver') != 'syslog') {
            ini_set('error_log', $log_dir . '/errors');
        }
    }


    /**
     * Send HTTP 503 response.
     * We send it on LDAP/IMAP server error instead of 401 (Unauth),
     * so devices will not ask for new password.
     */
    public static function server_error()
    {
        header("HTTP/1.1 503 Service Temporarily Unavailable");
        header("Retry-After: 120");
        exit;
    }


    /**
     * Function to be executed in script shutdown
     */
    public function shutdown()
    {
        parent::shutdown();

        // cache garbage collector
        $this->gc_run();

        // write performance stats to logs/console
        if ($this->config->get('devel_mode')) {
            if (function_exists('memory_get_usage'))
                $mem = sprintf('%.1f', memory_get_usage() / 1048576);
            if (function_exists('memory_get_peak_usage'))
                $mem .= '/' . sprintf('%.1f', memory_get_peak_usage() / 1048576);

            $query = $_SERVER['QUERY_STRING'];
            $log   = $query . ($mem ? ($query ? ' ' : '') . "[$mem]" : '');

            if (defined('KOLAB_SYNC_START'))
                self::print_timer(KOLAB_SYNC_START, $log);
            else
                self::console($log);
        }
    }
}
