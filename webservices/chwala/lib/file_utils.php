<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2013, Kolab Systems AG                                |
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

class file_utils
{
    static $class_map = array(
        'document' => array(
            // text
            'text/',
            'application/rtf',
            'application/x-rtf',
            'application/xml',
            // office
            'application/wordperfect',
            'application/excel',
            'application/msword',
            'application/msexcel',
            'application/mspowerpoint',
            'application/vnd.ms-word',
            'application/vnd.ms-excel',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument',
            'application/vnd.oasis.opendocument',
            'application/vnd.sun.xml.calc',
            'application/vnd.sun.xml.writer',
            'application/vnd.stardivision.calc',
            'application/vnd.stardivision.writer',
            // pdf
            'application/pdf',
            'application/x-pdf',
            'application/acrobat',
            'application/vnd.pdf',
        ),
        'audio' => array(
            'audio/',
        ),
        'video' => array(
            'video/',
        ),
        'image' => array(
            'image/',
            'application/dxf',
            'application/acad',
        ),
        'empty' => array(
            'application/x-empty',
        ),
    );


    /**
     * Return list of mimetype prefixes for specified file class
     *
     * @param string $class Class name
     *
     * @return array List of mimetype prefixes
     */
    static function class2mimetypes($class)
    {
        return isset(self::$class_map[$class]) ? self::$class_map[$class] : self::$class_map['empty'];
    }

    /**
     * Finds class of specified mimetype
     *
     * @param string $mimetype File mimetype
     *
     * @return string Class name
     */
    static function mimetype2class($mimetype)
    {
        $mimetype = strtolower($mimetype);

        foreach (self::$class_map as $class => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (strpos($mimetype, $prefix) === 0) {
                    return $class;
                }
            }
        }
    }

    /**
     * Apply some fixes on file mimetype string
     *
     * @param string $mimetype File type
     *
     * @return string File type
     */
    static function real_mimetype($mimetype)
    {
        if (preg_match('/^text\/(.+)/i', $mimetype, $m)) {
            // fix pdf mimetype
            if (preg_match('/^(pdf|x-pdf)$/i', $m[1])) {
                $mimetype = 'application/pdf';
            }
        }

        return $mimetype;
    }

    /**
     * Returns script URI
     *
     * @return string Script URI
     */
    static function script_uri()
    {
        if (!empty($_SERVER['SCRIPT_URI'])) {
            return $_SERVER['SCRIPT_URI'];
        }

        $uri = $_SERVER['SERVER_PORT'] == 443 ? 'https://' : 'http://';
        $uri .= $_SERVER['HTTP_HOST'];
        $uri .= preg_replace('/\?.*$/', '', $_SERVER['REQUEST_URI']);

        return $uri;
    }
}
