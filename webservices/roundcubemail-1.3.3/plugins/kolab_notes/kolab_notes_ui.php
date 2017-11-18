<?php

class kolab_notes_ui
{
    private $rc;
    private $plugin;
    private $ready = false;

    function __construct($plugin)
    {
        $this->plugin = $plugin;
        $this->rc = $plugin->rc;
    }

    /**
    * Calendar UI initialization and requests handlers
    */
    public function init()
    {
        if ($this->ready)  // already done
            return;

        // add taskbar button
        $this->plugin->add_button(array(
            'command'    => 'notes',
            'class'      => 'button-notes',
            'classsel'   => 'button-notes button-selected',
            'innerclass' => 'button-inner',
            'label'      => 'kolab_notes.navtitle',
            'type'       => 'link'
        ), 'taskbar');

        $this->plugin->include_stylesheet($this->plugin->local_skin_path() . '/notes.css');

        $this->plugin->register_action('print', array($this, 'print_template'));
        $this->plugin->register_action('folder-acl', array($this, 'folder_acl'));

        $this->ready = true;
  }

    /**
    * Register handler methods for the template engine
    */
    public function init_templates()
    {
        $this->plugin->register_handler('plugin.tagslist', array($this, 'tagslist'));
        $this->plugin->register_handler('plugin.notebooks', array($this, 'folders'));
        #$this->plugin->register_handler('plugin.folders_select', array($this, 'folders_select'));
        $this->plugin->register_handler('plugin.searchform', array($this->rc->output, 'search_form'));
        $this->plugin->register_handler('plugin.listing', array($this, 'listing'));
        $this->plugin->register_handler('plugin.editform', array($this, 'editform'));
        $this->plugin->register_handler('plugin.notetitle', array($this, 'notetitle'));
        $this->plugin->register_handler('plugin.detailview', array($this, 'detailview'));
        $this->plugin->register_handler('plugin.attachments_list', array($this, 'attachments_list'));
        $this->plugin->register_handler('plugin.object_changelog_table', array('libkolab', 'object_changelog_table'));

        $this->rc->output->include_script('list.js');
        $this->rc->output->include_script('treelist.js');
        $this->plugin->include_script('notes.js');

        jqueryui::tagedit();

        // include kolab folderlist widget if available
        if (in_array('libkolab', $this->plugin->api->loaded_plugins())) {
            $this->plugin->api->include_script('libkolab/js/folderlist.js');
            $this->plugin->api->include_script('libkolab/js/audittrail.js');
        }

        // load config options and user prefs relevant for the UI
        $settings = array(
            'sort_col' => $this->rc->config->get('kolab_notes_sort_col', 'changed'),
            'print_template' => $this->rc->url('print'),
        );

        if ($list = rcube_utils::get_input_value('_list', rcube_utils::INPUT_GPC)) {
            $settings['selected_list'] = $list;
        }
        if ($uid = rcube_utils::get_input_value('_id', rcube_utils::INPUT_GPC)) {
            $settings['selected_uid'] = $uid;
        }

        $lang_codes = array($_SESSION['language']);
        $assets_dir = $this->rc->config->get('assets_dir') ?: INSTALL_PATH;

        if ($pos = strpos($_SESSION['language'], '_')) {
            $lang_codes[] = substr($_SESSION['language'], 0, $pos);
        }

        foreach ($lang_codes as $code) {
            if (file_exists("$assets_dir/program/js/tinymce/langs/$code.js")) {
                $lang = $code;
                break;
            }
        }

        if (empty($lang)) {
            $lang = 'en';
        }

        $settings['editor'] = array(
            'lang'       => $lang,
            'spellcheck' => intval($this->rc->config->get('enable_spellcheck')),
            'spelldict'  => intval($this->rc->config->get('spellcheck_dictionary'))
        );

        $this->rc->output->set_env('kolab_notes_settings', $settings);

        $this->rc->output->add_label('save','cancel','delete','close');
    }

    public function folders($attrib)
    {
        $attrib += array('id' => 'rcmkolabnotebooks');

        if ($attrib['type'] == 'select') {
            $attrib['is_escaped'] = true;
            $select = new html_select($attrib);
        }

        $tree = $attrib['type'] != 'select' ? true : null;
        $lists = $this->plugin->get_lists($tree);
        $jsenv = array();

        if (is_object($tree)) {
            $html = $this->folder_tree_html($tree, $lists, $jsenv, $attrib);
        }
        else {
            $html = '';
            foreach ($lists as $prop) {
                $id = $prop['id'];

                if (!$prop['virtual']) {
                    unset($prop['user_id']);
                    $jsenv[$id] = $prop;
                }

                if ($attrib['type'] == 'select') {
                    if ($prop['editable'] || strpos($prop['rights'], 'i') !== false) {
                        $select->add($prop['name'], $prop['id']);
                    }
                }
                else {
                    $html .= html::tag('li', array('id' => 'rcmliknb' . rcube_utils::html_identifier($id), 'class' => $prop['group']),
                        $this->folder_list_item($id, $prop, $jsenv)
                    );
                }
            }
        }

        $this->rc->output->set_env('kolab_notebooks', $jsenv);
        $this->rc->output->add_gui_object('notebooks', $attrib['id']);

        return $attrib['type'] == 'select' ? $select->show() : html::tag('ul', $attrib, $html, html::$common_attrib);
    }

