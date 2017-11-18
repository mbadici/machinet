<?php

/**
 * WAP Client plugin.
 *
 * @version @package_version@
 * @author Aleksander Machniak <machniak@kolabsys.com>
 *
 * Copyright (C) 2016, Kolab Systems AG <contact@kolabsys.com>
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

class wap_client extends rcube_plugin
{
    public $task   = 'settings';
    public $noajax = true;

    protected $rc;
    protected $wap;
    protected $userinfo;
    protected $token;


    /**
     * Initializes the plugin
     */
    function init()
    {
        $this->rc = rcmail::get_instance();

        $this->add_hook('preferences_list', array($this, 'prefs_table'));
        $this->add_hook('preferences_save', array($this, 'save_prefs'));
    }

    /**
     * Hook to inject plugin-specific user settings
     */
    public function prefs_table($args)
    {
        global $CURR_SECTION;

        if ($args['section'] != 'server') {
            return;
        }

        $this->load_config();

        $accounts = (array) $this->rc->config->get('wap_client_accounts');

        if (empty($accounts)) {
            return;
        }

        $this->add_texts('localization');

        if ($CURR_SECTION) {
            $account_type = $this->get_account_type();
            $_SESSION['wap_client_account_type'] = $account_type;
        }

        $input   = new html_radiobutton(array('name' => '_account_type', 'style' => 'display:block; float:left'));
        $content = '';

        foreach ($accounts as $idx => $def) {
            $id   = 'account_type_' . strtolower(asciiwords($idx, true));
            $name = $idx;
            $name = $this->rc->text_exists('wap_client.account.' . $name) ? $this->gettext('account.' . $name) : $name;
            $desc = $this->rc->text_exists('wap_client.accountdesc.' . $name) ? $this->gettext('accountdesc.' . $name) : $def['description'];

            $name = html::span(array('style' => 'font-weight: bold'), rcube::Q($name));
            if ($desc) {
                $name .= html::br() . html::span(null, rcube::Q($desc));
            }

            $label_style = 'display:block; margin: 5px 0; padding-left: 30px';
            $content .= $input->show($account_type, array('value' => $idx, 'id' => $id))
                . html::label(array('for' => $id, 'style' => $label_style), $name);
        }

        $conf = array(
            'account' => array(
                'name'    => rcube::Q($this->gettext('accountoptions')),
                'options' => array(
                    'account_type' => array(
                        'title'   => $this->gettext('accounttype'),
                        'content' => $content,
                    )
                )
            )
        );

        $args['blocks'] = array_merge($conf, $args['blocks']);

        return $args;
    }

    /**
     * Hook to save plugin-specific user settings
     */
    public function save_prefs($args)
    {
        if ($args['section'] != 'server') {
            return;
        }

        $account_type = rcube_utils::get_input_value('_account_type', rcube_utils::INPUT_POST);

        if (!$account_type || $account_type == $_SESSION['wap_client_account_type']) {
            return;
        }

        $this->add_texts('localization');

        $this->set_account_type($account_type);
    }

    /**
     * Get current account type (from WAP)
     */
    protected function get_account_type()
    {
        $this->init_wap();

        if (empty($this->userinfo)) {
            $this->rc->output->show_message($this->gettext('failedtypedetection'), 'warning');
            return;
        }

        $roles    = (array) $this->userinfo['nsroledn'];
        $accounts = (array) $this->rc->config->get('wap_client_accounts');
        $root_dn  = $this->rc->config->get('wap_client_root_dn');
        $base_dn  = $this->rc->config->get('wap_client_base_dn');

        foreach ($accounts as $name => $account) {
            foreach ((array) $account['nsroledn'] as $role) {
                $value = str_replace('$base_dn', $base_dn, $value);
                $value = str_replace('$root_dn', $root_dn, $value);

                if (!in_array($value, $roles)) {
                    continue 2;
                }
            }

            return $name;
        }
    }

    /**
     * Set account type (in WAP)
     */
    protected function set_account_type($type)
    {
        if (!$this->init_wap()) {
            return false;
        }

        $query    = $this->userinfo;
        $accounts = (array) $this->rc->config->get('wap_client_accounts');
        $root_dn  = $this->rc->config->get('wap_client_root_dn');
        $base_dn  = $this->rc->config->get('wap_client_base_dn');
        $account  = $accounts[$type];

        if (empty($account)) {
            $this->rc->output->show_message($this->gettext('failedtypeupdate'), 'warning');
            return;
        }

        unset($account['description']);

        foreach ($account as $attr => $value) {
            switch ($attr) {
            case 'nsroledn':
                $value = array();
                foreach ((array) $account['nsroledn'] as $role) {
                    $role = str_replace('$base_dn', $base_dn, $role);
                    $role = str_replace('$root_dn', $root_dn, $role);
                    $value[] = $role;
                }

            default:
                $query[$attr] = $value;
            }
        }

        $response = $this->post('user.edit', $query);

        if (!$response || $response['status'] != 'OK') {
            $this->rc->output->show_message($this->gettext('failedtypeupdate'), 'warning');
            return;
        }

        $this->userinfo = $query;
    }

    /**
     * Initialize WAP connection and user session
     */
    protected function init_wap()
    {
        if ($this->wap) {
            return $this->wap;
        }

        $this->load_config();
        $this->require_plugin('libkolab');

        $uri  = $this->rc->config->get('wap_client_uri');
        $user = $this->rc->get_user_name();
        $pass = $this->rc->decrypt($_SESSION['password']);

        if (!$uri) {
            rcube::raise_error("wap_client_uri is not set", true, false);
            return;
        }

        // get HTTP_Request2 object
        $this->uri = rcube_utils::resolve_url($uri);
        $this->wap = libkolab::http_request($this->uri);

        $query = array(
            'username' => $user,
            'password' => $pass,
        //    'domain'   => $domain,
            'info'     => true,
        );

        // authenticate the user
        $response = $this->post('system.authenticate', $query);

        if ($response) {
            $this->userinfo = $response['result']['info'];
            $this->token    = $response['result']['session_token'];
        }

        return $this->wap;
    }

    /**
     * API's POST request.
     *
     * @param string $action Action name
     * @param array  $post   POST arguments
     *
     * @return kolab_client_api_result  Response
     */
    protected function post($action, $post = array())
    {
        $url = $this->build_url($action);

        if ($this->rc->config->get('wap_client_debug')) {
            $this->rc->write_log('wap', "Calling API POST: $url\n" . @json_encode($post));
        }

        if ($this->token) {
            $this->wap->setHeader('X-Session-Token', $this->token);
        }

        $this->wap->setMethod(HTTP_Request2::METHOD_POST);
        $this->wap->setBody(@json_encode($post));

        return $this->get_response($url);
    }

    /**
     * Build Net_URL2 object for the request
     *
     * @param string $action Action GET parameter
     * @param array  $args   GET parameters (hash array: name => value)
     *
     * @return Net_URL2 URL object
     */
    private function build_url($action, $args = array())
    {
        $url = rtrim($this->uri, '/');

        if ($action) {
            $url .= '/' . urlencode($action);
        }

        $url = new Net_URL2($url);

        if (!empty($args)) {
            $url->setQueryVariables($args);
        }

        return $url;
    }

    /**
     * HTTP Response handler.
     *
     * @param Net_URL2 $url URL object
     *
     * @return array Response data
     */
    protected function get_response($url)
    {
        try {
            $this->wap->setUrl($url);
            $response = $this->wap->send();
        }
        catch (Exception $e) {
            rcube::raise_error($e, true, false);
            return;
        }

        try {
            $body = $response->getBody();
        }
        catch (Exception $e) {
            rcube::raise_error($e, true, false);
            return;
        }

        if ($this->rc->config->get('wap_client_debug')) {
            $this->rc->write_log('wap', "Response:\n$body");
        }

        $body = @json_decode($body, true);

        if (!is_array($body)) {
            rcube::raise_error("Failed to decode WAP response", true, false);
            return;
        }

        return $body;
    }
}
