<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2011-2016, Kolab Systems AG                                |
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
 * Class integrating Collabora Online documents viewer
 */
class file_viewer_doc extends file_viewer
{
    /**
     * Class constructor
     *
     * @param file_api File API object
     */
    public function __construct($api)
    {
        $this->api = $api;
    }

    /**
     * Returns list of supported mimetype
     *
     * @return array List of mimetypes
     */
    public function supported_mimetypes()
    {
        $rcube = rcube::get_instance();

        // Get list of supported types from Collabora
        if ($rcube->config->get('fileapi_wopi_office')) {
            $wopi = new file_wopi($this->api);
            if ($types = $wopi->supported_filetypes()) {
                return $types;
            }
        }

        return array();
    }

    /**
     * Check if mimetype is supported by the viewer
     *
     * @param string $mimetype File type
     *
     * @return bool True if mimetype is supported, False otherwise
     */
    public function supports($mimetype)
    {
        return in_array($mimetype, $this->supported_mimetypes());
    }

    /**
     * Return file viewer URL
     *
     * @param string $file     File name
     * @param string $mimetype File type
     */
    public function href($file, $mimetype = null)
    {
        return $this->api->file_url($file) . '&viewer=doc';
    }

    /**
     * Print output and exit
     *
     * @param string $file      File name
     * @param array  $file_info File metadata (e.g. type)
     */
    public function output($file, $file_info = array())
    {
        // Create readonly session and get WOPI request parameters
        $wopi = new file_wopi($this->api);
        $url  = $wopi->session_start($file, $file_info, $session, true);

        if (!$url) {
            $this->api->output_error("Failed to open file", 404);
        }

        $info = array('readonly' => true);
        $post = $wopi->editor_post_params($info);
        $url  = htmlentities($url);
        $form = '';

        foreach ($post as $name => $value) {
            $form .= '<input type="hidden" name="' . $name . '" value="' . $value . '" />';
        }

        echo <<<EOT
<html>
  <head>
    <script src="viewers/doc/file_editor.js" type="text/javascript" charset="utf-8"></script>
    <style>
      iframe, body { width: 100%; height: 100%; margin: 0; border: none; }
      form { display: none; }
    </style>
  </head>
  <body>
    <iframe id="viewer" name="viewer" allowfullscreen></iframe>
    <form target="viewer" method="post" action="$url">
      $form
    </form>
    <script type="text/javascript">
      var file_editor = new file_editor;
      file_editor.init();
    </script>
  </body>
</html>
EOT;
    }
}
