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
 * Class integrating text editor http://ajaxorg.github.io/ace
 */
class file_viewer_text extends file_viewer
{
    /**
     * Mimetype to tokenizer map
     *
     * @var array
     */
    protected $mimetypes = array(
        'text/plain' => 'text',
        'text/html' => 'html',
        'text/javascript' => 'javascript',
        'text/ecmascript' => 'javascript',
        'text/x-c' => 'c_cpp',
        'text/css' => 'css',
        'text/x-java-source' => 'java',
        'text/x-php' => 'php',
        'text/x-sh' => 'sh',
        'text/xml' => 'xml',
        'application/xml' => 'xml',
        'application/x-vbscript' => 'vbscript',
        'message/rfc822' => 'text',
        'application/x-empty' => 'text',
    );

    /**
     * File extension to highligter mode mapping
     *
     * @var array
     */
    protected $extensions = array(
        'php'  => '/^(php|phpt|inc)$/',
        'html' => '/^html?$/',
        'css'  => '/^css$/',
        'xml'  => '/^xml$/',
        'javascript' => '/^js$/',
        'sh'   => '/^sh$/',
    );

    /**
     * Returns list of supported mimetype
     *
     * @return array List of mimetypes
     */
    public function supported_mimetypes()
    {
        // we return only mimetypes not starting with text/
        $mimetypes = array();

        foreach (array_keys($this->mimetypes) as $type) {
            if (strpos($type, 'text/') !== 0) {
                $mimetypes[] = $type;
            }
        }

        return $mimetypes;
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
        return $this->mimetypes[$mimetype] || preg_match('/^text\/(?!(pdf|x-pdf))/', $mimetype);
    }

    /**
     * Print file content
     */
    protected function print_file($file)
    {
        $stdout = fopen('php://output', 'w');

        stream_filter_register('file_viewer_text', 'file_viewer_content_filter');
        stream_filter_append($stdout, 'file_viewer_text');

        $this->api->api->file_get($file, array(), $stdout);
    }

    /**
     * Return file viewer URL
     *
     * @param string $file     File name
     * @param string $mimetype File type
     */
    public function href($file, $mimetype = null)
    {
        return $this->api->file_url($file) . '&viewer=text';
    }

    /**
     * Print output and exit
     *
     * @param string $file     File name
     * @param string $mimetype File type
     */
    public function output($file, $mimetype = null)
    {
        $mode = $this->get_mode($mimetype, $file);
        $href = addcslashes($this->api->file_url($file), "'");

        echo '<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
  <title>Editor</title>
  <script src="viewers/text/ace.js" type="text/javascript" charset="utf-8"></script>
  <script src="viewers/text/file_editor.js" type="text/javascript" charset="utf-8"></script>
  <style>
    #editor { top: 0; right: 0; bottom: 0; left: 0; position: absolute; font-size: 14px; padding: 0; margin: 0; }
    .ace_search_options { float: right; }
  </style>
</head>
<body>
  <pre id="editor">';

        $this->print_file($file);

        echo "</pre>
  <script>
    var file_editor = new file_editor;
    file_editor.init('editor', '$mode', '$href');
  </script>
</body>
</html>";
    }

    protected function get_mode($mimetype, $filename)
    {
        $mimetype = strtolower($mimetype);

        if ($this->mimetypes[$mimetype]) {
            return $this->mimetypes[$mimetype];
        }

        $filename = explode('.', $filename);
        $extension = count($filename) > 1 ? array_pop($filename) : null;

        if ($extension) {
            foreach ($this->extensions as $mode => $regexp) {
                if (preg_match($regexp, $extension)) {
                    return $mode;
                }
            }
        }

        return 'text';
    }
}


/**
 * PHP stream filter to detect escape html special chars in a file
 */
class file_viewer_content_filter extends php_user_filter
{
    private $buffer = '';
    private $cutoff = 2048;

    function onCreate()
    {
        $this->cutoff = rand(2048, 3027);
        return true;
    }

    function filter($in, $out, &$consumed, $closing)
    {
        while ($bucket = stream_bucket_make_writeable($in)) {
            $bucket->data = htmlspecialchars($bucket->data,  ENT_COMPAT | ENT_HTML401 | ENT_IGNORE);
            $this->buffer .= $bucket->data;

            // keep buffer small enough
            if (strlen($this->buffer) > 4096) {
                $this->buffer = substr($this->buffer, $this->cutoff);
            }

            $consumed += $bucket->datalen; // or strlen($bucket->data)?
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }
}
