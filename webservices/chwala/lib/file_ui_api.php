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
 +--------------------------------------------------------------------------+
*/

/**
 * Helper class to connect to the API
 */
class file_ui_api
{
    /**
     * @var HTTP_Request2
     */
    private $request;

    /**
     * @var string
     */
    private $base_url;

    const ERROR_INTERNAL   = 100;
    const ERROR_CONNECTION = 200;

    const ACCEPT_HEADER = "application/json,text/javascript,*/*";

    /**
     * Class constructor.
     *
     * @param string $base_url Base URL of the Kolab API
     */
    public function __construct($base_url)
    {
        $this->base_url = $base_url;
        $this->init();
    }

    /**
     * Initializes HTTP Request object.
     */
    public function init()
    {
        require_once 'HTTP/Request2.php';
        $this->request = new HTTP_Request2();
        self::configure($this->request);
    }

    /**
     * Configure HTTP_Request2 object
     *
     * @param HTTP_Request2 $request Request object
     */
    public static function configure($request)
    {
        // Configure connection options
        $config      = rcube::get_instance()->config;
        $http_config = (array) $config->get('http_request', $config->get('kolab_http_request'));

        // Deprecated config, all options are separated variables
        if (empty($http_config)) {
            $options = array(
                'ssl_verify_peer',
                'ssl_verify_host',
                'ssl_cafile',
                'ssl_capath',
                'ssl_local_cert',
                'ssl_passphrase',
                'follow_redirects',
            );

            foreach ($options as $optname) {
                if (($optvalue = $config->get($optname)) !== null
                    || ($optvalue = $config->get('kolab_' . $optname)) !== null
                ) {
                    $http_config[$optname] = $optvalue;
                }
            }
        }

        if (!empty($http_config)) {
            try {
                $request->setConfig($http_config);
            }
            catch (Exception $e) {
//                rcube::log_error("HTTP: " . $e->getMessage());
            }
        }

        // proxy User-Agent
        $request->setHeader('user-agent', $_SERVER['HTTP_USER_AGENT']);

        // some HTTP server configurations require this header
        $request->setHeader('accept', self::ACCEPT_HEADER);
    }

    /**
     * Return API's base URL
     *
     * @return string Base URL
     */
    public function base_url()
    {
        return $this->base_url;
    }

    /**
     * Return HTTP_Request2 object
     *
     * @return HTTP_Request2 Request object
     */
    public function request()
    {
        return $this->request;
    }

    /**
     * Logs specified user into the API
     *
     * @param string $username User name
     * @param string $password User password
     * @param array  $get      Additional GET parameters (e.g. 'version')
     *
     * @return file_ui_api_result Request response
     */
    public function login($username, $password, $get = null)
    {
        $query = array(
            'username' => $username,
            'password' => $password,
        );

        $response = $this->post('authenticate', $get, $query);

        return $response;
    }

    /**
     * Logs specified user out of the API
     *
     * @return bool True on success, False on failure
     */
    public function logout()
    {
        $response = $this->get('quit');

        return $response->get_error_code() ? false : true;
    }

    /**
     * Sets session token value.
     *
     * @param string $token  Token string
     */
    public function set_session_token($token)
    {
        $this->request->setHeader('X-Session-Token', $token);
    }

    /**
     * Gets capabilities of the API (according to logged in user).
     *
     * @return kolab_client_api_result  Capabilities response
     */
    public function get_capabilities()
    {
        $this->get('capabilities');
    }

    /**
     * API's GET request.
     *
     * @param string $action  Action name
     * @param array  $args    Request arguments
     *
     * @return file_ui_api_result  Response
     */
    public function get($action, $args = array())
    {
        $url = $this->build_url($action, $args);

//        Log::trace("Calling API GET: $url");

        $this->request->setMethod(HTTP_Request2::METHOD_GET);

        return $this->get_response($url);
    }

    /**
     * API's POST request.
     *
     * @param string $action    Action name
     * @param array  $url_args  URL arguments
     * @param array  $post      POST arguments
     *
     * @return kolab_client_api_result  Response
     */
    public function post($action, $url_args = array(), $post = array())
    {
        $url = $this->build_url($action, $url_args);

//        Log::trace("Calling API POST: $url");

        $this->request->setMethod(HTTP_Request2::METHOD_POST);
        $this->request->addPostParameter($post);

        return $this->get_response($url);
    }

    /**
     * @param string $action Action GET parameter
     * @param array  $args   GET parameters (hash array: name => value)
     *
     * @return Net_URL2 URL object
     */
    private function build_url($action, $args)
    {
        $url = new Net_URL2($this->base_url);

        $args['method'] = $action;

        $url->setQueryVariables($args);

        return $url;
    }

    /**
     * HTTP Response handler.
     *
     * @param Net_URL2 $url URL object
     *
     * @return kolab_client_api_result Response object
     */
    private function get_response($url)
    {
        try {
            $this->request->setUrl($url);
            $response = $this->request->send();
        }
        catch (Exception $e) {
            return new file_ui_api_result(null,
                self::ERROR_CONNECTION, $e->getMessage());
        }

        try {
            $body = $response->getBody();
        }
        catch (Exception $e) {
            return new file_ui_api_result(null,
                self::ERROR_INTERNAL, $e->getMessage());
        }

        $body     = @json_decode($body, true);
        $err_code = null;
        $err_str  = null;

        if (is_array($body) && (empty($body['status']) || $body['status'] != 'OK')) {
            $err_code = !empty($body['code']) ? $body['code'] : self::ERROR_INTERNAL;
            $err_str  = !empty($body['reason']) ? $body['reason'] : 'Unknown error';
        }
        else if (!is_array($body)) {
            $err_code = self::ERROR_INTERNAL;
            $err_str  = 'Unable to decode response';
        }

        if (!$err_code && array_key_exists('result', (array) $body)) {
            $body = $body['result'];
        }

        return new file_ui_api_result($body, $err_code, $err_str);
    }
}
