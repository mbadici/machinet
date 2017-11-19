<?php
/**
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2017, Kolab Systems AG                                |
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
 * Document editing sessions handling (WOPI)
 */
class file_wopi extends file_document
{
    protected $cache;

    // Mimetypes supported by CODE, but not advertised by all possible names
    protected $mimetype_aliases = array(
        'application/vnd.corel-draw' => 'image/x-coreldraw',
    );

    // Mimetypes supported by other Chwala viewers or ones we don't want to be editable
    protected $mimetype_exceptions = array(
        'text/plain',
        'image/bmp',
        'image/png',
        'image/jpeg',
        'image/jpg',
        'image/pjpeg',
        'image/gif',
        'image/tiff',
        'image/x-tiff',
    );

    /**
     * Return viewer URI for specified file/session. This creates
     * a new collaborative editing session when needed.
     *
     * @param string $file        File path
     * @param array  &$file_info  File metadata (e.g. type)
     * @param string &$session_id Optional session ID to join to
     * @param string $readonly    Create readonly (one-time) session
     *
     * @return string WOPI URI for specified document
     * @throws Exception
     */
    public function session_start($file, &$file_info, &$session_id = null, $readonly = false)
    {
        parent::session_start($file, $file_info, $session_id, $readonly);

        if ($session_id) {
            // Create Chwala session for use as WOPI access_token
            // This session will have access to this one document session only
            $keys = array('env', 'user_id', 'user', 'username', 'password',
                'storage_host', 'storage_port', 'storage_ssl');

            $data = array_intersect_key($_SESSION, array_flip($keys));
            $data['document_session'] = $session_id;

            $this->token = $this->api->session->create($data);

            $this->log_login($session_id);
        }

        return $this->frame_uri($session_id, $file_info['type']);
    }

    /**
     * Generate URI of WOPI editing session (WOPIsrc)
     */
    protected function frame_uri($id, $mimetype)
    {
        $capabilities = $this->capabilities();

        if (empty($capabilities) || empty($mimetype)) {
            return;
        }

        $metadata = $capabilities[strtolower($mimetype)];

        if (empty($metadata)) {
            return;
        }

        $office_url   = rtrim($metadata['urlsrc'], ' /?'); // collabora
        $service_url  = rtrim($this->rc->config->get('fileapi_wopi_service'), ' /'); // kolab-wopi
        $service_url .= '/wopi/files/' . $id;

        // @TODO: Parsing and replacing placeholder values
        // https://wopi.readthedocs.io/en/latest/discovery.html#action-urls

        $args = array('WOPISrc' => $service_url);

        // We could also set: title, closebutton, revisionhistory
        // @TODO: do it in editor_post_params() when supported by the editor
        if ($lang = $this->api->env['language']) {
            $args['lang'] = str_replace('_', '-', $lang);
        }

        return $office_url . '?' . http_build_query($args, '', '&');
    }

    /**
     * Retern extra viewer parameters to post to the viewer iframe
     *
     * @param array $info File info
     *
     * @return array POST parameters
     */
    public function editor_post_params($info)
    {
        // Access token TTL (number of milliseconds since January 1, 1970 UTC)
        if ($ttl = $this->rc->config->get('session_lifetime', 0) * 60) {
            $now = new DateTime('now', new DateTimeZone('UTC'));
            $ttl = ($ttl + $now->format('U')) . '000';
        }

        $params = array(
            'access_token'     => $this->token,
            'access_token_ttl' => $ttl ?: 0,
        );

        return $params;
    }

    /**
     * List supported mimetypes
     *
     * @param bool $editable Return only editable mimetypes
     *
     * @return array List of supported mimetypes
     */
    public function supported_filetypes($editable = false)
    {
        $caps = $this->capabilities();

        if ($editable) {
            $editable = array();
            foreach ($caps as $mimetype => $c) {
                if ($c['name'] == 'edit') {
                    $editable[] = $mimetype;
                }
            }

            return $editable;
        }

        return array_keys($caps);
    }

