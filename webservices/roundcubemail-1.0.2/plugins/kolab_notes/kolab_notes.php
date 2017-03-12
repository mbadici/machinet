<?php

/**
 * Kolab notes module
 *
 * Adds simple notes management features to the web client
 *
 * @version @package_version@
 * @author Thomas Bruederli <bruederli@kolabsys.com>
 *
 * Copyright (C) 2014, Kolab Systems AG <contact@kolabsys.com>
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

class kolab_notes extends rcube_plugin
{
    public $task = '?(?!login|logout).*';
    public $allowed_prefs = array('kolab_notes_sort_col');
    public $rc;

    private $ui;
    private $lists;
    private $folders;
    private $cache = array();
    private $message_notes = array();

    /**
     * Required startup method of a Roundcube plugin
     */
    public function init()
    {
        $this->require_plugin('libkolab');

        $this->rc = rcube::get_instance();

        $this->register_task('notes');

        // load plugin configuration
        $this->load_config();

        // proceed initialization in startup hook
        $this->add_hook('startup', array($this, 'startup'));
    }

    /**
     * Startup hook
     */
    public function startup($args)
    {
        // the notes module can be enabled/disabled by the kolab_auth plugin
        if ($this->rc->config->get('notes_disabled', false) || !$this->rc->config->get('notes_enabled', true)) {
            return;
        }

        // load localizations
        $this->add_texts('localization/', $args['task'] == 'notes' && (!$args['action'] || $args['action'] == 'dialog-ui'));
        $this->rc->load_language($_SESSION['language'], array('notes.notes' => $this->gettext('navtitle')));  // add label for task title

        if ($args['task'] == 'notes') {
            $this->add_hook('storage_init', array($this, 'storage_init'));

            // register task actions
            $this->register_action('index', array($this, 'notes_view'));
            $this->register_action('fetch', array($this, 'notes_fetch'));
            $this->register_action('get',   array($this, 'note_record'));
            $this->register_action('action', array($this, 'note_action'));
            $this->register_action('list',  array($this, 'list_action'));
            $this->register_action('dialog-ui', array($this, 'dialog_view'));
        }
        else if ($args['task'] == 'mail') {
            $this->add_hook('storage_init', array($this, 'storage_init'));
            $this->add_hook('message_load', array($this, 'mail_message_load'));
            $this->add_hook('message_compose', array($this, 'mail_message_compose'));
            $this->add_hook('template_object_messagebody', array($this, 'mail_messagebody_html'));

            // add 'Append note' item to message menu
            if ($this->api->output->type == 'html' && $_REQUEST['_rel'] != 'note') {
                $this->api->add_content(html::tag('li', null, 
                    $this->api->output->button(array(
                      'command'  => 'append-kolab-note',
                      'label'    => 'kolab_notes.appendnote',
                      'type'     => 'link',
                      'classact' => 'icon appendnote active',
                      'class'    => 'icon appendnote',
                      'innerclass' => 'icon note',
                    ))),
                    'messagemenu');

                $this->api->output->add_label('kolab_notes.appendnote', 'kolab_notes.editnote', 'kolab_notes.deletenotesconfirm', 'kolab_notes.entertitle', 'save', 'delete', 'cancel', 'close');
                $this->include_script('notes_mail.js');
            }
        }

        if (!$this->rc->output->ajax_call && (!$this->rc->output->env['framed'] || in_array($args['action'], array('folder-acl','dialog-ui')))) {
            $this->load_ui();
        }

        // notes use fully encoded identifiers
        kolab_storage::$encode_ids = true;
    }

    /**
     * Hook into IMAP FETCH HEADER.FIELDS command and request MESSAGE-ID
     */
    public function storage_init($p)
    {
        $p['fetch_headers'] = trim($p['fetch_headers'] . ' MESSAGE-ID');
        return $p;
    }

    /**
     * Load and initialize UI class
     */
    private function load_ui()
    {
        require_once($this->home . '/kolab_notes_ui.php');
        $this->ui = new kolab_notes_ui($this);
        $this->ui->init();
    }

    /**
     * Read available calendars for the current user and store them internally
     */
    private function _read_lists($force = false)
    {
        // already read sources
        if (isset($this->lists) && !$force)
            return $this->lists;

        // get all folders that have type "task"
        $folders = kolab_storage::sort_folders(kolab_storage::get_folders('note'));
        $this->lists = $this->folders = array();

        // find default folder
        $default_index = 0;
        foreach ($folders as $i => $folder) {
            if ($folder->default)
                $default_index = $i;
        }

        // put default folder on top of the list
        if ($default_index > 0) {
            $default_folder = $folders[$default_index];
            unset($folders[$default_index]);
            array_unshift($folders, $default_folder);
        }

        foreach ($folders as $folder) {
            $item = $this->folder_props($folder);
            $this->lists[$item['id']] = $item;
            $this->folders[$item['id']] = $folder;
            $this->folders[$folder->name] = $folder;
        }
    }

    /**
     * Get a list of available folders from this source
     */
    public function get_lists(&$tree = null)
    {
        $this->_read_lists();

        // attempt to create a default folder for this user
        if (empty($this->lists)) {
            $folder = array('name' => 'Notes', 'type' => 'note', 'default' => true, 'subscribed' => true);
            if (kolab_storage::folder_update($folder)) {
                $this->_read_lists(true);
            }
        }

        $folders = array();
        foreach ($this->lists as $id => $list) {
            if (!empty($this->folders[$id])) {
                $folders[] = $this->folders[$id];
            }
        }

        // include virtual folders for a full folder tree
        if (!is_null($tree)) {
            $folders = kolab_storage::folder_hierarchy($folders, $tree);
        }

        $delim = $this->rc->get_storage()->get_hierarchy_delimiter();

        $lists = array();
        foreach ($folders as $folder) {
            $list_id = $folder->id;
            $imap_path = explode($delim, $folder->name);

            // find parent
            do {
              array_pop($imap_path);
              $parent_id = kolab_storage::folder_id(join($delim, $imap_path));
            }
            while (count($imap_path) > 1 && !$this->folders[$parent_id]);

            // restore "real" parent ID
            if ($parent_id && !$this->folders[$parent_id]) {
                $parent_id = kolab_storage::folder_id($folder->get_parent());
            }

            $fullname = $folder->get_name();
            $listname = $folder->get_foldername();

            // special handling for virtual folders
            if ($folder instanceof kolab_storage_folder_user) {
                $lists[$list_id] = array(
                    'id'       => $list_id,
                    'name'     => $fullname,
                    'listname' => $listname,
                    'title'    => $folder->get_title(),
                    'virtual'  => true,
                    'editable' => false,
                    'group'    => 'other virtual',
                    'class'    => 'user',
                    'parent'   => $parent_id,
                );
            }
            else if ($folder->virtual) {
                $lists[$list_id] = array(
                    'id'       => $list_id,
                    'name'     => kolab_storage::object_name($fullname),
                    'listname' => $listname,
                    'virtual'  => true,
                    'editable' => false,
                    'group'    => $folder->get_namespace(),
                    'parent'   => $parent_id,
                );
            }
            else {
                if (!$this->lists[$list_id]) {
                    $this->lists[$list_id] = $this->folder_props($folder);
                    $this->folders[$list_id] = $folder;
                }
                $this->lists[$list_id]['parent'] = $parent_id;
                $lists[$list_id] = $this->lists[$list_id];
            }
        }

        return $lists;
    }

    /**
     * Search for shared or otherwise not listed folders the user has access
     *
     * @param string Search string
     * @param string Section/source to search
     * @return array List of notes folders
     */
    protected function search_lists($query, $source)
    {
        if (!kolab_storage::setup()) {
            return array();
        }

        $this->search_more_results = false;
        $this->lists = $this->folders = array();

        // find unsubscribed IMAP folders that have "event" type
        if ($source == 'folders') {
            foreach ((array)kolab_storage::search_folders('note', $query, array('other')) as $folder) {
                $this->folders[$folder->id] = $folder;
                $this->lists[$folder->id] = $this->folder_props($folder);
            }
        }
        // search other user's namespace via LDAP
        else if ($source == 'users') {
            $limit = $this->rc->config->get('autocomplete_max', 15) * 2;  // we have slightly more space, so display twice the number
            foreach (kolab_storage::search_users($query, 0, array(), $limit * 10) as $user) {
                $folders = array();
                // search for note folders shared by this user
                foreach (kolab_storage::list_user_folders($user, 'note', false) as $foldername) {
                    $folders[] = new kolab_storage_folder($foldername, 'note');
                }

                if (count($folders)) {
                    $userfolder = new kolab_storage_folder_user($user['kolabtargetfolder'], '', $user);
                    $this->folders[$userfolder->id] = $userfolder;
                    $this->lists[$userfolder->id] = $this->folder_props($userfolder);

                    foreach ($folders as $folder) {
                        $this->folders[$folder->id] = $folder;
                        $this->lists[$folder->id] = $this->folder_props($folder);
                        $count++;
                    }
                }

                if ($count >= $limit) {
                    $this->search_more_results = true;
                    break;
                }
            }

        }

        return $this->get_lists();
    }

    /**
     * Derive list properties from the given kolab_storage_folder object
     */
    protected function folder_props($folder)
    {
        if ($folder->get_namespace() == 'personal') {
            $norename = false;
            $readonly = false;
            $alarms = true;
        }
        else {
            $alarms = false;
            $readonly = true;
            if (($rights = $folder->get_myrights()) && !PEAR::isError($rights)) {
                if (strpos($rights, 'i') !== false)
                  $readonly = false;
            }
            $info = $folder->get_folder_info();
            $norename = $readonly || $info['norename'] || $info['protected'];
        }

        $list_id = $folder->id;
        return array(
            'id' => $list_id,
            'name' => $folder->get_name(),
            'listname' => $folder->get_foldername(),
            'editname' => $folder->get_foldername(),
            'editable' => !$readonly,
            'norename' => $norename,
            'parentfolder' => $folder->get_parent(),
            'subscribed' => (bool)$folder->is_subscribed(),
            'default'  => $folder->default,
            'group'    => $folder->default ? 'default' : $folder->get_namespace(),
            'class'    => trim($folder->get_namespace() . ($folder->default ? ' default' : '')),
        );
    }

    /**
     * Get the kolab_calendar instance for the given calendar ID
     *
     * @param string List identifier (encoded imap folder name)
     * @return object kolab_storage_folder Object nor null if list doesn't exist
     */
    public function get_folder($id)
    {
        // create list and folder instance if necesary
        if (!$this->lists[$id]) {
            $folder = kolab_storage::get_folder(kolab_storage::id_decode($id));
            if ($folder->type) {
                $this->folders[$id] = $folder;
                $this->lists[$id] = $this->folder_props($folder);
            }
        }

        return $this->folders[$id];
    }

    /*******  UI functions  ********/

    /**
     * Render main view of the tasklist task
     */
    public function notes_view()
    {
        $this->ui->init();
        $this->ui->init_templates();
        $this->rc->output->set_pagetitle($this->gettext('navtitle'));
        $this->rc->output->send('kolab_notes.notes');
    }

    /**
     * Deliver a rediced UI for inline (dialog)
     */
    public function dialog_view()
    {
        // resolve message reference
        if ($msgref = rcube_utils::get_input_value('_msg', RCUBE_INPUT_GPC, true)) {
            $storage = $this->rc->get_storage();
            list($uid, $folder) = explode('-', $msgref, 2);
            if ($message = $storage->get_message_headers($msgref)) {
                $this->rc->output->set_env('kolab_notes_template', array(
                    '_from_mail' => true,
                    'title' => $message->get('subject'),
                    'links' => array($this->get_message_reference($this->get_message_uri($message, $folder))),
                ));
            }
        }

        $this->ui->init_templates();
        $this->rc->output->send('kolab_notes.dialogview');
    }

    /**
     * Handler to retrieve note records for the given list and/or search query
     */
    public function notes_fetch()
    {
        $search = rcube_utils::get_input_value('_q', RCUBE_INPUT_GPC, true);
        $list   = rcube_utils::get_input_value('_list', RCUBE_INPUT_GPC);

        $data = $this->notes_data($this->list_notes($list, $search), $tags);

        $this->rc->output->command('plugin.data_ready', array(
                'list'   => $list,
                'search' => $search,
                'data'   => $data,
                'tags'   => array_values($tags)
        ));
    }

    /**
     * Convert the given note records for delivery to the client
     */
    protected function notes_data($records, &$tags)
    {
        $config = kolab_storage_config::get_instance();
        $tags   = $config->apply_tags($records);

        foreach ($records as $i => $rec) {
            unset($records[$i]['description']);
            $this->_client_encode($records[$i]);
        }

        return $records;
    }

    /**
     * Read note records for the given list from the storage backend
     */
    protected function list_notes($list_id, $search = null)
    {
        $results = array();

        // query Kolab storage
        $query = array();

        // full text search (only works with cache enabled)
        if (strlen($search)) {
            $words = array_filter(rcube_utils::normalize_string(mb_strtolower($search), true));
            foreach ($words as $word) {
                if (strlen($word) > 2) {  // only words > 3 chars are stored in DB
                    $query[] = array('words', '~', $word);
                }
            }
        }

        $this->_read_lists();
        if ($folder = $this->get_folder($list_id)) {
            foreach ($folder->select($query) as $record) {
                // post-filter search results
                if (strlen($search)) {
                    $matches = 0;
                    $contents = mb_strtolower(
                        $record['title'] .
                        ($this->is_html($record) ? strip_tags($record['description']) : $record['description'])
                    );
                    foreach ($words as $word) {
                        if (mb_strpos($contents, $word) !== false) {
                            $matches++;
                        }
                    }

                    // skip records not matching all search words
                    if ($matches < count($words)) {
                        continue;
                    }
                }
                $record['list'] = $list_id;
                $results[] = $record;
            }
        }

        return $results;
    }

    /**
     * Handler for delivering a full note record to the client
     */
    public function note_record()
    {
        $data = $this->get_note(array(
            'uid'  => rcube_utils::get_input_value('_id', RCUBE_INPUT_GPC),
            'list' => rcube_utils::get_input_value('_list', RCUBE_INPUT_GPC),
        ));

        // encode for client use
        if (is_array($data)) {
            $this->_client_encode($data, true);
        }

        $this->rc->output->command('plugin.render_note', $data);
    }

    /**
     * Get the full note record identified by the given UID + Lolder identifier
     */
    public function get_note($note)
    {
        if (is_array($note)) {
            $uid = $note['uid'] ?: $note['id'];
            $list_id = $note['list'];
        }
        else {
            $uid = $note;
        }

        // deliver from in-memory cache
        $key = $list_id . ':' . $uid;
        if ($this->cache[$key]) {
            return $this->cache[$key];
        }

        $result = false;

        $this->_read_lists();
        if ($list_id) {
            if ($folder = $this->get_folder($list_id)) {
                $result = $folder->get_object($uid);
            }
        }
        // iterate over all calendar folders and search for the event ID
        else {
            foreach ($this->folders as $list_id => $folder) {
                if ($result = $folder->get_object($uid)) {
                    $result['list'] = $list_id;
                    break;
                }
            }
        }

        if ($result) {
            // get note tags
            $result['tags'] = $this->get_tags($result['uid']);
        }

        return $result;
    }

    /**
     * Helper method to encode the given note record for use in the client
     */
    private function _client_encode(&$note, $resolve = false)
    {
        foreach ($note as $key => $prop) {
            if ($key[0] == '_' || $key == 'x-custom') {
                unset($note[$key]);
            }
        }

        foreach (array('created','changed') as $key) {
            if (is_object($note[$key]) && $note[$key] instanceof DateTime) {
                $note[$key.'_'] = $note[$key]->format('U');
                $note[$key] = $this->rc->format_date($note[$key]);
            }
        }

        // clean HTML contents
        if (!empty($note['description']) && $this->is_html($note)) {
            $note['html'] = $this->_wash_html($note['description']);
        }

        // resolve message links
        $me = $this;
        $note['links'] = array_map(function($link) use ($me, $resolve) {
                return $me->get_message_reference($link, $resolve) ?: array('uri' => $link);
            }, $this->get_links($note['uid']));

        return $note;
    }

    /**
     * Handler for client-initiated actions on a single note record
     */
    public function note_action()
    {
        $action = rcube_utils::get_input_value('_do', RCUBE_INPUT_POST);
        $note   = rcube_utils::get_input_value('_data', RCUBE_INPUT_POST, true);

        $success = false;
        switch ($action) {
            case 'new':
                $temp_id = $rec['tempid'];

            case 'edit':
                if ($success = $this->save_note($note)) {
                    $refresh = $this->get_note($note);
                    $refresh['tempid'] = $temp_id;
                }
                break;

            case 'move':
                $uids = explode(',', $note['uid']);
                foreach ($uids as $uid) {
                    $note['uid'] = $uid;
                    if (!($success = $this->move_note($note, $note['to']))) {
                        $refresh = $this->get_note($note);
                        break;
                    }
                }
                break;

            case 'delete':
                $uids = explode(',', $note['uid']);
                foreach ($uids as $uid) {
                    $note['uid'] = $uid;
                    if (!($success = $this->delete_note($note))) {
                        $refresh = $this->get_note($note);
                        break;
                    }
                }
                break;
        }

        // show confirmation/error message
        if ($success) {
            $this->rc->output->show_message('successfullysaved', 'confirmation');
        }
        else {
            $this->rc->output->show_message('errorsaving', 'error');
        }

        // unlock client
        $this->rc->output->command('plugin.unlock_saving');

        if ($refresh) {
            $this->rc->output->command('plugin.update_note', $this->_client_encode($refresh));
        }
    }

    /**
     * Update an note record with the given data
     *
     * @param array Hash array with note properties (id, list)
     * @return boolean True on success, False on error
     */
    private function save_note(&$note)
    {
        $this->_read_lists();

        $list_id = $note['list'];
        if (!$list_id || !($folder = $this->get_folder($list_id)))
            return false;

        // moved from another folder
        if ($note['_fromlist'] && ($fromfolder = $this->get_folder($note['_fromlist']))) {
            if (!$fromfolder->move($note['uid'], $folder->name))
                return false;

            unset($note['_fromlist']);
        }

        // load previous version of this record to merge
        if ($note['uid']) {
            $old = $folder->get_object($note['uid']);
            if (!$old || PEAR::isError($old))
                return false;

            // merge existing properties if the update isn't complete
            if (!isset($note['title']) || !isset($note['description']))
                $note += $old;
        }

        // generate new note object from input
        $object = $this->_write_preprocess($note, $old);

        // email links and tags are handled separately
        $links = $object['links'];
        $tags  = $object['tags'];

        unset($object['links']);
        unset($object['tags']);

        $saved = $folder->save($object, 'note', $note['uid']);

        if (!$saved) {
            raise_error(array(
                'code' => 600, 'type' => 'php',
                'file' => __FILE__, 'line' => __LINE__,
                'message' => "Error saving note object to Kolab server"),
                true, false);
            $saved = false;
        }
        else {
            // save links in configuration.relation object
            $this->save_links($object['uid'], $links);
            // save tags in configuration.relation object
            $this->save_tags($object['uid'], $tags);

            $note         = $object;
            $note['list'] = $list_id;
            $note['tags'] = (array) $tags;

            // cache this in memory for later read
            $key = $list_id . ':' . $note['uid'];
            $this->cache[$key] = $note;
        }

        return $saved;
    }

    /**
     * Move the given note to another folder
     */
    function move_note($note, $list_id)
    {
        $this->_read_lists();

        $tofolder   = $this->get_folder($list_id);
        $fromfolder = $this->get_folder($note['list']);

        if ($fromfolder && $tofolder) {
            return $fromfolder->move($note['uid'], $tofolder->name);
        }

        return false;
    }

    /**
     * Remove a single note record from the backend
     *
     * @param array   Hash array with note properties (id, list)
     * @param boolean Remove record irreversible (mark as deleted otherwise)
     * @return boolean True on success, False on error
     */
    public function delete_note($note, $force = true)
    {
        $this->_read_lists();

        $list_id = $note['list'];
        if (!$list_id || !($folder = $this->get_folder($list_id))) {
            return false;
        }

        $status = $folder->delete($note['uid'], $force);

        if ($status) {
            $this->save_links($note['uid'], null);
            $this->save_tags($note['uid'], null);
        }

        return $status;
    }

    /**
     * Handler for client requests to list (aka folder) actions
     */
    public function list_action()
    {
        $action  = rcube_utils::get_input_value('_do', RCUBE_INPUT_GPC);
        $list    = rcube_utils::get_input_value('_list', RCUBE_INPUT_GPC, true);
        $success = $update_cmd = false;

        if (empty($action)) {
            $action = rcube_utils::get_input_value('action', RCUBE_INPUT_GPC);
        }

        switch ($action) {
            case 'form-new':
            case 'form-edit':
                $this->_read_lists();
                echo $this->ui->list_editform($action, $this->lists[$list['id']], $this->folders[$list['id']]);
                exit;

            case 'new':
                $list['type'] = 'note';
                $list['subscribed'] = true;
                $folder = kolab_storage::folder_update($list);

                if ($folder === false) {
                    $save_error = $this->gettext(kolab_storage::$last_error);
                }
                else {
                    $success = true;
                    $update_cmd = 'plugin.update_list';
                    $list['id'] = kolab_storage::folder_id($folder);
                    $list['_reload'] = true;
                }
                break;

            case 'edit':
                $this->_read_lists();
                $oldparent = $this->lists[$list['id']]['parentfolder'];
                $newfolder = kolab_storage::folder_update($list);

                if ($newfolder === false) {
                  $save_error = $this->gettext(kolab_storage::$last_error);
                }
                else {
                    $success = true;
                    $update_cmd = 'plugin.update_list';
                    $list['newid'] = kolab_storage::folder_id($newfolder);
                    $list['_reload'] = $list['parent'] != $oldparent;

                    // compose the new display name
                    $delim = $this->rc->get_storage()->get_hierarchy_delimiter();
                    $path_imap = explode($delim, $newfolder);
                    $list['name'] = kolab_storage::object_name($newfolder);
                    $list['editname'] = rcube_charset::convert(array_pop($path_imap), 'UTF7-IMAP');
                    $list['listname'] = str_repeat('&nbsp;&nbsp;&nbsp;', count($path_imap)) . '&raquo; ' . $list['editname'];
                }
                break;

            case 'delete':
                $this->_read_lists();
                $folder = $this->get_folder($list['id']);
                if ($folder && kolab_storage::folder_delete($folder->name)) {
                    $success = true;
                    $update_cmd = 'plugin.destroy_list';
                }
                else {
                    $save_error = $this->gettext(kolab_storage::$last_error);
                }
                break;

            case 'search':
                $this->load_ui();
                $results = array();
                foreach ((array)$this->search_lists(rcube_utils::get_input_value('q', RCUBE_INPUT_GPC), rcube_utils::get_input_value('source', RCUBE_INPUT_GPC)) as $id => $prop) {
                    $editname = $prop['editname'];
                    unset($prop['editname']);  // force full name to be displayed

                    // let the UI generate HTML and CSS representation for this calendar
                    $html = $this->ui->folder_list_item($id, $prop, $jsenv, true);
                    $prop += (array)$jsenv[$id];
                    $prop['editname'] = $editname;
                    $prop['html'] = $html;

                    $results[] = $prop;
                }
                // report more results available
                if ($this->driver->search_more_results) {
                    $this->rc->output->show_message('autocompletemore', 'info');
                }

                $this->rc->output->command('multi_thread_http_response', $results, rcube_utils::get_input_value('_reqid', RCUBE_INPUT_GPC));
                return;

            case 'subscribe':
                $success = false;
                if ($list['id'] && ($folder = $this->get_folder($list['id']))) {
                    if (isset($list['permanent']))
                        $success |= $folder->subscribe(intval($list['permanent']));
                    if (isset($list['active']))
                        $success |= $folder->activate(intval($list['active']));
                }
                break;
        }

        $this->rc->output->command('plugin.unlock_saving');

        if ($success) {
            $this->rc->output->show_message('successfullysaved', 'confirmation');

            if ($update_cmd) {
                $this->rc->output->command($update_cmd, $list);
            }
        }
        else {
            $error_msg = $this->gettext('errorsaving') . ($save_error ? ': ' . $save_error :'');
            $this->rc->output->show_message($error_msg, 'error');
        }
    }

    /**
     * Hook to add note attachments to message compose if the according parameter is present.
     * This completes the 'send note by mail' feature.
     */
    public function mail_message_compose($args)
    {
        if (!empty($args['param']['with_notes'])) {
            $uids = explode(',', $args['param']['with_notes']);
            $list = $args['param']['notes_list'];
            $attachments = array();
            foreach ($uids as $uid) {
                if ($note = $this->get_note(array('uid' => $uid, 'list' => $list))) {
                    $args['attachments'][] = array(
                        'name'     => abbreviate_string($note['title'], 50, ''),
                        'mimetype' => 'message/rfc822',
                        'data'     => $this->note2message($note),
                    );

                    if (empty($args['param']['subject'])) {
                        $args['param']['subject'] = $note['title'];
                    }
                }
            }

            unset($args['param']['with_notes'], $args['param']['notes_list']);
        }

        return $args;
    }

    /**
     * Lookup backend storage and find notes associated with the given message
     */
    public function mail_message_load($p)
    {
        $this->message       = $p['object'];
        $this->message_notes = $this->get_message_notes($this->message->headers, $this->message->folder);
    }

    /**
     * Handler for 'messagebody_html' hook
     */
    public function mail_messagebody_html($args)
    {
        $html = '';
        foreach ($this->message_notes as $note) {
            $html .= html::a(array(
                'href' => $this->rc->url(array('task' => 'notes', '_list' => $note['list'], '_id' => $note['uid'])),
                'class' => 'kolabnotesref',
                'rel' => $note['uid'] . '@' . $note['list'],
                'target' => '_blank',
            ), Q($note['title']));
        }

        // prepend note links to message body
        if ($html) {
            $this->load_ui();
            $args['content'] = html::div('kolabmessagenotes', $html) . $args['content'];
        }

        return $args;
    }

    /**
     * Determine whether the given note is HTML formatted
     */
    private function is_html($note)
    {
        // check for opening and closing <html> or <body> tags
        return (preg_match('/<(html|body)(\s+[a-z]|>)/', $note['description'], $m) && strpos($note['description'], '</'.$m[1].'>') > 0);
    }

    /**
     * Build an RFC 822 message from the given note
     */
    private function note2message($note)
    {
        $message = new Mail_mime("\r\n");

        $message->setParam('text_encoding', '8bit');
        $message->setParam('html_encoding', 'quoted-printable');
        $message->setParam('head_encoding', 'quoted-printable');
        $message->setParam('head_charset', RCUBE_CHARSET);
        $message->setParam('html_charset', RCUBE_CHARSET);
        $message->setParam('text_charset', RCUBE_CHARSET);

        $message->headers(array(
            'Subject' => $note['title'],
            'Date' => $note['changed']->format('r'),
        ));

        if ($this->is_html($note)) {
            $message->setHTMLBody($note['description']);

            // add a plain text version of the note content as an alternative part.
            $h2t = new rcube_html2text($note['description'], false, true, 0, RCUBE_CHARSET);
            $plain_part = rcube_mime::wordwrap($h2t->get_text(), $this->rc->config->get('line_length', 72), "\r\n", false, RCUBE_CHARSET);
            $plain_part = trim(wordwrap($plain_part, 998, "\r\n", true));

            // make sure all line endings are CRLF
            $plain_part = preg_replace('/\r?\n/', "\r\n", $plain_part);

            $message->setTXTBody($plain_part);
        }
        else {
            $message->setTXTBody($note['description']);
        }

        return $message->getMessage();
    }

    private function save_links($uid, $links)
    {
        $config    = kolab_storage_config::get_instance();
        $search    = kolab_storage_config::build_member_url($uid);
        $relations = $this->get_relations($uid);

        // @TODO: here we support only one-way relations, i.e.
        // such relation can contain only note and mail members
        // So, when we remove a note member the whole relation
        // will be removed

        foreach ($relations as $relation) {
            if (empty($links)) {
                $config->delete($relation['uid']);
                $this->relations = null; // clear in-memory cache
            }
            else {
                // make relation members up-to-date
                kolab_storage_config::resolve_members($relation);

                // assign all links to one relation, others will be removed
                $members = array_merge($links, array($search));
                $diff1   = array_diff($members, $relation['members']);
                $diff1   = array_diff($relation['members'], $members);

                if (count($diff1) || count($diff2)) {
                    $relation['members'] = $members;
                    $config->save($relation, 'relation');
                    $this->relations = null; // clear in-memory cache
                }

                $links = null;
            }
        }

        // create a new relation
        if (!empty($links)) {
            $relation = array(
                'members'  => array_merge($links, array($search)),
                'category' => 'generic',
            );

            $config->save($relation, 'relation');
            $this->relations = null; // clear in-memory cache
        }
    }

    /**
     * Find messages assigned to specified note
     */
    private function get_links($uid)
    {
        $result = array();
        $search = kolab_storage_config::build_member_url($uid);

        foreach ($this->get_relations($uid) as $relation) {
            if (in_array($search, (array) $relation['members'])) {
                // make relation members up-to-date
                kolab_storage_config::resolve_members($relation);

                foreach ($relation['members'] as $member) {
                    if ($member != $search) {
                        $result[] = $member;
                    }
                }
            }
        }

        return array_unique($result);
    }

    /**
     * Get note tags
     */
    private function get_tags($uid)
    {
        $config = kolab_storage_config::get_instance();
        $tags   = $config->get_tags($uid);
        $tags   = array_map(function($v) { return $v['name']; }, $tags);

        return $tags;
    }

    /**
     * Find notes assigned to specified message
     */
    private function get_message_notes($message, $folder)
    {
        $result = array();
        $uids   = array();

        // TODO: only query for notes if message was flagged with $KolabNotes ?

        // get UIDs of assigned notes
        foreach ($this->get_relations() as $relation) {
            // get Folder/UIDs of relation members
            $messages = kolab_storage_config::resolve_members($relation);

            if (!empty($messages[$folder]) && in_array($message->uid, $messages[$folder])) {
                // find note UID(s)
                foreach ($relation['members'] as $member) {
                    if (strpos($member, 'urn:uuid:') === 0) {
                        $uids[] = substr($member, 9);
                    }
                }
            }
        }

        // get Note objects
        if (!empty($uids)) {
            $query = array(array('uid', '=', $uids));
            foreach (kolab_storage::select($query, 'note') as $record) {
                $record['list'] = kolab_storage::folder_id($record['_mailbox']);
                $result[] = $record;
            }
        }

        return $result;
    }

    /**
     * Find relation objects referring to specified note
     */
    private function get_relations($uid = null)
    {
        if (!isset($this->relations)) {
            $config      = kolab_storage_config::get_instance();
            $filter      = array(array('type', '=', 'relation'));
            $default     = true;
            $data_filter = array('category' => 'generic');

            $this->relations = $config->get_objects($filter, $default, $data_filter);
        }

        if ($uid === null) {
            return $this->relations;
        }

        $result = array();
        $search = kolab_storage_config::build_member_url($uid);

        foreach ($this->relations as $relation) {
            if (in_array($search, (array) $relation['members'])) {
                $result[] = $relation;
            }
        }

        return $result;
    }

    /**
     * Build a URI representing the given message reference
     */
    private function get_message_uri($headers, $folder)
    {
        $params = array(
            'folder' => $headers->folder ?: $folder,
            'uid'    => $headers->uid,
        );

        if (($messageid = $headers->get('message-id', false)) && ($date = $headers->get('date', false))) {
            $params['message-id'] = $messageid;
            $params['date']       = $date;

            if ($subject = $headers->get('subject')) {
                $params['subject'] = $subject;
            }
        }

        return kolab_storage_config::build_member_url($params);
    }

    /**
     * Resolve the email message reference from the given URI
     */
    public function get_message_reference($uri, $resolve = false)
    {
        if ($linkref = kolab_storage_config::parse_member_url($uri)) {
            $linkref['subject'] = $linkref['params']['subject'];
            $linkref['uri']     = $uri;
            $linkref['mailurl'] = $this->rc->url(array(
                'task'   => 'mail',
                'action' => 'show',
                'mbox'   => $linkref['folder'],
                'uid'    => $linkref['uid'],
                'rel'    => 'note',
            ));

            unset($linkref['params']);
        }

        return $linkref;
    }

    /**
     * Update note tags
     */
    private function save_tags($uid, $tags)
    {
        $config = kolab_storage_config::get_instance();
        $config->save_tags($uid, $tags);
    }

    /**
     * Process the given note data (submitted by the client) before saving it
     */
    private function _write_preprocess($note, $old = array())
    {
        $object = $note;

        // TODO: handle attachments

        // convert link references into simple URIs
        if (array_key_exists('links', $note)) {
            $object['links'] = array_map(function($link){ return is_array($link) ? $link['uri'] : strval($link); }, $note['links']);
        }
        else {
            $object['links'] = $old['links'];
        }

        // clean up HTML content
        $object['description'] = $this->_wash_html($note['description']);
        $is_html = true;

        // try to be smart and convert to plain-text if no real formatting is detected
        if (preg_match('!<body><(?:p|pre)>(.*)</(?:p|pre)></body>!Uims', $object['description'], $m)) {
            if (!preg_match('!<(a|b|i|strong|em|p|span|div|pre|li)(\s+[a-z]|>)!im', $m[1], $n) || !strpos($m[1], '</'.$n[1].'>')) {
                // $converter = new rcube_html2text($m[1], false, true, 0);
                // $object['description'] = rtrim($converter->get_text());
                $object['description'] = html_entity_decode(preg_replace('!<br(\s+/)>!', "\n", $m[1]));
                $is_html = false;
            }
        }

        // Add proper HTML header, otherwise Kontact renders it as plain text
        if ($is_html) {
            $object['description'] = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0//EN" "http://www.w3.org/TR/REC-html40/strict.dtd">'."\n" .
                str_replace('<head>', '<head><meta name="qrichtext" content="1" />', $object['description']);
        }

        // copy meta data (starting with _) from old object
        foreach ((array)$old as $key => $val) {
            if (!isset($object[$key]) && $key[0] == '_')
                $object[$key] = $val;
        }

        // make list of categories unique
        if (is_array($object['tags'])) {
            $object['tags'] = array_unique(array_filter($object['tags']));
        }

        unset($object['list'], $object['tempid'], $object['created'], $object['changed'], $object['created_'], $object['changed_']);
        return $object;
    }

    /**
     * Sanity checks/cleanups HTML content
     */
    private function _wash_html($html)
    {
        // Add header with charset spec., washtml cannot work without that
        $html = '<html><head>'
            . '<meta http-equiv="Content-Type" content="text/html; charset='.RCUBE_CHARSET.'" />'
            . '</head><body>' . $html . '</body></html>';

        // clean HTML with washtml by Frederic Motte
        $wash_opts = array(
            'show_washed'   => false,
            'allow_remote'  => 1,
            'charset'       => RCUBE_CHARSET,
            'html_elements' => array('html', 'head', 'meta', 'body', 'link'),
            'html_attribs'  => array('rel', 'type', 'name', 'http-equiv'),
        );

        // initialize HTML washer
        $washer = new rcube_washtml($wash_opts);

        $washer->add_callback('form', array($this, '_washtml_callback'));
        $washer->add_callback('a',    array($this, '_washtml_callback'));

        // Remove non-UTF8 characters
        $html = rcube_charset::clean($html);

        $html = $washer->wash($html);

        // remove unwanted comments (produced by washtml)
        $html = preg_replace('/<!--[^>]+-->/', '', $html);

        return $html;
    }

    /**
     * Callback function for washtml cleaning class
     */
    public function _washtml_callback($tagname, $attrib, $content, $washtml)
    {
        switch ($tagname) {
        case 'form':
            $out = html::div('form', $content);
            break;

        case 'a':
            // strip temporary link tags from plain-text markup
            $attrib = html::parse_attrib_string($attrib);
            if (!empty($attrib['class']) && strpos($attrib['class'], 'x-templink') !== false) {
                // remove link entirely
                if (strpos($attrib['href'], html_entity_decode($content)) !== false) {
                    $out = $content;
                    break;
                }
                $attrib['class'] = trim(str_replace('x-templink', '', $attrib['class']));
            }
            $out = html::a($attrib, $content);
            break;

        default:
            $out = '';
        }

        return $out;
    }

}
