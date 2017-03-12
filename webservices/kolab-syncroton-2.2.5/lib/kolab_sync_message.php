<?php

/**
 +--------------------------------------------------------------------------+
 | Kolab Sync (ActiveSync for Kolab)                                        |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG <contact@kolabsys.com>         |
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

class kolab_sync_message
{
    protected $headers = array();
    protected $body;
    protected $ctype;
    protected $ctype_params = array();

    /**
     * Constructor
     *
     * @param string|resource $source MIME message source
     */
    function __construct($source)
    {
        $this->parse_mime($source);
    }

    /**
     * Returns message headers
     *
     * @return array Message headers
     */
    public function headers()
    {
        return $this->headers;
    }

    public function source()
    {
        $headers = array();

        // Build the message back
        foreach ($this->headers as $header => $header_value) {
            $headers[$header] = $header . ': ' . $header_value;
        }

        return trim(implode("\r\n", $headers)) . "\r\n\r\n" . ltrim($this->body);
        // @TODO: work with file streams
    }

    /**
     * Appends text at the end of the message body
     *
     * @todo: HTML support
     *
     * @param string $text    Text to append
     * @param string $charset Text charset
     */
    public function append($text, $charset = null)
    {
        if ($this->ctype == 'text/plain') {
            // decode body
            $body = $this->decode($this->body, $this->headers['Content-Transfer-Encoding']);
            $body = rcube_charset::convert($body, $this->ctype_params['charset'], $charset);
            // append text
            $body .= $text;
            // encode and save
            $body = rcube_charset::convert($body, $charset, $this->ctype_params['charset']);
            $this->body = $this->encode($body, $this->headers['Content-Transfer-Encoding']);
        }
    }

    /**
     * Adds attachment to the message
     *
     * @param string $body   Attachment body (not encoded)
     * @param string $params Attachment parameters (Mail_mimePart format)
     */
    public function add_attachment($body, $params = array())
    {
        // convert the message into multipart/mixed
        if ($this->ctype != 'multipart/mixed') {
            $boundary = '_' . md5(rand() . microtime());

            $this->body = "--$boundary\r\n"
                ."Content-Type: " . $this->headers['Content-Type']."\r\n"
                ."Content-Transfer-Encoding: " . $this->headers['Content-Transfer-Encoding']."\r\n"
                ."\r\n" . trim($this->body) . "\r\n"
                ."--$boundary\r\n";

            $this->ctype = 'multipart/mixed';
            $this->ctype_params = array('boundary' => $boundary);
            unset($this->headers['Content-Transfer-Encoding']);
            $this->save_content_type($this->ctype, $this->ctype_params);
        }

        // make sure MIME-Version header is set, it's required by some servers
        if (empty($this->headers['MIME-Version'])) {
            $this->headers['MIME-Version'] = '1.0';
        }

        $boundary = $this->ctype_params['boundary'];

        $part = new Mail_mimePart($body, $params);
        $body = $part->encode();

        foreach ($body['headers'] as $name => $value) {
            $body['headers'][$name] = $name . ': ' . $value;
        }

        $this->body = rtrim($this->body);
        $this->body = preg_replace('/--$/', '', $this->body);

        // add the attachment to the end of the message
        $this->body .= "\r\n"
            .implode("\r\n", $body['headers']) . "\r\n\r\n"
            .$body['body'] . "\r\n--$boundary--\r\n";
    }

    /**
     * Sets the value of specified message header
     *
     * @param string $name  Header name
     * @param string $value Header value
     */
    public function set_header($name, $value)
    {
        $name = self::normalize_header_name($name);

        if ($name != 'Content-Type') {
            $this->headers[$name] = $value;
        }
    }

    /**
     * Send the given message using the configured method.
     *
     * @param array $smtp_error SMTP error array (reference)
     * @param array $smtp_opts  SMTP options (e.g. DSN request)
     *
     * @return boolean Send status.
     */
    public function send(&$smtp_error = null, $smtp_opts = null)
    {
        $rcube   = rcube::get_instance();
        $headers = $this->headers;
        $mailto  = $headers['To'];

        $headers['User-Agent'] .= $rcube->app_name . ' ' . kolab_sync::VERSION;
        if ($agent = $rcube->config->get('useragent')) {
            $headers['User-Agent'] .= '/' . $agent;
        }

        if (empty($headers['From'])) {
            $headers['From'] = $this->get_identity();
        }

        if (empty($headers['Message-ID'])) {
            $headers['Message-ID'] = $rcube->gen_message_id();
        }

        // remove empty headers
        $headers = array_filter($headers);

        // send thru SMTP server using custom SMTP library
        if ($rcube->config->get('smtp_server')) {
            $smtp_headers = $headers;
            // generate list of recipients
            $recipients = array();

            if (!empty($headers['To']))
                $recipients[] = $headers['To'];
            if (!empty($headers['Cc']))
                $recipients[] = $headers['Cc'];
            if (!empty($headers['Bcc']))
                $recipients[] = $headers['Bcc'];

            // remove Bcc header
            unset($smtp_headers['Bcc']);

            // send message
            if (!is_object($rcube->smtp)) {
                $rcube->smtp_init(true);
            }

            $sent = $rcube->smtp->send_mail($headers['From'], $recipients, $smtp_headers, $this->body, $smtp_opts);
            $smtp_response = $rcube->smtp->get_response();
            $smtp_error    = $rcube->smtp->get_error();

            // log error
            if (!$sent) {
                rcube::raise_error(array('code' => 800, 'type' => 'smtp',
                    'line' => __LINE__, 'file' => __FILE__,
                    'message' => "SMTP error: ".join("\n", $smtp_response)), true, false);
            }
        }
        // send mail using PHP's mail() function
        else {
            $mail_headers = $headers;
            $delim        = $rcube->config->header_delimiter();
            $subject      = $headers['Subject'];
            $to           = $headers['To'];

            // unset some headers because they will be added by the mail() function
            unset($mail_headers['To'], $mail_headers['Subject']);

            // #1485779
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                if (preg_match_all('/<([^@]+@[^>]+)>/', $to, $m)) {
                    $to = implode(', ', $m[1]);
                }
            }

            foreach ($mail_headers as $header => $header_value) {
                $mail_headers[$header] = $header . ': ' . $header_value;
            }
            $header_str = rtrim(implode("\r\n", $mail_headers));

            if ($delim != "\r\n") {
                $header_str = str_replace("\r\n", $delim, $header_str);
                $msg_body   = str_replace("\r\n", $delim, $this->body);
                $to         = str_replace("\r\n", $delim, $to);
                $subject    = str_replace("\r\n", $delim, $subject);
            }

            if (ini_get('safe_mode')) {
                $sent = mail($to, $subject, $msg_body, $header_str);
            }
            else {
                $sent = mail($to, $subject, $msg_body, $header_str, "-f$from");
            }
        }

        if ($sent) {
            $rcube->plugins->exec_hook('message_sent', array('headers' => $headers, 'body' => $this->body));

            // remove MDN headers after sending
            unset($headers['Return-Receipt-To'], $headers['Disposition-Notification-To']);

            // get all recipients
            if ($headers['Cc'])
                $mailto .= ' ' . $headers['Cc'];
            if ($headers['Bcc'])
                $mailto .= ' ' . $headers['Bcc'];
            if (preg_match_all('/<([^@]+@[^>]+)>/', $mailto, $m))
                $mailto = implode(', ', array_unique($m[1]));

            if ($rcube->config->get('smtp_log')) {
                rcube::write_log('sendmail', sprintf("User %s [%s]; Message for %s; %s",
                    $rcube->get_user_name(),
                    $_SERVER['REMOTE_ADDR'],
                    $mailto,
                    !empty($smtp_response) ? join('; ', $smtp_response) : ''));
            }
        }

        unset($headers['Bcc']);

        $this->headers = $headers;

        return $sent;
    }

    /**
     * Parses the message source and fixes 8bit data for ActiveSync.
     * This way any not UTF8 characters will be encoded before
     * sending to the device.
     *
     * @param string $message Message source
     *
     * @return string Fixed message source
     */
    public static function recode_message($message)
    {
        // @TODO: work with stream, to workaround memory issues with big messages
        if (is_resource($message)) {
            $message = stream_get_contents($message);
        }

        list($headers, $message) = preg_split('/\r?\n\r?\n/', $message, 2, PREG_SPLIT_NO_EMPTY);

        $hdrs = self::parse_headers($headers);

        // multipart message
        if (preg_match('/boundary="?([a-z0-9-\'\(\)+_\,\.\/:=\? ]+)"?/i', $hdrs['Content-Type'], $matches)) {
            $boundary = '--' . $matches[1];
            $message  = explode($boundary, $message);

            for ($x=1, $parts = count($message) - 1; $x<$parts; $x++) {
                $message[$x] = "\r\n" . self::recode_message(ltrim($message[$x]));
            }

            return $headers . "\r\n\r\n" . implode($boundary , $message);
        }

        // single part
        $enc = strtolower($hdrs['Content-Transfer-Encoding']);

        // do nothing if already encoded
        if ($enc != 'quoted-printable' && $enc != 'base64') {
            // recode body if any non-printable-ascii characters found
            if (preg_match('/[^\x20-\x7E\x0A\x0D\x09]/', $message)) {
                $hdrs['Content-Transfer-Encoding'] = 'base64';
                foreach ($hdrs as $header => $header_value) {
                    $hdrs[$header] = $header . ': ' . $header_value;
                }

                $headers = trim(implode("\r\n", $hdrs));
                $message = rtrim(chunk_split(base64_encode(rtrim($message)), 76, "\r\n")) . "\r\n";
            }
        }

        return $headers . "\r\n\r\n" . $message;
    }

    /**
     * MIME message parser
     *
     * @param string|resource $message     MIME message source
     * @param bool            $decode_body Enables body decoding
     *
     * @return array Message headers array and message body
     */
    protected function parse_mime($message)
    {
        // @TODO: work with stream, to workaround memory issues with big messages
        if (is_resource($message)) {
            $message = stream_get_contents($message);
        }

        list($headers, $message) = preg_split('/\r?\n\r?\n/', $message, 2, PREG_SPLIT_NO_EMPTY);

        $headers = self::parse_headers($headers);

        // parse Content-Type header
        $ctype_parts = preg_split('/[; ]+/', $headers['Content-Type']);
        $this->ctype = strtolower(array_shift($ctype_parts));
        foreach ($ctype_parts as $part) {
            if (preg_match('/^([a-z-_]+)\s*=\s*(.+)$/i', trim($part), $m)) {
                $this->ctype_params[strtolower($m[1])] = trim($m[2], '"');
            }
        }

        if (!empty($headers['Content-Transfer-Encoding'])) {
            $headers['Content-Transfer-Encoding'] = strtolower($headers['Content-Transfer-Encoding']);
        }

        $this->headers = $headers;
        $this->body    = $message;
    }

    /**
     * Parse message source with headers
     */
    protected static function parse_headers($headers)
    {
        // Parse headers
        $headers = str_replace("\r\n", "\n", $headers);
        $headers = explode("\n", trim($headers));

        $ln    = 0;
        $lines = array();

        foreach ($headers as $line) {
            if (ord($line[0]) <= 32) {
                $lines[$ln] .= (empty($lines[$ln]) ? '' : "\r\n") . $line;
            }
            else {
                $lines[++$ln] = trim($line);
            }
        }

        // Unify char-case of header names
        $headers = array();
        foreach ($lines as $line) {
            list($field, $string) = explode(':', $line, 2);
            if ($field = self::normalize_header_name($field)) {
                $headers[$field] = trim($string);
            }
        }

        return $headers;
    }

    /**
     * Normalize (fix) header names
     */
    protected static function normalize_header_name($name)
    {
        $headers_map = array(
            'subject' => 'Subject',
            'from'    => 'From',
            'to'      => 'To',
            'cc'      => 'Cc',
            'bcc'     => 'Bcc',
            'message-id'   => 'Message-ID',
            'references'   => 'References',
            'content-type' => 'Content-Type',
            'content-transfer-encoding' => 'Content-Transfer-Encoding',
        );

        $name_lc = strtolower($name);

        return isset($headers_map[$name_lc]) ? $headers_map[$name_lc] : $name;
    }

    /**
     * Encodes message/part body
     *
     * @param string $body     Message/part body
     * @param string $encoding Content encoding
     *
     * @return string Encoded body
     */
    protected function encode($body, $encoding)
    {
        switch ($encoding) {
        case 'base64':
            $body = base64_encode($body);
            $body = chunk_split($body, 76, "\r\n");
            break;
        case 'quoted-printable':
            $body = quoted_printable_encode($body);
            break;
        }

        return $body;
    }

    /**
     * Decodes message/part body
     *
     * @param string $body     Message/part body
     * @param string $encoding Content encoding
     *
     * @return string Decoded body
     */
    protected function decode($body, $encoding)
    {
        $body  = str_replace("\r\n", "\n", $body);

        switch ($encoding) {
        case 'base64':
            $body = base64_decode($body);
            break;
        case 'quoted-printable':
            $body = quoted_printable_decode($body);
            break;
        }

        return $body;
    }

    /**
     * Returns email address string from default identity of the current user
     */
    protected function get_identity()
    {
        $user = kolab_sync::get_instance()->user;

        if ($identity = $user->get_identity()) {
            return format_email_recipient(format_email($identity['email']), $identity['name']);
        }
    }

    protected function save_content_type($ctype, $params = array())
    {
        $this->ctype        = $ctype;
        $this->ctype_params = $params;

        $this->headers['Content-Type'] = $ctype;
        if (!empty($params)) {
            foreach ($params as $name => $value) {
                $this->headers['Content-Type'] .= sprintf('; %s="%s"', $name, $value);
            }
        }
    }

}
