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

class file_ui_client_main extends file_ui
{
    public function action_default()
    {
        // assign default set of translations
        $this->output->add_translation('saving', 'deleting', 'search', 'search.loading',
            'collection.audio', 'collection.video', 'collection.image', 'collection.document',
            'moving', 'copying', 'file.skip', 'file.skipall', 'file.overwrite', 'file.overwriteall',
            'file.moveconfirm', 'file.progress', 'upload.size', 'upload.progress',
            'upload.eta', 'upload.rate'
        );

        $result = $this->api_get('mimetypes');

        $this->output->set_env('search_threads', $this->config->get('files_search_threads'));
        $this->output->set_env('supported_mimetypes', $result->get());

        $this->ui_init();
    }

    public function folder_create_form()
    {
        $input_name = new html_inputfield(array(
            'type'  => 'text',
            'name'  => 'name',
            'value' => '',
        ));
        $input_parent = new html_checkbox(array(
            'name'  => 'parent',
            'value' => '1',
            'id'    => 'folder-parent-checkbox',
        ));
        $submit = new html_inputfield(array(
            'type'    => 'button',
            'onclick' => 'ui.folder_create_submit()',
            'value'   => $this->translate('form.submit'),
        ));
        $cancel = new html_inputfield(array(
            'type'    => 'button',
            'onclick' => 'ui.folder_create_stop()',
            'value'   => $this->translate('form.cancel'),
        ));

        $table = new html_table;

        $table->add(null, $input_name->show() . $input_parent->show()
            . html::label('folder-parent-checkbox', $this->translate('folder.under')));
        $table->add('buttons', $submit->show() . $cancel->show());

        $content = html::tag('fieldset', null,
            html::tag('legend', null,
                $this->translate('folder.createtitle')) . $table->show());

        $form = html::tag('form', array(
            'id'       => 'folder-create-form',
            'onsubmit' => 'ui.folder_create_submit(); return false'),
            $content);

        return $form;
    }

    public function folder_edit_form()
    {
        $input_name = new html_inputfield(array(
            'type'  => 'text',
            'name'  => 'name',
            'value' => '',
        ));
        $submit = new html_inputfield(array(
            'type'    => 'button',
            'onclick' => 'ui.folder_edit_submit()',
            'value'   => $this->translate('form.submit'),
        ));
        $cancel = new html_inputfield(array(
            'type'    => 'button',
            'onclick' => 'ui.folder_edit_stop()',
            'value'   => $this->translate('form.cancel'),
        ));

        $table = new html_table;

        $table->add(null, $input_name->show());
        $table->add('buttons', $submit->show() . $cancel->show());

        $content = html::tag('fieldset', null,
            html::tag('legend', null,
                $this->translate('folder.edittitle')) . $table->show());

        $form = html::tag('form', array(
            'id'       => 'folder-edit-form',
            'onsubmit' => 'ui.folder_edit_submit(); return false'),
            $content);

        return $form;
    }

    public function file_search_form()
    {
        $input_name = new html_inputfield(array(
            'type'  => 'text',
            'name'  => 'name',
            'value' => '',
        ));
        $input_in1 = new html_inputfield(array(
            'type'  => 'radio',
            'name'  => 'all_folders',
            'value' => '0',
            'id'    => 'all-folders-radio1',
        ));
        $input_in2 = new html_inputfield(array(
            'type'  => 'radio',
            'name'  => 'all_folders',
            'value' => '1',
            'id'    => 'all-folders-radio2',
        ));
        $submit = new html_inputfield(array(
            'type'    => 'button',
            'onclick' => 'ui.file_search_submit()',
            'value'   => $this->translate('form.submit'),
        ));
        $cancel = new html_inputfield(array(
            'type'    => 'button',
            'onclick' => 'ui.file_search_stop()',
            'value'   => $this->translate('form.cancel'),
        ));

        $table = new html_table;

        $table->add(null, $input_name->show()
            . $input_in1->show() . html::label('all-folders-radio1', $this->translate('search.in_current_folder'))
            . $input_in2->show() . html::label('all-folders-radio2', $this->translate('search.in_all_folders'))
        );
        $table->add('buttons', $submit->show() . $cancel->show());

        $content = html::tag('fieldset', null,
            html::tag('legend', null,
                $this->translate('file.search')) . $table->show());

        $form = html::tag('form', array(
            'id'       => 'file-search-form',
            'onsubmit' => 'ui.file_search_submit(); return false'),
            $content);

        return $form;
    }
}
