<?php

/**
 * Type-aware folder management/listing for Kolab
 *
 * @author Aleksander Machniak <machniak@kolabsys.com>
 *
 * Copyright (C) 2011-2017, Kolab Systems AG <contact@kolabsys.com>
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

class kolab_folders extends rcube_plugin
{
    public $task = '?(?!login).*';

    public $types      = array('mail', 'event', 'journal', 'task', 'note', 'contact', 'configuration', 'file', 'freebusy');
    public $subtypes   = array(
        'mail'          => array('inbox', 'drafts', 'sentitems', 'outbox', 'wastebasket', 'junkemail'),
        'event'         => array('default', 'confidential', 'private'),
        'task'          => array('default', 'confidential', 'private'),
        'journal'       => array('default'),
        'note'          => array('default'),
        'contact'       => array('default'),
        'configuration' => array('default'),
        'file'          => array('default'),
        'freebusy'      => array('default'),
    );
    public $act_types  = array('event', 'task');

    private $rc;
    private static $instance;
    private $expire_annotation = '/shared/vendor/cmu/cyrus-imapd/expire';


    /**
     * Plugin initialization.
     */
    function init()
    {
        self::$instance = $this;
        $this->rc = rcube::get_instance();

        // load required plugin
        $this->require_plugin('libkolab');

        // Folder listing hooks
        $this->add_hook('storage_folders', array($this, 'mailboxes_list'));

        // Folder manager hooks
        $this->add_hook('folder_form', array($this, 'folder_form'));
        $this->add_hook('folder_update', array($this, 'folder_save'));
        $this->add_hook('folder_create', array($this, 'folder_save'));
        $this->add_hook('folder_delete', array($this, 'folder_save'));
        $this->add_hook('folder_rename', array($this, 'folder_save'));
        $this->add_hook('folders_list', array($this, 'folders_list'));

        // Special folders setting
        $this->add_hook('preferences_save', array($this, 'prefs_save'));

        // ACL plugin hooks
        $this->add_hook('acl_rights_simple', array($this, 'acl_rights_simple'));
        $this->add_hook('acl_rights_supported', array($this, 'acl_rights_supported'));
    }

    /**
     * Handler for mailboxes_list hook. Enables type-aware lists filtering.
     */
    function mailboxes_list($args)
    {
        // infinite loop prevention
        if ($this->is_processing) {
            return $args;
        }

        if (!$this->metadata_support()) {
            return $args;
        }

        $this->is_processing = true;

        // get folders
        $folders = kolab_storage::list_folders($args['root'], $args['name'], $args['filter'], $args['mode'] == 'LSUB', $folderdata);

        $this->is_processing = false;

        if (!is_array($folders)) {
            return $args;
        }

        // Create default folders
        if ($args['root'] == '' && $args['name'] = '*') {
            $this->create_default_folders($folders, $args['filter'], $folderdata, $args['mode'] == 'LSUB');
        }

        $args['folders'] = $folders;

        return $args;
    }

    /**
     * Handler for folders_list hook. Add css classes to folder rows.
     */
    function folders_list($args)
    {
        if (!$this->metadata_support()) {
            return $args;
        }

        // load translations
        $this->add_texts('localization/', false);

        // Add javascript script to the client
        $this->include_script('kolab_folders.js');

        $this->add_label('folderctype');
        foreach ($this->types as $type) {
            $this->add_label('foldertype' . $type);
        }

        $skip_namespace = $this->rc->config->get('kolab_skip_namespace');
        $skip_roots     = array();

        if (!empty($skip_namespace)) {
            $storage = $this->rc->get_storage();
            foreach ((array)$skip_namespace as $ns) {
                foreach((array)$storage->get_namespace($ns) as $root) {
                    $skip_roots[] = rtrim($root[0], $root[1]);
                }
            }
        }

        $this->rc->output->set_env('skip_roots', $skip_roots);
        $this->rc->output->set_env('foldertypes', $this->types);

        // get folders types
        $folderdata = kolab_storage::folders_typedata();

        if (!is_array($folderdata)) {
            return $args;
        }

        // Add type-based style for table rows
        // See kolab_folders::folder_class_name()
        if ($table = $args['table']) {
            for ($i=1, $cnt=$table->size(); $i<=$cnt; $i++) {
                $attrib = $table->get_row_attribs($i);
                $folder = $attrib['foldername']; // UTF7-IMAP
                $type   = $folderdata[$folder];

                if (!$type) {
                    $type = 'mail';
                }

                $class_name = self::folder_class_name($type);
                $attrib['class'] = trim($attrib['class'] . ' ' . $class_name);
                $table->set_row_attribs($attrib, $i);
            }
        }

        // Add type-based class for list items
        if (is_array($args['list'])) {
            foreach ((array)$args['list'] as $k => $item) {
                $folder = $item['folder_imap']; // UTF7-IMAP
                $type   = $folderdata[$folder];

                if (!$type) {
                    $type = 'mail';
                }

                $class_name = self::folder_class_name($type);
                $args['list'][$k]['class'] = trim($item['class'] . ' ' . $class_name);
            }
        }

        return $args;
    }

    /**
     * Handler for folder info/edit form (folder_form hook).
     * Adds folder type selector.
     */
    function folder_form($args)
    {
        if (!$this->metadata_support()) {
            return $args;
        }
        // load translations
        $this->add_texts('localization/', false);

        // INBOX folder is of type mail.inbox and this cannot be changed
        if ($args['name'] == 'INBOX') {
            $args['form']['props']['fieldsets']['settings']['content']['foldertype'] = array(
                'label' => $this->gettext('folderctype'),
                'value' => sprintf('%s (%s)', $this->gettext('foldertypemail'), $this->gettext('inbox')),
            );

            $this->add_expire_input($args['form'], 'INBOX');

            return $args;
        }

        if ($args['options']['is_root']) {
            return $args;
        }

        $mbox = strlen($args['name']) ? $args['name'] : $args['parent_name'];

        if (isset($_POST['_ctype'])) {
            $new_ctype   = trim(rcube_utils::get_input_value('_ctype', rcube_utils::INPUT_POST));
            $new_subtype = trim(rcube_utils::get_input_value('_subtype', rcube_utils::INPUT_POST));
        }

        // Get type of the folder or the parent
        if (strlen($mbox)) {
            list($ctype, $subtype) = $this->get_folder_type($mbox);
            if (strlen($args['parent_name']) && $subtype == 'default')
                $subtype = ''; // there can be only one
        }

        if (!$ctype) {
            $ctype = 'mail';
        }

        $storage = $this->rc->get_storage();

        // Don't allow changing type of shared folder, according to ACL
        if (strlen($mbox)) {
            $options = $storage->folder_info($mbox);
            if ($options['namespace'] != 'personal' && !in_array('a', (array)$options['rights'])) {
                if (in_array($ctype, $this->types)) {
                    $value = $this->gettext('foldertype'.$ctype);
                }
                else {
                    $value = $ctype;
                }
                if ($subtype) {
                    $value .= ' ('. ($subtype == 'default' ? $this->gettext('default') : $subtype) .')';
                }

                $args['form']['props']['fieldsets']['settings']['content']['foldertype'] = array(
                    'label' => $this->gettext('folderctype'),
                    'value' => $value,
                );

                return $args;
            }
        }

        // Add javascript script to the client
        $this->include_script('kolab_folders.js');

        // build type SELECT fields
        $type_select = new html_select(array('name' => '_ctype', 'id' => '_ctype',
            'onchange' => "\$('[name=\"_expire\"]').attr('disabled', \$(this).val() != 'mail')"
        ));
        $sub_select  = new html_select(array('name' => '_subtype', 'id' => '_subtype'));
        $sub_select->add('', '');

        foreach ($this->types as $type) {
            $type_select->add($this->gettext('foldertype'.$type), $type);
        }
        // add non-supported type
        if (!in_array($ctype, $this->types)) {
            $type_select->add($ctype, $ctype);
        }

        $sub_types = array();
        foreach ($this->subtypes as $ftype => $subtypes) {
            $sub_types[$ftype] = array_combine($subtypes, array_map(array($this, 'gettext'), $subtypes));

            // fill options for the current folder type
            if ($ftype == $ctype || $ftype == $new_ctype) {
                $sub_select->add(array_values($sub_types[$ftype]), $subtypes);
            }
        }

        $args['form']['props']['fieldsets']['settings']['content']['foldertype'] = array(
            'label' => $this->gettext('folderctype'),
            'value' => $type_select->show(isset($new_ctype) ? $new_ctype : $ctype)
                . $sub_select->show(isset($new_subtype) ? $new_subtype : $subtype),
        );

        $this->rc->output->set_env('kolab_folder_subtypes', $sub_types);
        $this->rc->output->set_env('kolab_folder_subtype', isset($new_subtype) ? $new_subtype : $subtype);

        $this->add_expire_input($args['form'], $args['name'], $ctype);

        return $args;
    }

    /**
     * Handler for folder update/create action (folder_update/folder_create hook).
     */
    function folder_save($args)
    {
        // Folder actions from folders list
        if (empty($args['record'])) {
            return $args;
        }

        // Folder create/update with form
        $ctype     = trim(rcube_utils::get_input_value('_ctype', rcube_utils::INPUT_POST));
        $subtype   = trim(rcube_utils::get_input_value('_subtype', rcube_utils::INPUT_POST));
        $mbox      = $args['record']['name'];
        $old_mbox  = $args['record']['oldname'];
        $subscribe = $args['record']['subscribe'];

        if (empty($ctype)) {
            return $args;
        }

        // load translations
        $this->add_texts('localization/', false);

        // Skip folder creation/rename in core
        // @TODO: Maybe we should provide folder_create_after and folder_update_after hooks?
        //        Using create_mailbox/rename_mailbox here looks bad
        $args['abort']  = true;

        // There can be only one default folder of specified type
        if ($subtype == 'default') {
            $default = $this->get_default_folder($ctype);

            if ($default !== null && $old_mbox != $default) {
                $args['result'] = false;
                $args['message'] = $this->gettext('defaultfolderexists');
                return $args;
            }
        }
        // Subtype sanity-checks
        else if ($subtype && (!($subtypes = $this->subtypes[$ctype]) || !in_array($subtype, $subtypes))) {
            $subtype = '';
        }

        $ctype .= $subtype ? '.'.$subtype : '';

        $storage = $this->rc->get_storage();

        // Create folder
        if (!strlen($old_mbox)) {
            // By default don't subscribe to non-mail folders
            if ($subscribe)
                $subscribe = (bool) preg_match('/^mail/', $ctype);

            $result = $storage->create_folder($mbox, $subscribe);
            // Set folder type
            if ($result) {
                $this->set_folder_type($mbox, $ctype);
            }
        }
        // Rename folder
        else {
            if ($old_mbox != $mbox) {
                $result = $storage->rename_folder($old_mbox, $mbox);
            }
            else {
                $result = true;
            }

            if ($result) {
                list($oldtype, $oldsubtype) = $this->get_folder_type($mbox);
                $oldtype .= $oldsubtype ? '.'.$oldsubtype : '';

                if ($ctype != $oldtype) {
                    $this->set_folder_type($mbox, $ctype);
                }
            }
        }

        // Set messages expiration in days
        if ($result && isset($_POST['_expire'])) {
            $expire = trim(rcube_utils::get_input_value('_expire', rcube_utils::INPUT_POST));
            $expire = intval($expire) && preg_match('/^mail/', $ctype) ? intval($expire) : null;

            $storage->set_metadata($mbox, array($this->expire_annotation => $expire));
        }

        $args['record']['class']     = self::folder_class_name($ctype);
        $args['record']['subscribe'] = $subscribe;
        $args['result'] = $result;

        return $args;
    }

    /**
     * Handler for user preferences save (preferences_save hook)
     *
     * @param array $args Hash array with hook parameters
     *
     * @return array Hash array with modified hook parameters
     */
    public function prefs_save($args)
    {
        if ($args['section'] != 'folders') {
            return $args;
        }

        $dont_override = (array) $this->rc->config->get('dont_override', array());

        // map config option name to kolab folder type annotation
        $opts = array(
            'drafts_mbox' => 'mail.drafts',
            'sent_mbox'   => 'mail.sentitems',
            'junk_mbox'   => 'mail.junkemail',
            'trash_mbox'  => 'mail.wastebasket',
        );

        // check if any of special folders has been changed
        foreach ($opts as $opt_name => $type) {
            $new = $args['prefs'][$opt_name];
            $old = $this->rc->config->get($opt_name);
            if (!strlen($new) || $new === $old || in_array($opt_name, $dont_override)) {
                unset($opts[$opt_name]);
            }
        }

        if (empty($opts)) {
            return $args;
        }

        $folderdata = kolab_storage::folders_typedata();

        if (!is_array($folderdata)) {
             return $args;
        }

        foreach ($opts as $opt_name => $type) {
            $foldername = $args['prefs'][$opt_name];

            // get all folders of specified type
            $folders = array_intersect($folderdata, array($type));

            // folder already annotated with specified type
            if (!empty($folders[$foldername])) {
                continue;
            }

            // set type to the new folder
            $this->set_folder_type($foldername, $type);

            // unset old folder(s) type annotation
            list($maintype, $subtype) = explode('.', $type);
            foreach (array_keys($folders) as $folder) {
                $this->set_folder_type($folder, $maintype);
            }
        }

        return $args;
    }

    /**
     * Handler for ACL permissions listing (acl_rights_simple hook)
     *
     * This shall combine the write and delete permissions into one item for
     * groupware folders as updating groupware objects is an insert + delete operation.
     *
     * @param array $args Hash array with hook parameters
     *
     * @return array Hash array with modified hook parameters
     */
    public function acl_rights_simple($args)
    {
        if ($args['folder']) {
            list($type,) = $this->get_folder_type($args['folder']);

            // we're dealing with a groupware folder here...
            if ($type && $type !== 'mail') {
                if ($args['rights']['write'] && $args['rights']['delete']) {
                    $write_perms = $args['rights']['write'] . $args['rights']['delete'];
                    $rw_perms    = $write_perms . $args['rights']['read'];

                    $args['rights']['write'] = $write_perms;
                    $args['rights']['other'] = preg_replace("/[$rw_perms]/", '', $args['rights']['other']);

                    // add localized labels and titles for the altered items
                    $args['labels'] = array(
                        'other'  => $this->rc->gettext('shortacla','acl'),
                    );
                    $args['titles'] = array(
                        'other'  => $this->rc->gettext('longaclother','acl'),
                    );
                }
            }
        }

        return $args;
    }

    /**
     * Handler for ACL permissions listing (acl_rights_supported hook)
     *
     * @param array $args Hash array with hook parameters
     *
     * @return array Hash array with modified hook parameters
     */
    public function acl_rights_supported($args)
    {
        if ($args['folder']) {
            list($type,) = $this->get_folder_type($args['folder']);

            // we're dealing with a groupware folder here...
            if ($type && $type !== 'mail') {
                // remove some irrelevant (for groupware objects) rights
                $args['rights'] = str_split(preg_replace('/[p]/', '', join('', $args['rights'])));
            }
        }

        return $args;
    }

    /**
     * Checks if IMAP server supports any of METADATA, ANNOTATEMORE, ANNOTATEMORE2
     *
     * @return boolean
     */
    function metadata_support()
    {
        $storage = $this->rc->get_storage();

        return $storage->get_capability('METADATA') ||
            $storage->get_capability('ANNOTATEMORE') ||
            $storage->get_capability('ANNOTATEMORE2');
    }

    /**
     * Checks if IMAP server supports any of METADATA, ANNOTATEMORE, ANNOTATEMORE2
     *
     * @param string $folder Folder name
     *
     * @return array Folder content-type
     */
    function get_folder_type($folder)
    {
        return explode('.', (string)kolab_storage::folder_type($folder));
    }

    /**
     * Sets folder content-type.
     *
     * @param string $folder Folder name
     * @param string $type   Content type
     *
     * @return boolean True on success
     */
    function set_folder_type($folder, $type = 'mail')
    {
        return kolab_storage::set_folder_type($folder, $type);
    }

    /**
     * Returns the name of default folder
     *
     * @param string $type Folder type
     *
     * @return string Folder name
     */
    function get_default_folder($type)
    {
        $folderdata = kolab_storage::folders_typedata();

        if (!is_array($folderdata)) {
            return null;
        }

        // get all folders of specified type
        $folderdata = array_intersect($folderdata, array($type.'.default'));

        return key($folderdata);
    }

    /**
     * Returns CSS class name for specified folder type
     *
     * @param string $type Folder type
     *
     * @return string Class name
     */
    static function folder_class_name($type)
    {
        list($ctype, $subtype) = explode('.', $type);

        $class[] = 'type-' . ($ctype ? $ctype : 'mail');

        if ($subtype)
            $class[] = 'subtype-' . $subtype;

        return implode(' ', $class);
    }

    /**
     * Creates default folders if they doesn't exist
     */
    private function create_default_folders(&$folders, $filter, $folderdata = null, $lsub = false)
    {
        $storage     = $this->rc->get_storage();
        $namespace   = $storage->get_namespace();
        $defaults    = array();
        $prefix      = '';

        // Find personal namespace prefix
        if (is_array($namespace['personal']) && count($namespace['personal']) == 1) {
            $prefix = $namespace['personal'][0][0];
        }

        $this->load_config();

        // get configured defaults
        foreach ($this->types as $type) {
            foreach ((array)$this->subtypes[$type] as $subtype) {
                $opt_name = 'kolab_folders_' . $type . '_' . $subtype;
                if ($folder = $this->rc->config->get($opt_name)) {
                    // convert configuration value to UTF7-IMAP charset
                    $folder = rcube_charset::convert($folder, RCUBE_CHARSET, 'UTF7-IMAP');
                    // and namespace prefix if needed
                    if ($prefix && strpos($folder, $prefix) === false && $folder != 'INBOX') {
                        $folder = $prefix . $folder;
                    }
                    $defaults[$type . '.' . $subtype] = $folder;
                }
            }
        }

        if (empty($defaults)) {
            return;
        }

        if ($folderdata === null) {
            $folderdata = kolab_storage::folders_typedata();
        }

        if (!is_array($folderdata)) {
            return;
        }

        // find default folders
        foreach ($defaults as $type => $foldername) {
            // get all folders of specified type
            $_folders = array_intersect($folderdata, array($type));

            // default folder found
            if (!empty($_folders)) {
                continue;
            }

            list($type1, $type2) = explode('.', $type);

            $activate = in_array($type1, $this->act_types);
            $exists   = false;
            $result   = false;

            // check if folder exists
            if (!empty($folderdata[$foldername]) || $foldername == 'INBOX') {
                $exists = true;
            }
            else if ((!$filter || $filter == $type1) && in_array($foldername, $folders)) {
                // this assumes also that subscribed folder exists
                $exists = true;
            }
            else {
                $exists = $storage->folder_exists($foldername);
            }

            // create folder
            if (!$exists) {
                $exists = $storage->create_folder($foldername);
            }

            // set type + subscribe + activate
            if ($exists) {
                if ($result = kolab_storage::set_folder_type($foldername, $type)) {
                    // check if folder is subscribed
                    if ((!$filter || $filter == $type1) && $lsub && in_array($foldername, $folders)) {
                        // already subscribed
                        $subscribed = true;
                    }
                    else {
                        $subscribed = $storage->subscribe($foldername);
                    }

                    // activate folder
                    if ($activate) {
                        kolab_storage::folder_activate($foldername, true);
                    }
                }
            }

            // add new folder to the result
            if ($result && (!$filter || $filter == $type1) && (!$lsub || $subscribed)) {
                $folders[] = $foldername;
            }
        }
    }

    /**
     * Static getter for default folder of the given type
     *
     * @param string $type Folder type
     * @return string Folder name
     */
    public static function default_folder($type)
    {
        return self::$instance->get_default_folder($type);
    }

    /**
     * Get /shared/vendor/cmu/cyrus-imapd/expire value
     *
     * @param string $folder IMAP folder name
     *
     * @return int|false The annotation value or False if not supported
     */
    private function get_expire_annotation($folder)
    {
        $storage = $this->rc->get_storage();

        if ($storage->get_vendor() != 'cyrus') {
            return false;
        }

        if (!strlen($folder)) {
            return 0;
        }

        $value = $storage->get_metadata($folder, $this->expire_annotation);

        if (is_array($value)) {
            return $value[$folder] ? intval($value[$folder][$this->expire_annotation]) : 0;
        }

        return false;
    }

    /**
     * Add expiration time input to the form if supported
     */
    private function add_expire_input(&$form, $folder, $type = null)
    {
        if (($expire = $this->get_expire_annotation($folder)) !== false) {
            $post    = trim(rcube_utils::get_input_value('_expire', rcube_utils::INPUT_POST));
            $is_mail = empty($type) || preg_match('/^mail/i', $type);
            $input   = new html_inputfield(array('name' => '_expire', 'size' => 3, 'disabled' => !$is_mail));

            if ($post && $is_mail) {
                $expire = (int) $post;
            }

            $form['props']['fieldsets']['settings']['content']['kolabexpire'] = array(
                'label' => $this->gettext('folderexpire'),
                'value' => str_replace('$x', $input->show($expire ?: ''), $this->gettext('xdays')),
            );
        }
    }
}
