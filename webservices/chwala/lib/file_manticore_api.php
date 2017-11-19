<?php
/**
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2011-2015, Kolab Systems AG                                |
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
 * Helper class to connect to the Manticore API
 */
class file_manticore_api
{
    /**
     * @var HTTP_Request2
     */
    private $request;

    /**
     * @var string
     */
    private $base_url;

    /**
     * @var bool
     */
    private $debug = false;

    const ERROR_INTERNAL   = 100;
    const ERROR_CONNECTION = 500;

    const ACCEPT_HEADER = "application/json,text/javascript,*/*";

    const ACCESS_WRITE = 'write';
    const ACCESS_READ  = 'read';
    const ACCESS_DENY  = 'deny';


    /**
     * Class constructor.
     *
     * @param string $base_url Base URL of the Kolab API
     */
    public function __construct($base_url)
    {
        require_once 'HTTP/Request2.php';

        $config         = rcube::get_instance()->config;
        $this->debug    = rcube_utils::get_boolean($config->get('fileapi_manticore_debug'));
        $this->base_url = rtrim($base_url, '/') . '/';
        $this->request  = new HTTP_Request2();

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
                rcube::log_error("HTTP: " . $e->getMessage());
            }
        }

        // proxy User-Agent
        $request->setHeader('user-agent', $_SERVER['HTTP_USER_AGENT']);

        // some HTTP server configurations require this header
        $request->setHeader('accept', self::ACCEPT_HEADER);

        $request->setHeader('Content-Type', 'application/json; charset=UTF-8');
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
     *
     * @return string Session token (on success)
     */
    public function login($username, $password)
    {
        $query = array(
            'email'    => $username,
            'password' => $password,
        );

        // remove current token if any
        $this->request->setHeader('Authorization');

        // authenticate the user
        $response = $this->post('auth/local', $query);

        if ($token = $response->get('token')) {
            $this->set_session_token($token);
        }

        return $token;
    }

    /**
     * Sets request session token.
     *
     * @param string $token    Session token.
     * @param bool   $validate Enables token validatity check
     *
     * @return bool Token validity status
     */
    public function set_session_token($token, $validate = false)
    {
        $this->request->setHeader('Authorization', "Bearer $token");

        if ($validate) {
            $result = $this->get('api/users/me');

            return $result->get_error_code() == 200;
        }

        return true;
    }

    /**
     * Delete document editing session
     *
     * @param array $id Session identifier
     *
     * @return bool True on success, False on failure
     */
    public function document_delete($id)
    {
        $res = $this->delete('api/documents/' . $id);

        return $res->get_error_code() == 204;
    }

    /**
     * Create document editing session
     *
     * @param array $params Session parameters
     *
     * @return bool True on success, False on failure
     */
    public function document_create($params)
    {
        $res = $this->post('api/documents', $params);

        // @FIXME: 422?
        return $res->get_error_code() == 201 || $res->get_error_code() == 422;
    }

    /**
     * Add document editor (update 'access' array)
     *
     * @param array $session_id Session identifier
     * @param array $identity   User identifier
     *
     * @return bool True on success, False on failure
     */
    public function editor_add($session_id, $identity, $permission)
    {
        $res = $this->get("api/documents/$session_id/access");

        if ($res->get_error_code() != 200) {
            return false;
        }

        $access = $res->get();

        // sanity check, this should never be empty
        if (empty($access)) {
            return false;
        }

        // add editor to the 'access' array
        foreach ($access as $entry) {
            if ($entry['identity'] == $identity) {
                return true;
            }
        }

        $access[] = array('identity' => $identity, 'permission' => $permission);

        $res = $this->put("api/documents/$session_id/access", $access);

        return $res->get_error_code() == 200;
    }

    /**
     * Remove document editor (update 'access' array)
     *
     * @param array $session_id Session identifier
     * @param array $identity   User identifier
     *
     * @return bool True on success, False on failure
     */
    public function editor_delete($session_id, $identity)
    {
        $res = $this->get("api/documents/$session_id/access");

        if ($res->get_error_code() != 200) {
            return false;
        }

        $access = $res->get();
        $found  = true;

        // remove editor from the 'access' array
        foreach ((array) $access as $idx => $entry) {
            if ($entry['identity'] == $identity) {
                unset($access[$idx]);
            }
        }

        if (!$found) {
            return false;
        }

        $res = $this->put("api/documents/$session_id/access", $access);

        return $res->get_error_code() == 200;
    }

    /**
     * API's GET request.
     *
     * @param string $action Action name
     * @param array  $get    Request arguments
     *
     * @return file_ui_api_result Response
     */
    public function get($action, $get = array())
    {
        $url = $this->build_url($action, $get);

        if ($this->debug) {
            rcube::write_log('manticore', "GET: $url " . json_encode($get));
        }

        $this->request->setMethod(HTTP_Request2::METHOD_GET);
        $this->request->setBody('');

        return $this->get_response($url);
    }

    /**
     * API's POST request.
     *
     * @param string $action Action name
     * @param array  $post   POST arguments
     *
     * @return kolab_client_api_result Response
     */
    public function post($action, $post = array())
    {
        $url = $this->build_url($action);

        if ($this->debug) {
            rcube::write_log('manticore', "POST: $url " . json_encode($post));
        }

        $this->request->setMethod(HTTP_Request2::METHOD_POST);
        $this->request->setBody(json_encode($post));

        return $this->get_response($url);
    }

    /**
     * API's PUT request.
     *
     * @param string $action Action name
     * @param array  $post   POST arguments
     *
     * @return kolab_client_api_result Response
     */
    public function put($action, $post = array())
    {
        $url = $this->build_url($action);

        if ($this->debug) {
            rcube::write_log('manticore', "PUT: $url " . json_encode($post));
        }

        $this->request->setMethod(HTTP_Request2::METHOD_PUT);
        $this->request->setBody(json_encode($post));

        return $this->get_response($url);
    }

    /**
     * API's DELETE request.
     *
     * @param string $action Action name
     * @param array  $get    Request arguments
     *
     * @return file_ui_api_result Response
     */
    public function delete($action, $get = array())
    {
        $url = $this->build_url($action, $get);

        if ($this->debug) {
            rcube::write_log('manticore', "DELETE: $url " . json_encode($get));
        }

        $this->request->setMethod(HTTP_Request2::METHOD_DELETE);
        $this->request->setBody('');

        return $this->get_response($url);
    }

    /**
     * @param string $action Action GET parameter
     * @param array  $args   GET parameters (hash array: name => value)
     *
     * @return Net_URL2 URL object
     */
    private function build_url($action, $args = array())
    {
        $url = new Net_URL2($this->base_url . $action);

        $url->setQueryVariables((array) $args);

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

        $code = $response->getStatus();

        if ($this->debug) {
            rcube::write_log('manticore', "Response [$code]: $body");
        }

        if ($code < 300) {
            $result = $body ? json_decode($body, true) : array();
        }
        else {
            if ($code != 401) {
                rcube::raise_error("Error $code on $url", true, false);
            }

            $error = $body;
        }

        return new file_ui_api_result($result, $code, $error);
    }
}