    /**
     * Return html for a structured list <ul> for the folder tree
     */
    public function folder_tree_html($node, $data, &$jsenv, $attrib)
    {
        $out = '';
        foreach ($node->children as $folder) {
            $id = $folder->id;
            $prop = $data[$id];
            $is_collapsed = false; // TODO: determine this somehow?

            $content = $this->folder_list_item($id, $prop, $jsenv);

            if (!empty($folder->children)) {
                $content .= html::tag('ul', array('style' => ($is_collapsed ? "display:none;" : null)),
                    $this->folder_tree_html($folder, $data, $jsenv, $attrib));
            }

            if (strlen($content)) {
                $out .= html::tag('li', array(
                      'id' => 'rcmliknb' . rcube_utils::html_identifier($id),
                      'class' => $prop['group'] . ($prop['virtual'] ? ' virtual' : ''),
                    ),
                    $content);
            }
        }

        return $out;
    }

    /**
     * Helper method to build a tasklist item (HTML content and js data)
     */
    public function folder_list_item($id, $prop, &$jsenv, $checkbox = false)
    {
        if (!$prop['virtual']) {
            unset($prop['user_id']);
            $jsenv[$id] = $prop;
        }

        $classes = array('folder');
        if ($prop['virtual']) {
            $classes[] = 'virtual';
        }
        else if (!$prop['editable']) {
            $classes[] = 'readonly';
        }
        if ($prop['subscribed']) {
            $classes[] = 'subscribed';
        }
        if ($prop['class']) {
            $classes[] = $prop['class'];
        }

        $title = $prop['title'] ?: ($prop['name'] != $prop['listname'] || strlen($prop['name']) > 25 ?
          html_entity_decode($prop['name'], ENT_COMPAT, RCUBE_CHARSET) : '');

        $label_id = 'nl:' . $id;
        $attr = $prop['virtual'] ? array('tabindex' => '0') : array('href' => $this->rc->url(array('_list' => $id)));
        return html::div(join(' ', $classes),
            html::a($attr + array('class' => 'listname', 'title' => $title, 'id' => $label_id), $prop['listname'] ?: $prop['name']) .
            ($prop['virtual'] ? '' :
                ($checkbox ?
                    html::tag('input', array('type' => 'checkbox', 'name' => '_list[]', 'value' => $id, 'checked' => $prop['active'], 'aria-labelledby' => $label_id)) :
                    ''
                ) .
                html::span('handle', '') .
                html::span('actions',
                    (!$prop['default'] ?
                        html::a(array('href' => '#', 'class' => 'remove', 'title' => $this->plugin->gettext('removelist')), ' ') :
                        ''
                    ) .
                    (isset($prop['subscribed']) ?
                        html::a(array('href' => '#', 'class' => 'subscribed', 'title' => $this->plugin->gettext('foldersubscribe'), 'role' => 'checkbox', 'aria-checked' => $prop['subscribed'] ? 'true' : 'false'), ' ') :
                        ''
                    )
                )
            )
        );

        return '';
    }

    public function listing($attrib)
    {
        $attrib += array('id' => 'rcmkolabnoteslist');
        $this->rc->output->add_gui_object('noteslist', $attrib['id']);
        return html::tag('ul', $attrib, '', html::$common_attrib);
    }

    public function tagslist($attrib)
    {
        $attrib += array('id' => 'rcmkolabnotestagslist');
        $this->rc->output->add_gui_object('notestagslist', $attrib['id']);
        return html::tag('ul', $attrib, '', html::$common_attrib);
    }

    public function editform($attrib)
    {
        $attrib += array('action' => '#', 'id' => 'rcmkolabnoteseditform');

        $this->rc->output->add_gui_object('noteseditform', $attrib['id']);
        $this->rc->output->include_script('tinymce/tinymce.min.js');

        $textarea = new html_textarea(array('name' => 'content', 'id' => 'notecontent', 'cols' => 60, 'rows' => 20, 'tabindex' => 0));
        return html::tag('form', $attrib, $textarea->show(), array_merge(html::$common_attrib, array('action')));
    }

    public function detailview($attrib)
    {
        $attrib += array('id' => 'rcmkolabnotesdetailview');
        $this->rc->output->add_gui_object('notesdetailview', $attrib['id']);
        return html::div($attrib, '');
    }

    public function notetitle($attrib)
    {
        $attrib += array('id' => 'rcmkolabnotestitle');
        $this->rc->output->add_gui_object('noteviewtitle', $attrib['id']);

        $summary = new html_inputfield(array('name' => 'summary', 'class' => 'notetitle inline-edit', 'size' => 60, 'tabindex' => 0));

        $html = $summary->show();
        $html .= html::div(array('class' => 'tagline tagedit', 'style' => 'display:none'), '&nbsp;');
        $html .= html::div(array('class' => 'dates', 'style' => 'display:none'),
            html::label(array(), $this->plugin->gettext('created')) .
            html::span('notecreated', '') .
            html::label(array(), $this->plugin->gettext('changed')) .
            html::span('notechanged', '')
        );

        return html::div($attrib, $html);
    }

