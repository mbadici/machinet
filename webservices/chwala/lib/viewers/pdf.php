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
 * Class integrating PDF viewer from https://github.com/mozilla/pdf.js
 */
class file_viewer_pdf extends file_viewer
{
    protected $mimetypes = array(
        'application/pdf',
        'application/x-pdf',
        'application/acrobat',
        'applications/vnd.pdf',
        'text/pdf',
        'text/x-pdf',
    );

    /**
     * Class constructor
     *
     * @param file_api File API object
     */
    public function __construct($api)
    {
        $this->api = $api;

        $browser = $api->get_browser();

        // disable viewer in unsupported browsers according to
        // https://github.com/mozilla/pdf.js/wiki/Required-Browser-Features
        if (($browser->ie && $browser->ver < 9)
            || ($browser->opera && $browser->ver < 9.5)
            || ($browser->chrome && $browser->ver < 24)
            || ($browser->safari && $browser->ver < 5)
            || ($browser->mz && $browser->ver < 6)
        ) {
            $this->mimetypes = array();
        }
    }

    /**
     * Return file viewer URL
     *
     * @param string $file     File name
     * @param string $mimetype File type
     */
    public function href($file, $mimetype = null)
    {
        return file_utils::script_uri() . 'viewers/pdf/viewer.html'
            . '?file=' . urlencode($this->api->file_url($file));
    }
}
