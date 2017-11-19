<?php
/**
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2016, Kolab Systems AG                                |
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
 * Document editing sessions handling (Manticore)
 */
class file_manticore extends file_document
{
    protected $request;


    /**
     * Return viewer URI for specified file/session. This creates
     * a new collaborative editing session when needed.
     *
     * @param string $file        File path
     * @param array  &$file_info  File metadata (e.g. type)
     * @param string &$session_id Optional session ID to join to
     * @param string $readonly    Create readonly (one-time) session
     *
     * @return string Manticore URI
     * @throws Exception
     */
    public function session_start($file, &$file_info, &$session_id = null, $readonly = false)
    {
        parent::session_start($file, $file_info, $session_id, $readonly);

        // authenticate to Manticore, we need auth token for frame_uri
        if (empty($_SESSION['manticore_token'])) {
            $this->get_request();
        }

        // @TODO: make sure the session exists in Manticore?

        return $this->frame_uri($session_id);
    }

    /**
     * Delete editing session (only owner can do that)
     *
     * @param string $id    Session identifier
     * @param bool   $local Remove session only from local database
     */
    public function session_delete($id, $local = false)
    {
        $success = parent::session_delete($id, $local);

        // Send document delete to Manticore
        if ($success && !$local) {
            $req = $this->get_request();
            $res = $req->document_delete($id);
        }

        return $success;
    }

    /**
     * Create editing session
     */
    protected function session_create($id, $uri, $owner, $data, $readonly = false)
    {
        $success = parent::session_create($id, $uri, $owner, $data, $readonly);

        // create the session in Manticore
        if ($success) {
            $req = $this->get_request();
            $res = $req->document_create(array(
                'id'     => $id,
                'title'  => '', // @TODO: maybe set to a file path without extension?
                'access' => array(
                    array(
                        'identity'   => $owner,
                        'permission' => file_manticore_api::ACCESS_WRITE,
                    ),
                ),
            ));

            if (!$res) {
                $this->session_delete($id, true);
                return false;
            }
        }

        return $success;
    }

    /**
     * Create an invitation
     *
     * @param string $session_id Document session identifier
     * @param string $user       User identifier (use null for current user)
     * @param string $status     Invitation status (invited, requested)
     * @param string $comment    Invitation description/comment
     * @param string &$user_name Optional user name
     *
     * @throws Exception
     */
    public function invitation_create($session_id, $user, $status = 'invited', $comment = '', &$user_name = '')
    {
        parent::invitation_create($session_id, $user, $status, $comment, $user_name);

        // Update Manticore 'access' array
        if ($status == file_document::STATUS_INVITED) {
            $req = $this->get_request();
            $res = $req->editor_add($session_id, $user, file_manticore_api::ACCESS_WRITE);

            if (!$res) {
                $this->invitation_delete($session_id, $user, true);
                throw new Exception("Failed to create an invitation.", file_api_core::ERROR_CODE);
            }
        }
    }

    /**
     * Delete an invitation (only session owner can do that)
     *
     * @param string $session_id Session identifier
     * @param string $user       User identifier
     * @param bool   $local      Remove invitation only from local database
     *
     * @throws Exception
     */
    public function invitation_delete($session_id, $user, $local = false)
    {
        parent::invitation_delete($session_id, $user, $local);

        // Update Manticore 'access' array
        if (!$local) {
            $req = $this->get_request();
            $res = $req->editor_delete($session_id, $user);

            if (!$res) {
                throw new Exception("Failed to remove an invitation.", file_api_core::ERROR_CODE);
            }
        }
    }

    /**
     * Update an invitation status
     *
     * @param string $session_id Session identifier
     * @param string $user       User identifier (use null for current user)
     * @param string $status     Invitation status (accepted, declined)
     * @param string $comment    Invitation description/comment
     *
     * @throws Exception
     */
    public function invitation_update($session_id, $user, $status, $comment = '')
    {
        parent::invitation_update($session_id, $user, $status, $comment);

        // Update Manticore 'access' array if an owner accepted an invitation request
        if ($status == file_document::STATUS_ACCEPTED_OWNER) {
            $req = $this->get_request();
            $res = $req->editor_add($session_id, $user, file_manticore_api::ACCESS_WRITE);

            if (!$res) {
                throw new Exception("Failed to update an invitation status.", file_api_core::ERROR_CODE);
            }
        }
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
        return array(
            'application/vnd.oasis.opendocument.text',
        );
    }

    /**
     * Generate URI of Manticore editing session
     */
    protected function frame_uri($id)
    {
        $base_url = rtrim($this->rc->config->get('fileapi_manticore'), ' /');

        return $base_url . '/document/' . $id . '/' . $_SESSION['manticore_token'];
    }

    /**
     * Initialize Manticore API request handler
     */
    protected function get_request()
    {
        if (!$this->request) {
            $uri = rcube_utils::resolve_url($this->rc->config->get('fileapi_manticore'));
            $this->request = new file_manticore_api($uri);

            // Use stored session token, check if it's still valid
            if ($_SESSION['manticore_token']) {
                $is_valid = $this->request->set_session_token($_SESSION['manticore_token'], true);

                if ($is_valid) {
                    return $this->request;
                }
            }

            $backend = $this->api->get_backend();
            $auth    = $backend->auth_info();

            $_SESSION['manticore_token'] = $this->request->login($auth['username'], $auth['password']);

            if (empty($_SESSION['manticore_token'])) {
                throw new Exception("Unable to login to Manticore server.", file_api_core::ERROR_CODE);
            }
        }

        return $this->request;
    }
}
