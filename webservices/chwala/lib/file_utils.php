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

    // list of known file extensions, more in Roundcube config
    static $ext_map = array(
        'doc'  => 'application/msword',
        'eml'  => 'message/rfc822',
        'gz'   => 'application/gzip',
        'htm'  => 'text/html',
        'html' => 'text/html',
        'mp3'  => 'audio/mpeg',
        'odp'  => 'application/vnd.oasis.opendocument.presentation',
        'ods'  => 'application/vnd.oasis.opendocument.spreadsheet',
        'odt'  => 'application/vnd.oasis.opendocument.text',
        'ogg'  => 'application/ogg',
        'pdf'  => 'application/pdf',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'rar'  => 'application/x-rar-compressed',
        'tgz'  => 'application/gzip',
        'txt'  => 'text/plain',
        'zip'  => 'application/zip',
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
     * Find mimetype from file name (extension)
     *
     * @param string $filename File name
     * @param string $fallback Follback mimetype
     *
     * @return string File mimetype
     */
    static function ext_to_type($filename, $fallback = 'application/octet-stream')
    {
        static $mime_ext = array();

        $config = rcube::get_instance()->config;
        $ext    = substr($filename, strrpos($filename, '.') + 1);

        if (empty($mime_ext)) {
            $mime_ext = self::$ext_map;
            foreach ($config->resolve_paths('mimetypes.php') as $fpath) {
                $mime_ext = array_merge($mime_ext, (array) @include($fpath));
            }
        }

        if (is_array($mime_ext) && $ext) {
            $mimetype = $mime_ext[strtolower($ext)];
        }

        return $mimetype ?: $fallback;
    }

    /**
     * Callback for uasort() that implements correct
     * locale-aware case-sensitive sorting
     */
    public static function sort_folder_comparator($p1, $p2)
    {
        $ext   = is_array($p1); // folder can be a string or an array with 'folder' key
        $path1 = explode(file_storage::SEPARATOR, $ext ? $p1['folder'] : $p1);
        $path2 = explode(file_storage::SEPARATOR, $ext ? $p2['folder'] : $p2);

        foreach ($path1 as $idx => $folder1) {
            $folder2 = $path2[$idx];

            if ($folder1 === $folder2) {
                continue;
            }

            return strcoll($folder1, $folder2);
        }

        return 0;
    }

    /**
     * Encode folder path for use in an URI
     *
     * @param string $path Folder path
     *
     * @return string Encoded path
     */
    public static function encode_path($path)
    {
        $items = explode(file_storage::SEPARATOR, $path);
        $items = array_map('rawurlencode', $items);

        return implode(file_storage::SEPARATOR, $items);
    }

    /**
     * Decode an URI into folder path
     *
     * @param string $path Encoded folder path
     *
     * @return string Decoded path
     */
    public static function decode_path($path)
    {
        $items = explode(file_storage::SEPARATOR, $path);
        $items = array_map('rawurldecode', $items);

        return implode(file_storage::SEPARATOR, $items);
    }

    /**
     *
     * @return string Date time string in specified format and timezone
     */
    public static function date_format($datetime, $format = 'Y-m-d H:i', $timezone = null)
    {
        if (!$datetime instanceof DateTime) {
            return '';
        }

        if ($timezone && $timezone != $datetime->getTimezone()) {
            try {
                $dt = clone $datetime;
                $dt->setTimezone(new DateTimeZone($timezone));
                $datetime = $dt;
            }
            catch (Exception $e) {
                // ignore, return original timezone
            }
        }

        return $datetime->format($format);
    }
}
