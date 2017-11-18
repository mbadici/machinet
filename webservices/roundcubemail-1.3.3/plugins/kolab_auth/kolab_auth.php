<?php

/**
 * Kolab Authentication (based on ldap_authentication plugin)
 *
 * Authenticates on LDAP server, finds canonized authentication ID for IMAP
 * and for new users creates identity based on LDAP information.
 *
 * Supports impersonate feature (login as another user). To use this feature
 * imap_auth_type/smtp_auth_type must be set to DIGEST-MD5 or PLAIN.
 *
 * @version @package_version@
 * @author Aleksander Machniak <machniak@kolabsys.com>
 *
 * Copyright (C) 2011-2013, Kolab Systems AG <contact@kolabsys.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class kolab_auth extends rcube_plugin
{
    static $ldap;
    private $username;
    private $data = array();

    public function init()
    {
        $rcmail = rcube::get_instance();

        $this->load_config();

        $this->add_hook('authenticate', array($this, 'authenticate'));
        $this->add_hook('startup', array($this, 'startup'));
        $this->add_hook('ready', array($this, 'ready'));
        $this->add_hook('user_create', array($this, 'user_create'));

        // Hook for password change
        $this->add_hook('password_ldap_bind', array($this, 'password_ldap_bind'));

        // Hooks related to "Login As" feature
        $this->add_hook('template_object_loginform', array($this, 'login_form'));
        $this->add_hook('storage_connect', array($this, 'imap_connect'));
        $this->add_hook('managesieve_connect', array($this, 'imap_connect'));
        $this->add_hook('smtp_connect', array($this, 'smtp_connect'));
        $this->add_hook('identity_form', array($this, 'identity_form'));

        // Hook to modify some configuration, e.g. ldap
        $this->add_hook('config_get', array($this, 'config_get'));

        // Hook to modify logging directory
        $this->add_hook('write_log', array($this, 'write_log'));
        $this->username = $_SESSION['username'];

        // Enable debug logs (per-user), when logged as another user
        if (!empty($_SESSION['kolab_auth_admin']) && $rcmail->config->get('kolab_auth_auditlog')) {
            $rcmail->config->set('debug_level', 1);
            $rcmail->config->set('smtp_log', true);
            $rcmail->config->set('log_logins', true);
            $rcmail->config->set('log_session', true);
            $rcmail->config->set('memcache_debug', true);
            $rcmail->config->set('imap_debug', true);
            $rcmail->config->set('ldap_debug', true);
            $rcmail->config->set('smtp_debug', true);
            $rcmail->config->set('sql_debug', true);

            // SQL debug need to be set directly on DB object
            // setting config variable will not work here because
            // the object is already initialized/configured
            if ($db = $rcmail->get_dbh()) {
                $db->set_debug(true);
            }
        }
    }

    /**
     * Ready hook handler
     */
    public function ready($args)
    {
        $rcmail = rcube::get_instance();

        // Store user unique identifier for freebusy_session_auth feature
        if (!($uniqueid = $rcmail->config->get('kolab_uniqueid'))) {
            $uniqueid = $_SESSION['kolab_auth_uniqueid'];

            if (!$uniqueid) {
                // Find user record in LDAP
                if (($ldap = self::ldap()) && $ldap->ready) {
                    if ($record = $ldap->get_user_record($rcmail->get_user_name(), $_SESSION['kolab_host'])) {
                        $uniqueid = $record['uniqueid'];
                    }
                }
            }

            if ($uniqueid) {
                $uniqueid = md5($uniqueid);
                $rcmail->user->save_prefs(array('kolab_uniqueid' => $uniqueid));
            }
        }

        // Set/update freebusy_session_auth entry
        if ($uniqueid && empty($_SESSION['kolab_auth_admin'])
            && ($ttl = $rcmail->config->get('freebusy_session_auth'))
        ) {
            if ($ttl === true) {
                $ttl = $rcmail->config->get('session_lifetime', 0) * 60;

                if (!$ttl) {
                    $ttl = 10 * 60;
                }
            }

            $rcmail->config->set('freebusy_auth_cache', 'db');
            $rcmail->config->set('freebusy_auth_cache_ttl', $ttl);

            if ($cache = $rcmail->get_cache_shared('freebusy_auth', false)) {
                $key      = md5($uniqueid . ':' . rcube_utils::remote_addr() . ':' . $rcmail->get_user_name());
                $value    = $cache->get($key);
                $deadline = new DateTime('now', new DateTimeZone('UTC'));

                // We don't want to do the cache update on every request
                // do it once in a 1/10 of the ttl
                if ($value) {
                    $value = new DateTime($value);
                    $value->sub(new DateInterval('PT' . intval($ttl * 9/10) . 'S'));
                    if ($value > $deadline) {
                        return;
                    }
                }

                $deadline->add(new DateInterval('PT' . $ttl . 'S'));

                $cache->set($key, $deadline->format(DateTime::ISO8601));
            }
        }
    }

    /**
     * Startup hook handler
     */
    public function startup($args)
    {
        // Check access rights when logged in as another user
        if (!empty($_SESSION['kolab_auth_admin']) && $args['task'] != 'login' && $args['task'] != 'logout') {
            // access to specified task is forbidden,
            // redirect to the first task on the list
            if (!empty($_SESSION['kolab_auth_allowed_tasks'])) {
                $tasks = (array)$_SESSION['kolab_auth_allowed_tasks'];
                if (!in_array($args['task'], $tasks) && !in_array('*', $tasks)) {
                    header('Location: ?_task=' . array_shift($tasks));
                    die;
                }

                // add script that will remove disabled taskbar buttons
                if (!in_array('*', $tasks)) {
                    $this->add_hook('render_page', array($this, 'render_page'));
                }
            }
        }

        // load per-user settings
        $this->load_user_role_plugins_and_settings();

        return $args;
    }

    /**
     * Modify some configuration according to LDAP user record
     */
    public function config_get($args)
    {
        // Replaces ldap_vars (%dc, etc) in public kolab ldap addressbooks
        // config based on the users base_dn. (for multi domain support)
        if ($args['name'] == 'ldap_public' && !empty($args['result'])) {
            $rcmail      = rcube::get_instance();
            $kolab_books = (array) $rcmail->config->get('kolab_auth_ldap_addressbooks');

            foreach ($args['result'] as $name => $config) {
                if (in_array($name, $kolab_books) || in_array('*', $kolab_books)) {
                    $args['result'][$name] = $this->patch_ldap_config($config);
                }
            }
        }
        else if ($args['name'] == 'kolab_users_directory' && !empty($args['result'])) {
            $args['result'] = $this->patch_ldap_config($args['result']);
        }

        return $args;
    }

    /**
     * Helper method to patch the given LDAP directory config with user-specific values
     */
    protected function patch_ldap_config($config)
    {
        if (is_array($config)) {
            $config['base_dn']        = self::parse_ldap_vars($config['base_dn']);
            $config['search_base_dn'] = self::parse_ldap_vars($config['search_base_dn']);
            $config['bind_dn']        = str_replace('%dn', $_SESSION['kolab_dn'], $config['bind_dn']);

            if (!empty($config['groups'])) {
                $config['groups']['base_dn'] = self::parse_ldap_vars($config['groups']['base_dn']);
            }
        }

        return $config;
    }

    /**
     * Modifies list of plugins and settings according to
     * specified LDAP roles
     */
    public function load_user_role_plugins_and_settings($startup = false)
    {
        if (empty($_SESSION['user_roledns'])) {
            return;
        }

        $rcmail = rcube::get_instance();

        // Example 'kolab_auth_role_plugins' =
        //
        //  Array(
        //      '<role_dn>' => Array('plugin1', 'plugin2'),
        //  );
        //
        // NOTE that <role_dn> may in fact be something like: 'cn=role,%dc'

        $role_plugins = $rcmail->config->get('kolab_auth_role_plugins');

        // Example $rcmail_config['kolab_auth_role_settings'] =
        //
        //  Array(
        //      '<role_dn>' => Array(
        //          '$setting' => Array(
        //              'mode' => '(override|merge)', (default: override)
        //              'value' => <>,
        //              'allow_override' => (true|false) (default: false)
        //          ),
        //      ),
        //  );
        //
        // NOTE that <role_dn> may in fact be something like: 'cn=role,%dc'

        $role_settings = $rcmail->config->get('kolab_auth_role_settings');

        if (!empty($role_plugins)) {
            foreach ($role_plugins as $role_dn => $plugins) {
                $role_dn = self::parse_ldap_vars($role_dn);
                if (!empty($role_plugins[$role_dn])) {
                    $role_plugins[$role_dn] = array_unique(array_merge((array)$role_plugins[$role_dn], $plugins));
                } else {
                    $role_plugins[$role_dn] = $plugins;
                }
            }
        }

        if (!empty($role_settings)) {
            foreach ($role_settings as $role_dn => $settings) {
                $role_dn = self::parse_ldap_vars($role_dn);
                if (!empty($role_settings[$role_dn])) {
                    $role_settings[$role_dn] = array_merge((array)$role_settings[$role_dn], $settings);
                } else {
                    $role_settings[$role_dn] = $settings;
                }
            }
        }

        foreach ($_SESSION['user_roledns'] as $role_dn) {
            if (!empty($role_settings[$role_dn]) && is_array($role_settings[$role_dn])) {
                foreach ($role_settings[$role_dn] as $setting_name => $setting) {
                    if (!isset($setting['mode'])) {
                        $setting['mode'] = 'override';
                    }

                    if ($setting['mode'] == "override") {
                        $rcmail->config->set($setting_name, $setting['value']);
                    } elseif ($setting['mode'] == "merge") {
                        $orig_setting = $rcmail->config->get($setting_name);

                        if (!empty($orig_setting)) {
                            if (is_array($orig_setting)) {
                                $rcmail->config->set($setting_name, array_merge($orig_setting, $setting['value']));
                            }
                        } else {
                            $rcmail->config->set($setting_name, $setting['value']);
                        }
                    }

                    $dont_override = (array) $rcmail->config->get('dont_override');

                    if (empty($setting['allow_override'])) {
                        $rcmail->config->set('dont_override', array_merge($dont_override, array($setting_name)));
                    }
                    else {
                        if (in_array($setting_name, $dont_override)) {
                            $_dont_override = array();
                            foreach ($dont_override as $_setting) {
                                if ($_setting != $setting_name) {
                                    $_dont_override[] = $_setting;
                                }
                            }
                            $rcmail->config->set('dont_override', $_dont_override);
                        }
                    }

                    if ($setting_name == 'skin') {
                        if ($rcmail->output->type == 'html') {
                            $rcmail->output->set_skin($setting['value']);
                            $rcmail->output->set_env('skin', $setting['value']);
                        }
                    }
                }
            }

            if (!empty($role_plugins[$role_dn])) {
                foreach ((array)$role_plugins[$role_dn] as $plugin) {
                    $loaded = $this->api->load_plugin($plugin);

                    // Some plugins e.g. kolab_2fa use 'startup' hook to
                    // register other hooks, but when called on 'authenticate' hook
                    // we're already after 'startup', so we'll call it directly
                    if ($loaded && $startup && $plugin == 'kolab_2fa'
                        && ($plugin = $this->api->get_plugin($plugin))
                    ) {
                        $plugin->startup(array('task' => $rcmail->task, 'action' => $rcmail->action));
                    }
                }
            }
        }
    }

    /**
     * Logging method replacement to print debug/errors into
     * a separate (sub)folder for each user
     */
    public function write_log($args)
    {
        $rcmail = rcube::get_instance();

        if ($rcmail->config->get('log_driver') == 'syslog') {
            return $args;
        }

        // log_driver == 'file' is assumed here
        $log_dir  = $rcmail->config->get('log_dir', RCUBE_INSTALL_PATH . 'logs');

        // Append original username + target username for audit-logging
        if ($rcmail->config->get('kolab_auth_auditlog') && !empty($_SESSION['kolab_auth_admin'])) {
            $args['dir'] = $log_dir . '/' . strtolower($_SESSION['kolab_auth_admin']) . '/' . strtolower($this->username);

            // Attempt to create the directory
            if (!is_dir($args['dir'])) {
                @mkdir($args['dir'], 0750, true);
            }
        }
        // Define the user log directory if a username is provided
        else if ($rcmail->config->get('per_user_logging') && !empty($this->username)) {
            $user_log_dir = $log_dir . '/' . strtolower($this->username);
            if (is_writable($user_log_dir)) {
                $args['dir'] = $user_log_dir;
            }
            else if (!in_array($args['name'], array('errors', 'userlogins', 'sendmail'))) {
                $args['abort'] = true;  // don't log if unauthenticed or no per-user log dir
            }
        }

        return $args;
    }

    /**
     * Sets defaults for new user.
     */
    public function user_create($args)
    {
        if (!empty($this->data['user_email'])) {
            // addresses list is supported
            if (array_key_exists('email_list', $args)) {
                $email_list = array_unique($this->data['user_email']);

                // add organization to the list
                if (!empty($this->data['user_organization'])) {
                    foreach ($email_list as $idx => $email) {
                        $email_list[$idx] = array(
                            'organization' => $this->data['user_organization'],
                            'email'        => $email,
                        );
                    }
                }

                $args['email_list'] = $email_list;
            }
            else {
                $args['user_email'] = $this->data['user_email'][0];
            }
        }

        if (!empty($this->data['user_name'])) {
            $args['user_name'] = $this->data['user_name'];
        }

        return $args;
    }

    /**
     * Modifies login form adding additional "Login As" field
     */
    public function login_form($args)
    {
        $this->add_texts('localization/');

        $rcmail      = rcube::get_instance();
        $admin_login = $rcmail->config->get('kolab_auth_admin_login');
        $group       = $rcmail->config->get('kolab_auth_group');
        $role_attr   = $rcmail->config->get('kolab_auth_role');

        // Show "Login As" input
        if (empty($admin_login) || (empty($group) && empty($role_attr))) {
            return $args;
        }

        $input = new html_inputfield(array('name' => '_loginas', 'id' => 'rcmloginas',
            'type' => 'text', 'autocomplete' => 'off'));
        $row = html::tag('tr', null,
            html::tag('td', 'title', html::label('rcmloginas', rcube::Q($this->gettext('loginas'))))
            . html::tag('td', 'input', $input->show(trim(rcube_utils::get_input_value('_loginas', rcube_utils::INPUT_POST))))
        );
        $args['content'] = preg_replace('/<\/tbody>/i', $row . '</tbody>', $args['content']);

        return $args;
    }

    /**
     * Find user credentials In LDAP.
     */
    public function authenticate($args)
    {
        // get username and host
        $host    = $args['host'];
        $user    = $args['user'];
        $pass    = $args['pass'];
        $loginas = trim(rcube_utils::get_input_value('_loginas', rcube_utils::INPUT_POST));

        if (empty($user) || (empty($pass) && empty($_SERVER['REMOTE_USER']))) {
            $args['abort'] = true;
            return $args;
        }

        // temporarily set the current username to the one submitted
        $this->username = $user;

        $ldap = self::ldap();
        if (!$ldap || !$ldap->ready) {
            self::log_login_error($user, "LDAP not ready");

            $args['abort']            = true;
            $args['kolab_ldap_error'] = true;

            return $args;
        }

        // Find user record in LDAP
        $record = $ldap->get_user_record($user, $host);

        if (empty($record)) {
            self::log_login_error($user, "No user record found");

            $args['abort'] = true;

            return $args;
        }

        $rcmail      = rcube::get_instance();
        $admin_login = $rcmail->config->get('kolab_auth_admin_login');
        $admin_pass  = $rcmail->config->get('kolab_auth_admin_password');
        $login_attr  = $rcmail->config->get('kolab_auth_login');
        $name_attr   = $rcmail->config->get('kolab_auth_name');
        $email_attr  = $rcmail->config->get('kolab_auth_email');
        $org_attr    = $rcmail->config->get('kolab_auth_organization');
        $role_attr   = $rcmail->config->get('kolab_auth_role');
        $imap_attr   = $rcmail->config->get('kolab_auth_mailhost');

        if (!empty($role_attr) && !empty($record[$role_attr])) {
            $_SESSION['user_roledns'] = (array)($record[$role_attr]);
        }

        if (!empty($imap_attr) && !empty($record[$imap_attr])) {
            $default_host = $rcmail->config->get('default_host');
            if (!empty($default_host)) {
                rcube::write_log("errors", "Both default host and kolab_auth_mailhost set. Incompatible.");
            } else {
                $args['host'] = "tls://" . $record[$imap_attr];
            }
        }

        // Login As...
        if (!empty($loginas) && $admin_login) {
            // Authenticate to LDAP
            $result = $ldap->bind($record['dn'], $pass);

            if (!$result) {
                self::log_login_error($user, "Unable to bind with '" . $record['dn'] . "'");

                $args['abort'] = true;

                return $args;
            }

            $isadmin = false;
            $admin_rights = $rcmail->config->get('kolab_auth_admin_rights', array());

            // @deprecated: fall-back to the old check if the original user has/belongs to administrative role/group
            if (empty($admin_rights)) {
                $group   = $rcmail->config->get('kolab_auth_group');
                $role_dn = $rcmail->config->get('kolab_auth_role_value');

                // check role attribute
                if (!empty($role_attr) && !empty($role_dn) && !empty($record[$role_attr])) {
                    $role_dn = $ldap->parse_vars($role_dn, $user, $host);
                    if (in_array($role_dn, (array)$record[$role_attr])) {
                        $isadmin = true;
                    }
                }

                // check group
                if (!$isadmin && !empty($group)) {
                    $groups = $ldap->get_user_groups($record['dn'], $user, $host);
                    if (in_array($group, $groups)) {
                        $isadmin = true;
                    }
                }

                if ($isadmin) {
                    // user has admin privileges privilage, get "login as" user credentials
                    $target_entry = $ldap->get_user_record($loginas, $host);
                    $allowed_tasks = $rcmail->config->get('kolab_auth_allowed_tasks');
                }
            }
            else {
                // get "login as" user credentials
                $target_entry = $ldap->get_user_record($loginas, $host);

                if (!empty($target_entry)) {
                    // get effective rights to determine login-as permissions
                    $effective_rights = (array)$ldap->effective_rights($target_entry['dn']);

                    if (!empty($effective_rights)) {
                        $effective_rights['attrib'] = $effective_rights['attributeLevelRights'];
                        $effective_rights['entry']  = $effective_rights['entryLevelRights'];

                        // compare the rights with the permissions mapping
                        $allowed_tasks = array();
                        foreach ($admin_rights as $task => $perms) {
                            $perms_ = explode(':', $perms);
                            $type   = array_shift($perms_);
                            $req    = array_pop($perms_);
                            $attrib = array_pop($perms_);

                            if (array_key_exists($type, $effective_rights)) {
                                if ($type == 'entry' && in_array($req, $effective_rights[$type])) {
                                    $allowed_tasks[] = $task;
                                }
                                else if ($type == 'attrib' && array_key_exists($attrib, $effective_rights[$type]) &&
                                        in_array($req, $effective_rights[$type][$attrib])) {
                                    $allowed_tasks[] = $task;
                                }
                            }
                        }

                        $isadmin = !empty($allowed_tasks);
                    }
                }
            }

            // Save original user login for log (see below)
            if ($login_attr) {
                $origname = is_array($record[$login_attr]) ? $record[$login_attr][0] : $record[$login_attr];
            }
            else {
                $origname = $user;
            }

            if (!$isadmin || empty($target_entry)) {
                $this->add_texts('localization/');

                $args['abort'] = true;
                $args['error'] = $this->gettext(array(
                    'name' => 'loginasnotallowed',
                    'vars' => array('user' => rcube::Q($loginas)),
                ));

                self::log_login_error($user, "No privileges to login as '" . $loginas . "'", $loginas);

                return $args;
            }

            // replace $record with target entry
            $record = $target_entry;

            $args['user'] = $this->username = $loginas;

            // Mark session to use SASL proxy for IMAP authentication
            $_SESSION['kolab_auth_admin']    = strtolower($origname);
            $_SESSION['kolab_auth_login']    = $rcmail->encrypt($admin_login);
            $_SESSION['kolab_auth_password'] = $rcmail->encrypt($admin_pass);
            $_SESSION['kolab_auth_allowed_tasks'] = $allowed_tasks;
        }

        // Store UID and DN of logged user in session for use by other plugins
        $_SESSION['kolab_uid'] = is_array($record['uid']) ? $record['uid'][0] : $record['uid'];
        $_SESSION['kolab_dn']  = $record['dn'];

        // Store LDAP replacement variables used for current user
        // This improves performance of load_user_role_plugins_and_settings()
        // which is executed on every request (via startup hook) and where
        // we don't like to use LDAP (connection + bind + search)
        $_SESSION['kolab_auth_vars'] = $ldap->get_parse_vars();

        // Store user unique identifier for freebusy_session_auth feature
        $_SESSION['kolab_auth_uniqueid'] = is_array($record['uniqueid']) ? $record['uniqueid'][0] : $record['uniqueid'];

        // Store also host as we need it for get_user_reacod() in 'ready' hook handler
        $_SESSION['kolab_host'] = $host;

        // Set user login
        if ($login_attr) {
            $this->data['user_login'] = is_array($record[$login_attr]) ? $record[$login_attr][0] : $record[$login_attr];
        }
        if ($this->data['user_login']) {
            $args['user'] = $this->username = $this->data['user_login'];
        }

        // User name for identity (first log in)
        foreach ((array)$name_attr as $field) {
            $name = is_array($record[$field]) ? $record[$field][0] : $record[$field];
            if (!empty($name)) {
                $this->data['user_name'] = $name;
                break;
            }
        }
        // User email(s) for identity (first log in)
        foreach ((array)$email_attr as $field) {
            $email = is_array($record[$field]) ? array_filter($record[$field]) : $record[$field];
            if (!empty($email)) {
                $this->data['user_email'] = array_merge((array)$this->data['user_email'], (array)$email);
            }
        }
        // Organization name for identity (first log in)
        foreach ((array)$org_attr as $field) {
            $organization = is_array($record[$field]) ? $record[$field][0] : $record[$field];
            if (!empty($organization)) {
                $this->data['user_organization'] = $organization;
                break;
            }
        }

        // Log "Login As" usage
        if (!empty($origname)) {
            rcube::write_log('userlogins', sprintf('Admin login for %s by %s from %s',
                $args['user'], $origname, rcube_utils::remote_ip()));
        }

        // load per-user settings/plugins
        $this->load_user_role_plugins_and_settings(true);

        return $args;
    }

    /**
     * Set user DN for password change (password plugin with ldap_simple driver)
     */
    public function password_ldap_bind($args)
    {
        $args['user_dn'] = $_SESSION['kolab_dn'];

        $rcmail = rcube::get_instance();

        $rcmail->config->set('password_ldap_method', 'user');

        return $args;
    }

    /**
     * Sets SASL Proxy login/password for IMAP and Managesieve auth
     */
    public function imap_connect($args)
    {
        if (!empty($_SESSION['kolab_auth_admin'])) {
            $rcmail      = rcube::get_instance();
            $admin_login = $rcmail->decrypt($_SESSION['kolab_auth_login']);
            $admin_pass  = $rcmail->decrypt($_SESSION['kolab_auth_password']);

            $args['auth_cid'] = $admin_login;
            $args['auth_pw']  = $admin_pass;
        }

        return $args;
    }

    /**
     * Sets SASL Proxy login/password for SMTP auth
     */
    public function smtp_connect($args)
    {
        if (!empty($_SESSION['kolab_auth_admin'])) {
            $rcmail      = rcube::get_instance();
            $admin_login = $rcmail->decrypt($_SESSION['kolab_auth_login']);
            $admin_pass  = $rcmail->decrypt($_SESSION['kolab_auth_password']);

            $args['smtp_auth_cid'] = $admin_login;
            $args['smtp_auth_pw']  = $admin_pass;
        }

        return $args;
    }

    /**
     * Hook to replace the plain text input field for email address by a drop-down list
     * with all email addresses (including aliases) from this user's LDAP record.
     */
    public function identity_form($args)
    {
        $rcmail      = rcube::get_instance();
        $ident_level = intval($rcmail->config->get('identities_level', 0));

        // do nothing if email address modification is disabled
        if ($ident_level == 1 || $ident_level == 3) {
            return $args;
        }

        $ldap = self::ldap();
        if (!$ldap || !$ldap->ready || empty($_SESSION['kolab_dn'])) {
            return $args;
        }

        $emails      = array();
        $user_record = $ldap->get_record($_SESSION['kolab_dn']);

        foreach ((array)$rcmail->config->get('kolab_auth_email', array()) as $col) {
            $values = rcube_addressbook::get_col_values($col, $user_record, true);
            if (!empty($values))
                $emails = array_merge($emails, array_filter($values));
        }

        // kolab_delegation might want to modify this addresses list
        $plugin = $rcmail->plugins->exec_hook('kolab_auth_emails', array('emails' => $emails));
        $emails = $plugin['emails'];

        if (!empty($emails)) {
            $args['form']['addressing']['content']['email'] = array(
                'type' => 'select',
                'options' => array_combine($emails, $emails),
            );
        }

        return $args;
    }

    /**
     * Action executed before the page is rendered to add an onload script
     * that will remove all taskbar buttons for disabled tasks
     */
    public function render_page($args)
    {
        $rcmail  = rcube::get_instance();
        $tasks   = (array)$_SESSION['kolab_auth_allowed_tasks'];
        $tasks[] = 'logout';

        // disable buttons in taskbar
        $script = "
        \$('a').filter(function() {
            var ev = \$(this).attr('onclick');
            return ev && ev.match(/'switch-task','([a-z]+)'/)
                && \$.inArray(RegExp.\$1, " . json_encode($tasks) . ") < 0;
        }).remove();
        ";

        $rcmail->output->add_script($script, 'docready');
    }

    /**
     * Initializes LDAP object and connects to LDAP server
     */
    public static function ldap()
    {
        if (self::$ldap) {
            return self::$ldap;
        }

        $rcmail      = rcube::get_instance();
        $addressbook = $rcmail->config->get('kolab_auth_addressbook');

        if (!is_array($addressbook)) {
            $ldap_config = (array)$rcmail->config->get('ldap_public');
            $addressbook = $ldap_config[$addressbook];
        }

        if (empty($addressbook)) {
            return null;
        }

        $addressbook['fieldmap']['uniqueid'] = 'nsuniqueid';

        require_once __DIR__ . '/kolab_auth_ldap.php';

        self::$ldap = new kolab_auth_ldap($addressbook);

        return self::$ldap;
    }

    /**
     * Close LDAP connection
     */
    public static function ldap_close()
    {
        if (self::$ldap) {
            self::$ldap->close();
            self::$ldap = null;
        }
    }

    /**
     * Parses LDAP DN string with replacing supported variables.
     * See kolab_auth_ldap::parse_vars()
     *
     * @param string $str LDAP DN string
     *
     * @return string Parsed DN string
     */
    public static function parse_ldap_vars($str)
    {
        if (!empty($_SESSION['kolab_auth_vars'])) {
            $str = strtr($str, $_SESSION['kolab_auth_vars']);
        }

        return $str;
    }

    /**
     * Log failed logins
     *
     * @param string $username Username/Login
     * @param string $message  Error message (failure reason)
     * @param string $login_as Username/Login of "login as" user
     */
    public static function log_login_error($username, $message = null, $login_as = null)
    {
        $config = rcube::get_instance()->config;

        if ($config->get('log_logins')) {
            // don't fill the log with complete input, which could
            // have been prepared by a hacker
            if (strlen($username) > 256) {
                $username = substr($username, 0, 256) . '...';
            }
            if (strlen($login_as) > 256) {
                $login_as = substr($login_as, 0, 256) . '...';
            }

            if ($login_as) {
                $username = sprintf('%s (as user %s)', $username, $login_as);
            }

            $message = sprintf(
                "Failed login for %s from %s in session %s %s",
                $username,
                rcube_utils::remote_ip(),
                session_id() ?: 'no-session',
                $message ? "($message)" : ''
            );

            rcube::write_log('userlogins', $message);

            // disable log_logins to prevent from duplicate log entries
            $config->set('log_logins', false);
        }
    }
}
