<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2011-2013, Kolab Systems AG                                |
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
 * Class implementing image viewer (with format converter)
 *
 * NOTE: some formats are supported by browser, don't use viewer when not needed.
 */
class file_viewer_image extends file_viewer
{
    protected $mimetypes = array(
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
     * Class constructor
     *
     * @param file_api File API object
     */
    public function __construct($api)
    {
        // @TODO: disable types not supported by some browsers
        $this->api = $api;
    }

    /**
     * Return file viewer URL
     *
     * @param string $file     File name
     * @param string $mimetype File type
     */
    public function href($file, $mimetype = null)
    {
        $href = file_utils::script_uri() . '?method=file_get'
            . '&file=' . urlencode($file)
            . '&token=' . urlencode(session_id());

        // we redirect to self only images with types unsupported
        // by browser
        if (in_array($mimetype, $this->mimetypes)) {
            $href .= '&viewer=image';
        }

        return $href;
    }

    /**
     * Print output and exit
     *
     * @param string $file     File name
     * @param string $mimetype File type
     */
    public function output($file, $mimetype = null)
    {
/*
        // conversion not needed
        if (preg_match('/^image/p?jpe?g$/i', $mimetype)) {
            $this->api->api->file_get($file);
            return;
        }
*/
        $rcube     = rcube::get_instance();
        $temp_dir  = unslashify($rcube->config->get('temp_dir'));
        $file_path = tempnam($temp_dir, 'rcmImage');

        // write content to temp file
        $fd = fopen($file_path, 'w');
        $this->api->api->file_get($file, array(), $fd);
        fclose($fd);

        // convert image to jpeg and send it to the browser
        $image = new rcube_image($file_path);
        if ($image->convert(rcube_image::TYPE_JPG, $file_path)) {
          header("Content-Type: image/jpeg");
          header("Content-Length: " . filesize($file_path));
          readfile($file_path);
        }
        unlink($file_path);
    }
}
