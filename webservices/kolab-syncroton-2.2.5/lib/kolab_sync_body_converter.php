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

/**
 * Utility class for data convertion between ActiveSync Body formats
 */
class kolab_sync_body_converter
{
    protected $text;
    protected $type;


    /**
     * Constructor
     *
     * @param string $data Data string
     * @param int    $type Data type. One of Syncroton_Model_EmailBody constants.
     */
    public function __construct($data, $type)
    {
        $this->text = $data;
        $this->type = $type;
    }

    /**
     * Converter
     *
     * @param int $type Result data type (to which the string will be converted).
     *                  One of Syncroton_Model_EmailBody constants.
     *
     * @return string Body value
     */
    public function convert($type)
    {
        if (empty($this->text) || empty($type) || $type == $this->type) {
            return $this->text;
        }

        // ActiveSync types: TYPE_PLAINTEXT, TYPE_HTML, TYPE_RTF, TYPE_MIME
        switch ($this->type) {
        case Syncroton_Model_EmailBody::TYPE_PLAINTEXT:
            return $this->convert_text_plain($type);
        case Syncroton_Model_EmailBody::TYPE_HTML:
            return $this->convert_text_html($type);
        case Syncroton_Model_EmailBody::TYPE_RTF:
            return $this->convert_rtf($type);
        default:
            return $this->text;
        }
    }

    /**
     * Text/plain converter
     *
     * @param int $type Result data type (to which the string will be converted).
     *                  One of Syncroton_Model_EmailBody constants.
     *
     * @return string Body value
     */
    protected function convert_text_plain($type)
    {
        $data = $this->text;

        switch ($type) {
        case Syncroton_Model_EmailBody::TYPE_HTML:
            return '<pre>' . htmlspecialchars($data, ENT_COMPAT, kolab_sync::CHARSET) . '</pre>';
        case Syncroton_Model_EmailBody::TYPE_RTF:
            // @TODO
            return '';
        }

        return $data;
    }

    /**
     * HTML converter
     *
     * @param int $type Result data type (to which the string will be converted).
     *                  One of Syncroton_Model_EmailBody constants.
     *
     * @return string Body value
     */
    protected function convert_text_html($type)
    {
        switch ($type) {
        case Syncroton_Model_EmailBody::TYPE_PLAINTEXT:
            $txt = new rcube_html2text($this->text, false, true);
            return $txt->get_text();
        case Syncroton_Model_EmailBody::TYPE_RTF:
            // @TODO
            return '';
        case Syncroton_Model_EmailBody::TYPE_MIME:
            return '';
        }

        return $this->text;
    }

    /**
     * RTF converter
     *
     * @param int $type Result data type (to which the string will be converted).
     *                  One of Syncroton_Model_EmailBody constants.
     *
     * @return string Body value
     */
    protected function convert_rtf($type)
    {
        $rtf = new rtf();
        $rtf->loadrtf($this->text);

        switch ($type) {
        case Syncroton_Model_EmailBody::TYPE_PLAINTEXT:
            $rtf->output('ascii');
            $rtf->parse();
            return $rtf->out;
        case Syncroton_Model_EmailBody::TYPE_HTML:
            // @TODO: Conversion to HTML is broken,
            // convert to text and use <PRE> tags
            $rtf->output('ascii');
            $rtf->parse();
            return '<pre>' . trim($rtf->out) . '</pre>';
        }

        return $this->text;
    }
}