    /**
     * Uses WOPI discovery to get Office capabilities
     * https://wopi.readthedocs.io/en/latest/discovery.html
     */
    protected function capabilities()
    {
        $cache_key = 'wopi.capabilities';
        if ($result = $this->get_from_cache($cache_key)) {
            return $this->apply_aliases_and_exceptions($result);
        }

        $office_url  = rtrim($this->rc->config->get('fileapi_wopi_office'), ' /');
        $office_url .= '/hosting/discovery';

        try {
            $request = $this->http_request();
            $request->setMethod(HTTP_Request2::METHOD_GET);
            $request->setBody('');
            $request->setUrl($office_url);

            $response = $request->send();
            $body     = $response->getBody();
            $code     = $response->getStatus();

            if (empty($body) || $code != 200) {
                throw new Exception("Unexpected WOPI discovery response");
            }
        }
        catch (Exception $e) {
            rcube::raise_error($e, true, false);

            // Don't bail out here, it would make the kolab_files UI broken
            return array();
        }

        // parse XML output
        // <wopi-discovery>
        //   <net-zone name="external-http">
        //     <app name="application/vnd.lotus-wordpro">
        //       <action ext="lwp" name="edit" urlsrc="https://office.example.org/loleaflet/1.8.3/loleaflet.html?"/>
        //     </app>
        // ...

        $node = new DOMDocument('1.0', 'UTF-8');
        $node->loadXML($body);

        $result = array();

        foreach ($node->getElementsByTagName('app') as $app) {
            if ($mimetype = $app->getAttribute('name')) {
                if ($action = $app->getElementsByTagName('action')->item(0)) {
                    foreach ($action->attributes as $attr) {
                        $result[$mimetype][$attr->name] = $attr->value;
                    }
                }
            }
        }

        if (empty($result)) {
            rcube::raise_error("Failed to parse WOPI discovery response: $body", true, false);

            // Don't bail out here, it would make the kolab_files UI broken
            return array();
        }

        $this->save_in_cache($cache_key, $result);

        return $this->apply_aliases_and_exceptions($result);
    }

    /**
     * Initializes HTTP request object
     */
    protected function http_request()
    {
        require_once 'HTTP/Request2.php';

        $request = new HTTP_Request2();

        // Configure connection options
        $config      = $this->rc->config;
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
        $request->setHeader('accept', "application/json,text/javascript,*/*");

        return $request;
    }

    /**
     * Get cached data
     */
    protected function get_from_cache($key)
    {
        if ($cache = $this->get_cache()) {
            return $cache->get($key);
        }
    }

    /**
     * Store data in cache
     */
    protected function save_in_cache($key, $value)
    {
        if ($cache = $this->get_cache()) {
            $cache->set($key, $value);
        }
    }

    /**
     * Getter for the shared cache engine object
     */
    protected function get_cache()
    {
        if ($this->cache === null) {
            $this->cache = $this->rc->get_cache_shared('fileapi') ?: false;
        }

        return $this->cache;
    }

    /**
     * Support more mimetypes in CODE capabilities
     */
    protected function apply_aliases_and_exceptions($caps)
    {
        foreach ($this->mimetype_aliases as $type => $alias) {
            if (isset($caps[$type]) && !isset($caps[$alias])) {
                $caps[$alias] = $caps[$type];
            }
        }

        foreach ($this->mimetype_exceptions as $type) {
            unset($caps[$type]);
        }

        return $caps;
    }

    /**
     * Write login data (name, ID, IP address) to the 'userlogins' log file.
     */
    protected function log_login($session_id)
    {
        if (!$this->api->config->get('log_logins')) {
            return;
        }

        $rcube     = rcube::get_instance();
        $user_name = $rcube->get_user_name();
        $user_id   = $rcube->get_user_id();

        $message = sprintf('CODE access for %s (ID: %d) from %s in session %s; %s',
            $user_name, $user_id, rcube_utils::remote_ip(), session_id(), $session_id);

        // log login
        rcube::write_log('userlogins', $message);
    }
}
