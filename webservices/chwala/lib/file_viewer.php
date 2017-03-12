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
 * Abstract viewer class
 */
abstract class file_viewer
{
    protected $mimetypes = array();
    protected $api;


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
        return $this->mimetypes;
    }

    /**
     * Check if mimetype is supported by the viewer
     *
     * @param string $mimetype File type
     *
     * @return bool
     */
    public function supports($mimetype)
    {
        return in_array($mimetype, $this->mimetypes);
    }

    /**
     * Print output and exit
     *
     * @param string $file     File name
     * @param string $mimetype File type
     */
    public function output($file, $mimetype = null)
    {
    }

    /**
     * Return output of file content area
     *
     * @param string $file     File name
     * @param string $mimetype File type
     */
    public function frame($file, $mimetype = null)
    {
    }

    /**
     * Return file URL of file content area
     *
     * @param string $file     File name
     * @param string $mimetype File type
     */
    public function href($file, $mimetype = null)
    {
    }
}
