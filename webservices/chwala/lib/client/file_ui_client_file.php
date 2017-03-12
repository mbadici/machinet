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

class file_ui_client_file extends file_ui
{
    private $file;
    private $filedata;


    public function action_open()
    {
        $this->ui_init();

        // assign default set of translations
        $this->output->add_translation('saving', 'deleting');

        $this->file = $this->get_input('file', 'GET'); // @TODO: error handling

        // Set filename (without path?) as a page title
        $this->page_title    = $this->file;
        $this->task_template = 'file_open';

        // fetch file metadata
        $this->file_data();

        $this->output->set_env('file', $this->file);
        $this->output->set_env('filedata', $this->filedata);

        // read browser capabilities
        if (isset($_GET['caps'])) {
            $caps = explode(',', $_GET['caps']);
            $capabilities = array('pdf' => 0, 'flash' => 0, 'tif' => 0);
            foreach ($caps as $c) {
                $capabilities[$c] = 1;
            }

            $this->output->set_env('browser_capabilities', $capabilities);
        }
    }

    /**
     * File content template object
     */
    public function file_open_frame()
    {
        // check if viewer provides frame content
        if ($frame = $this->filedata['viewer']['frame']) {
            return $frame;
        }

        // src attribute will be set on page load
        return html::iframe(array('id' => 'file-content'));
    }

    /**
     * Fetch and parse file metadata
     */
    protected function file_data()
    {
        $response = $this->api_get('file_info', array('file' => $this->file,
            'viewer' => !empty($_GET['viewer'])));

        $this->filedata = $response->get(); // @TODO: error handling

        $mimetype = file_utils::real_mimetype($this->filedata['type']);

        // create href string for file load frame
        if ($href = $this->filedata['viewer']['href']) {
        }
        else {
            $href = 'api/?method=file_get'
                . '&file=' . urlencode($this->file)
                . '&token=' . urlencode($_SESSION['user']['token']);
        }

        $this->filedata['mimetype'] = $mimetype;
        $this->filedata['href']     = $href;
    }

    public function file_open_data()
    {
        $table = new html_table(array('cols' => 2));

        // file name
        $table->add('label', $this->translate('file.name').':');
        $table->add('data filename', html::quote($this->filedata['name']));

        // file type
        // @TODO: human-readable type name
        $table->add('label', $this->translate('file.type').':');
        $table->add('data filetype', html::quote($this->filedata['type']));

        // file size
        $table->add('label', $this->translate('file.size').':');
        $table->add('data filesize', html::quote($this->show_bytes($this->filedata['size'])));

        // file modification time
        $table->add('label', $this->translate('file.mtime').':');
        $table->add('data filemtime', html::quote($this->filedata['mtime']));

        // @TODO: for images: width, height, color depth, etc.
        // @TODO: for text files: count of characters, lines, words

        return $table->show();
    }
}