    public function attachments_list($attrib)
    {
        $attrib += array('id' => 'rcmkolabnotesattachmentslist');
        $this->rc->output->add_gui_object('notesattachmentslist', $attrib['id']);
        return html::tag('ul', $attrib, '', html::$common_attrib);
    }

    /**
     * Render edit for notes lists (folders)
     */
    public function list_editform($action, $list, $folder)
    {
        if (is_object($folder)) {
            $folder_name = $folder->name; // UTF7
        }
        else {
            $folder_name = '';
        }

        $hidden_fields[] = array('name' => 'oldname', 'value' => $folder_name);

        $storage = $this->rc->get_storage();
        $delim   = $storage->get_hierarchy_delimiter();
        $form   = array();

        if (strlen($folder_name)) {
            $options = $storage->folder_info($folder_name);

            $path_imap = explode($delim, $folder_name);
            array_pop($path_imap);  // pop off name part
            $path_imap = implode($path_imap, $delim);
        }
        else {
            $path_imap = '';
            $options = array();
        }

        // General tab
        $form['properties'] = array(
            'name' => $this->rc->gettext('properties'),
            'fields' => array(),
        );

        // folder name (default field)
        $input_name = new html_inputfield(array('name' => 'name', 'id' => 'noteslist-name', 'size' => 20));
        $form['properties']['fields']['name'] = array(
            'label' => $this->plugin->gettext('listname'),
            'value' => $input_name->show($list['editname'], array('disabled' => ($options['norename'] || $options['protected']))),
            'id' => 'noteslist-name',
        );

        // prevent user from moving folder
        if (!empty($options) && ($options['norename'] || $options['protected'])) {
            $hidden_fields[] = array('name' => 'parent', 'value' => $path_imap);
        }
        else {
            $select = kolab_storage::folder_selector('note', array('name' => 'parent', 'id' => 'parent-folder'), $folder_name);
            $form['properties']['fields']['path'] = array(
                'label' => $this->plugin->gettext('parentfolder'),
                'value' => $select->show(strlen($folder_name) ? $path_imap : ''),
                'id' => 'parent-folder',
            );
        }

        // add folder ACL tab
        if ($action != 'form-new') {
            $form['sharing'] = array(
                'name'    => rcube::Q($this->plugin->gettext('tabsharing')),
                'content' => html::tag('iframe', array(
                    'src' => $this->rc->url(array('_action' => 'folder-acl', '_folder' => $folder_name, 'framed' => 1)),
                    'width' => '100%',
                    'height' => 280,
                    'border' => 0,
                    'style' => 'border:0'),
                '')
            );
        }

        $form_html = '';
        if (is_array($hidden_fields)) {
            foreach ($hidden_fields as $field) {
                $hiddenfield = new html_hiddenfield($field);
                $form_html .= $hiddenfield->show() . "\n";
            }
        }

        // create form output
        foreach ($form as $tab) {
            if (is_array($tab['fields']) && empty($tab['content'])) {
                $table = new html_table(array('cols' => 2));
                foreach ($tab['fields'] as $col => $colprop) {
                    $label = !empty($colprop['label']) ? $colprop['label'] : $this->plugin->gettext($col);

                    $table->add('title', html::label($colprop['id'], rcube::Q($label)));
                    $table->add(null, $colprop['value']);
                }
                $content = $table->show();
            }
            else {
                $content = $tab['content'];
            }

            if (!empty($content)) {
                $form_html .= html::tag('fieldset', null, html::tag('legend', null, rcube::Q($tab['name'])) . $content) . "\n";
            }
        }

        return html::tag('form', array('action' => "#", 'method' => "post", 'id' => "noteslistpropform"), $form_html);
    }

    /**
     * Handler to render ACL form for a notes folder
     */
    public function folder_acl()
    {
        $this->plugin->require_plugin('acl');
        $this->rc->output->add_handler('folderacl', array($this, 'folder_acl_form'));
        $this->rc->output->send('kolab_notes.kolabacl');
    }

    /**
     * Handler for ACL form template object
     */
    public function folder_acl_form()
    {
        $folder = rcube_utils::get_input_value('_folder', rcube_utils::INPUT_GPC);

        if (strlen($folder)) {
            $storage = $this->rc->get_storage();
            $options = $storage->folder_info($folder);

            // get sharing UI from acl plugin
            $acl = $this->rc->plugins->exec_hook('folder_form',
                array('form' => array(), 'options' => $options, 'name' => $folder));
        }

        return $acl['form']['sharing']['content'] ?: html::div('hint', $this->plugin->gettext('aclnorights'));
    }

    /**
     * Render the template for printing with placeholders
     */
    public function print_template()
    {
        header('Content-Type: text/html; charset=' . RCUBE_CHARSET);
        $this->rc->output->reset(true);
        echo $this->rc->output->parse('kolab_notes.print', false, false);
        exit;
    }

}

