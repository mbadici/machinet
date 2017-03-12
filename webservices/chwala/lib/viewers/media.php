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
 * Class integrating HTML5 audio/video player from http://mediaelementjs.com
 */
class file_viewer_media extends file_viewer
{
    protected $mimetypes = array(
        'video/mp4',
        'video/m4v',
        'video/ogg',
        'video/webm',
//        'video/3gpp',
//        'video/flv',
//        'video/x-flv',
        'application/ogg',
        'audio/mp3',
        'audio/mpeg',
        'audio/ogg',
        'audio/wav',
        'audio/flv',
        'audio/x-mpeg',
        'audio/x-ogg',
        'audio/x-wav',
        'audio/x-flv',
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
     * Return output of file content area
     *
     * @param string $file     File name
     * @param string $mimetype File type
     */
    public function frame($file, $mimetype = null)
    {
        $path     = file_utils::script_uri();
        $file_uri = htmlentities($this->api->file_url($file));
        $mimetype = htmlentities($mimetype);
        $source   = "<source src=\"$file_uri\" type=\"$mimetype\"></source>";

        if (preg_match('/^audio/', $mimetype)) {
            $tag = 'audio';
        }
        else {
            $tag = 'video';
        }

        return <<<EOT
    <link rel="stylesheet" type="text/css" href="{$path}viewers/media/mediaelementplayer.css" />
    <script type="text/javascript" src="{$path}viewers/media/mediaelement-and-player.js"></script>
    <$tag id="media-player" controls preload="auto">$source</$tag>
    <style>
      .mejs-container { text-align: center; }
    </style>
    <script>
      var content_frame = $('#media-player').parent(),
        height = content_frame.height(),
        width = content_frame.width(),
        player = new MediaElementPlayer('#media-player', {
          videoHeight: height, audioHeight: height, videoWidth: width, audioWidth: width
        });

      player.pause();
      player.play();
      // add player resize handler
      $(window).resize(function() {
        player.setPlayerSize(content_frame.width(), content_frame.height());
      });
    </script>
EOT;
    }
}
