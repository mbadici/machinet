<?php

/**
 * HTML-to-Text conversion using lynx browser
 *
 * @version 0.1
 * @license GNU GPLv3+
 * @author Aleksander Machniak <machniak@kolabsys.com>
 *
 * Copyright (C) 2016, Kolab Systems AG <contact@kolabsys.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

class html_converter extends rcube_plugin
{
    private static $replaces = array(
        "<blockquote>"  => "<br>&amp;&raquo;&amp;<br>",
        "</blockquote>" => "<br>&amp;&laquo;&amp;<br>",
        "\x02\x03"      => "***^^^SIG^^^***",
    );


    /**
     * Plugin initialization.
     */
    function init()
    {
        // register hook to convert HTML to Text
        $this->add_hook('html2text', array($this, 'html2text'));
    }

    /**
     * Hook to convert HTML to Text.
     * Arguments: body, width, links, charset
     */
    public function html2text($p)
    {
        // prepare HTML content for conversion
        $html = $this->prefilter($p['body']);

        // convert HTML to text
        $result = $this->convert($html, $p);

        // on success skip default rcube_html2text conversion
        if ($result !== false) {
            $result = $this->postfilter($result);

            $p['body']  = $result;
            $p['abort'] = true;
        }

        return $p;
    }

    /**
     * Html to text converter
     */
    private function convert($html, $p)
    {
        if (empty($html)) {
            return false;
        }

        $rcmail   = rcmail::get_instance();
        $temp_dir = $rcmail->config->get('temp_dir');
        $tmpfname = tempnam($temp_dir, 'rcmHtml');

        // write HTML to temp file
        if (!file_put_contents($tmpfname, $html)) {
            return false;
        }

        $args = array(
            '{path}'    => $tmpfname,
            '{width}'   => (int) $p['width'],
            '{charset}' => $p['charset'],
            '{links}'   => $p['links'] ? 1 : 0,
        );
/*
        $command = 'links -force-html -no-connect -no-g -codepage {charset}'
            . ' -aggressive-cache 0 -html-margin 0 -html-numbered-links {links}'
            . ' -width {width} -dump {path}';
*/
        $command = 'lynx -force_html -noreferer -nomargins -dont_wrap_pre'
            . ' -nolist -display_charset={charset} -width={width} -dump {path}';

        $command = str_replace(array_keys($args), array_values($args), $command);

        if ($p['links']) {
            $command = str_replace(' -nolist', '', $command);
        }

        // convert HTML to text
        ob_start();
        passthru($command, $status);
        $result = ob_get_contents();
        ob_end_clean();

        // remove temp file
        unlink($tmpfname);

        if ($status) {
            rcube::raise_error(array(
                    'line'    => __LINE__,
                    'file'    => __FILE__,
                    'message' => "Failed executing: $command (code: $status)"
                ), true, false);
            return false;
        }

        return $result;
    }

    /**
     * HTML content preparation for conversion.
     */
    private function prefilter($html)
    {
        // blockquotes are ignored by links, so we replace them
        // with special code that will be handled later in postfilter

        // the same for special signature-replacement sequence
        // which is used in compose editor

        $html = str_ireplace(array_keys(self::$replaces), array_values(self::$replaces), $html);

        return $html;
    }

    /**
     * Post-filtering on plain text content.
     */
    function postfilter($text)
    {
        $replaces = self::$replaces;

        unset($replaces['<blockquote>']);
        unset($replaces['</blockquote>']);

        // replace special sequences
        $text = str_replace(array_values($replaces), array_keys($replaces), $text);

        // blockquotes handling after conversion
        $start = str_replace('<br>', '', self::$replaces['<blockquote>']);
        $end   = str_replace('<br>', '', self::$replaces['</blockquote>']);
        $start = html_entity_decode($start, ENT_COMPAT, 'UTF-8');
        $end   = html_entity_decode($end, ENT_COMPAT, 'UTF-8');

        if (strpos($text, $start) !== false) {
            $last   = false;
            $level  = 0;
            $result = explode("\n", $text);

            foreach ($result as $idx => $line) {
                if ($line === $start) {
                    $level++;
                    $last = true;
                    unset($result[$idx]);
                }
                else if ($line === $end) {
                    $level--;
                    $last = true;
                    unset($result[$idx]);
                }
                else if ($last && !strlen($line)) {
                    unset($result[$idx]);
                    $last = false;
                }
                else if ($level) {
                    $len = strlen($line);

                    if (!$len && isset($result[$idx+1])
                        && ($result[$idx+1] === $end || $result[$idx+1] === $start)
                    ) {
                        unset($result[$idx]);
                    }
                    else {
                        $result[$idx] = str_repeat('>', $level) . ($len ? ' ' . $line : '');
                    }
                }
            }

            $text = implode("\n", $result);
        }

        return $text;
    }
}
