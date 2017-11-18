<?php

/**
 * Kolab Tags engine
 *
 * @author Aleksander Machniak <machniak@kolabsys.com>
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

class kolab_tags_engine
{
    private $backend;
    private $plugin;
    private $rc;

    /**
     * Class constructor
     */
    public function __construct($plugin)
    {
        $plugin->require_plugin('libkolab');

        require_once $plugin->home . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'kolab_tags_backend.php';

        $this->backend = new kolab_tags_backend;
        $this->plugin  = $plugin;
        $this->rc      = $plugin->rc;
    }

    /**
     * User interface initialization
     */
    public function ui()
    {
        // set templates of Files UI and widgets
        if ($this->rc->task != 'mail') {
            return;
        }

        if ($this->rc->action && !in_array($this->rc->action, array('show', 'preview'))) {
            return;
        }

        $this->plugin->add_texts('localization/');

        $this->plugin->include_stylesheet($this->plugin->local_skin_path().'/style.css');
        $this->plugin->include_script('kolab_tags.js');
        $this->rc->output->add_label('cancel', 'save');
        $this->plugin->add_label('tags', 'add', 'edit', 'delete', 'saving',
            'nameempty', 'nameexists', 'colorinvalid', 'untag', 'tagname',
            'tagcolor', 'tagsearchnew', 'newtag');

        $this->rc->output->add_handlers(array(
            'plugin.taglist' => array($this, 'taglist'),
        ));

        $ui = $this->rc->output->parse('kolab_tags.ui', false, false);
        $this->rc->output->add_footer($ui);

        // load miniColors
        jqueryui::miniColors();

        // Modify search filter (and set selected tags)
        if ($this->rc->action == 'show' || !$this->rc->action) {
            $this->search_filter_mods();
        }
    }

    /**
     * Engine actions handler (managing tag objects)
     */
    public function actions()
    {
        $this->plugin->add_texts('localization/');

        $action = rcube_utils::get_input_value('_act', rcube_utils::INPUT_POST);

        if ($action) {
            $this->{'action_' . $action}();
        }
        // manage tag objects
        else {
            $delete   = (array) rcube_utils::get_input_value('delete', rcube_utils::INPUT_POST);
            $update   = (array) rcube_utils::get_input_value('update', rcube_utils::INPUT_POST, true);
            $add      = (array) rcube_utils::get_input_value('add', rcube_utils::INPUT_POST, true);
            $response = array();

            // tags deletion
            foreach ($delete as $uid) {
                if ($this->backend->remove($uid)) {
                    $response['delete'][] = $uid;
                }
                else {
                    $error = true;
                }
            }

            // tags creation
            foreach ($add as $tag) {
                if ($tag = $this->backend->create($tag)) {
                    $response['add'][] = $this->parse_tag($tag);
                }
                else {
                    $error = true;
                }
            }

            // tags update
            foreach ($update as $tag) {
                if ($this->backend->update($tag)) {
                    $response['update'][] = $this->parse_tag($tag);
                }
                else {
                    $error = true;
                }
            }

            if (!empty($error)) {
                $this->rc->output->show_message($this->plugin->gettext('updateerror'), 'error');
            }
            else {
                $this->rc->output->show_message($this->plugin->gettext('updatesuccess'), 'confirmation');
            }

            $this->rc->output->command('plugin.kolab_tags', $response);
        }


        $this->rc->output->send();
    }

    /**
     * Remove tag from message(s)
     */
    public function action_remove()
    {
        $tag     = rcube_utils::get_input_value('_tag', rcube_utils::INPUT_POST);
        $filter  = $tag == '*' ? array() : array(array('uid', '=', explode(',', $tag)));
        $taglist = $this->backend->list_tags($filter);
        $filter  = array();
        $tags    = array();

        foreach (rcmail::get_uids() as $mbox => $uids) {
            if ($uids === '*') {
                $filter[$mbox] = $this->build_member_url(array('folder' => $mbox));
            }
            else {
                foreach ((array)$uids as $uid) {
                    $filter[$mbox][] = $this->build_member_url(array(
                            'folder' => $mbox,
                            'uid'    => $uid
                    ));
                }
            }
        }

        // for every tag...
        foreach ($taglist as $tag) {
            $updated = false;

            // @todo: make sure members list is up-to-date (UIDs are up-to-date)

            // ...filter members by folder/uid prefix
            foreach ((array) $tag['members'] as $idx => $member) {
                foreach ($filter as $members) {
                    // list of prefixes
                    if (is_array($members)) {
                        foreach ($members as $message) {
                            if ($member == $message || strpos($member, $message . '?') === 0) {
                                unset($tag['members'][$idx]);
                                $updated = true;
                            }
                        }
                    }
                    // one prefix (all messages in a folder)
                    else {
                        if (preg_match('/^' . preg_quote($members, '/') . '\/[0-9]+(\?|$)/', $member)) {
                            unset($tag['members'][$idx]);
                            $updated = true;
                        }
                    }
                }
            }

            // update tag object
            if ($updated) {
                if (!$this->backend->update($tag)) {
                    $error = true;
                }
            }

            $tags[] = $tag['uid'];
        }

        if ($error) {
            if ($_POST['_from'] != 'show') {
                $this->rc->output->show_message($this->plugin->gettext('untaggingerror'), 'error');
                $this->rc->output->command('list_mailbox');
            }
        }
        else {
            $this->rc->output->show_message($this->plugin->gettext('untaggingsuccess'), 'confirmation');
            $this->rc->output->command('plugin.kolab_tags', array('mark' => 1, 'delete' => $tags));
        }
    }

    /**
     * Add tag to message(s)
     */
    public function action_add()
    {
        $tag     = rcube_utils::get_input_value('_tag', rcube_utils::INPUT_POST);
        $storage = $this->rc->get_storage();
        $members = array();

        // build list of members
        foreach (rcmail::get_uids() as $mbox => $uids) {
            if ($uids === '*') {
                $index = $storage->index($mbox, null, null, true);
                $uids  = $index->get();
                $msgs  = $storage->fetch_headers($mbox, $uids, false);
            }
            else {
                $msgs = $storage->fetch_headers($mbox, $uids, false);
            }

            $members = array_merge($members, $this->build_members($mbox, $msgs));
        }

        // create a new tag?
        if (!empty($_POST['_new'])) {
            $object = array(
                'name'    => $tag,
                'members' => $members,
            );

            $object = $this->backend->create($object);
            $error  = $object === false;
        }
        // use existing tags (by UID)
        else {
            $filter  = array(array('uid', '=', explode(',', $tag)));
            $taglist = $this->backend->list_tags($filter);

            // for every tag...
            foreach ($taglist as $tag) {
                $tag['members'] = array_unique(array_merge((array) $tag['members'], $members));

                // update tag object
                if (!$this->backend->update($tag)) {
                    $error = true;
                }
            }
        }

        if ($error) {
            $this->rc->output->show_message($this->plugin->gettext('taggingerror'), 'error');

            if ($_POST['_from'] != 'show') {
                $this->rc->output->command('list_mailbox');
            }
        }
        else {
            $this->rc->output->show_message($this->plugin->gettext('taggingsuccess'), 'confirmation');

            if (isset($object)) {
                $this->rc->output->command('plugin.kolab_tags', array('mark' => 1, 'add' => array($this->parse_tag($object))));
            }
        }
    }

    /**
     * Template object building tags list/cloud
     */
    public function taglist($attrib)
    {
        $taglist = $this->backend->list_tags();

        // Performance: Save the list for later
        if ($this->rc->action == 'show' || $this->rc->action == 'preview') {
            $this->taglist = $taglist;
        }

        $taglist = array_map(array($this, 'parse_tag'), $taglist);

        $this->rc->output->set_env('tags', $taglist);
        $this->rc->output->add_gui_object('taglist', $attrib['id']);

        return html::tag('ul', $attrib, '', html::$common_attrib);
    }

    /**
     * Handler for messages list (add tag-boxes in subject line on the list)
     */
    public function messages_list_handler($args)
    {
        if (empty($args['messages'])) {
            return;
        }

        // get tags list
        $taglist = $this->backend->list_tags();

        // get message UIDs
        foreach ($args['messages'] as $msg) {
            $message_tags[$msg->uid . '-' . $msg->folder] = null;
        }

        $uids = array_keys($message_tags);

        foreach ($taglist as $tag) {
            $tag = $this->parse_tag($tag, true);

            foreach ((array) $tag['uids'] as $folder => $_uids) {
                array_walk($_uids, function(&$uid, $key, $folder) { $uid .= '-' . $folder; }, $folder);

                foreach (array_intersect($uids, $_uids) as $uid) {
                    $message_tags[$uid][] = $tag['uid'];
                }
            }
        }

        $this->rc->output->set_env('message_tags', array_filter($message_tags));

        // @TODO: tag counters for the whole folder (search result)

        return $args;
    }

    /**
     * Handler for a single message (add tag-boxes in subject line)
     */
    public function message_headers_handler($args)
    {
        $taglist = $this->taglist ?: $this->backend->list_tags();
        $uid     = $args['uid'];
        $folder  = $args['folder'];
        $tags    = array();

        foreach ($taglist as $tag) {
            $tag = $this->parse_tag($tag, true, false);
            if (in_array($uid, (array)$tag['uids'][$folder])) {
                unset($tag['uids']);
                $tags[] = $tag;
            }
        }

        if (!empty($tags)) {
            $this->rc->output->set_env('message_tags', $tags);
        }

        return $args;
    }

    /**
     * Handler for messages searching requests
     */
    public function imap_search_handler($args)
    {
        if (empty($args['search_tags'])) {
            return $args;
        }

        // we'll reset to current folder to fix issues when searching in multi-folder mode
        $storage     = $this->rc->get_storage();
        $orig_folder = $storage->get_folder();

        // get tags
        $tags = $this->backend->list_tags(array(array('uid', '=', $args['search_tags'])));

        // sanity check (that should not happen)
        if (empty($tags)) {
            if ($orig_folder) {
                $storage->set_folder($orig_folder);
            }

            return $args;
        }

        $search  = array();
        $folders = (array) $args['folder'];

        // collect folders and uids
        foreach ($tags as $tag) {
            $tag = $this->parse_tag($tag, true);

            // tag has no members -> empty search result
            if (empty($tag['uids'])) {
                goto empty_result;
            }

            foreach ($tag['uids'] as $folder => $uid_list) {
                $search[$folder] = array_merge((array)$search[$folder], $uid_list);
            }
        }

        $search   = array_map('array_unique', $search);
        $criteria = array();

        // modify search folders/criteria
        $args['folder'] = array_intersect($folders, array_keys($search));

        foreach ($args['folder'] as $folder) {
            $criteria[$folder] = ($args['search'] != 'ALL' ? trim($args['search']).' ' : '')
                . 'UID ' . rcube_imap_generic::compressMessageSet($search[$folder]);
        }

        if (!empty($args['folder'])) {
            $args['search'] = $criteria;
        }
        else {
            // return empty result
            empty_result:

            if (count($folders) > 1) {
                $args['result'] = new rcube_result_multifolder($args['folder']);
                foreach ($args['folder'] as $folder) {
                    $index = new rcube_result_index($folder, '* SORT');
                    $args['result']->add($index);
                }
            }
            else {
                $class  = 'rcube_result_' . ($args['threading'] ? 'thread' : 'index');
                $result = $args['threading'] ? '* THREAD' : '* SORT';

                $args['result'] = new $class($folder, $result);
            }
        }

        if ($orig_folder) {
            $storage->set_folder($orig_folder);
        }

        return $args;
    }

    /**
     * Get selected tags when in search-mode
     */
    protected function search_filter_mods()
    {
       if (!empty($_REQUEST['_search']) && !empty($_SESSION['search'])
            && $_SESSION['search_request'] == $_REQUEST['_search']
            && ($filter = $_SESSION['search_filter'])
       ) {
            if (preg_match('/^(kolab_tags_[0-9]{10,}:([^:]+):)/', $filter, $m)) {
                $search_tags   = explode(',', $m[2]);
                $search_filter = substr($filter, strlen($m[1]));

                // send current search properties to the browser
                $this->rc->output->set_env('search_filter_selected', $search_filter);
                $this->rc->output->set_env('selected_tags', $search_tags);
            }
        }
    }

    /**
     * "Convert" tag object to simple array for use in javascript
     */
    private function parse_tag($tag, $list = false, $force = true)
    {
        $result = array(
            'uid'   => $tag['uid'],
            'name'  => $tag['name'],
            'color' => $tag['color'],
        );

        if ($list) {
            $result['uids'] = $this->get_tag_messages($tag, $force);
        }

        return $result;
    }

    /**
     * Resolve members to folder/UID
     *
     * @param array $tag Tag object
     *
     * @return array Folder/UID list
     */
    protected function get_tag_messages(&$tag, $force = true)
    {
        return kolab_storage_config::resolve_members($tag, $force);
    }

    /**
     * Build array of member URIs from set of messages
     */
    protected function build_members($folder, $messages)
    {
        return kolab_storage_config::build_members($folder, $messages);
    }

    /**
     * Parses tag member string
     *
     * @param string $url Member URI
     *
     * @return array Message folder, UID, Search headers (Message-Id, Date)
     */
    protected function parse_member_url($url)
    {
        return kolab_storage_config::parse_member_url($url);
    }

    /**
     * Builds member URI
     *
     * @param array Message folder, UID, Search headers (Message-Id, Date)
     *
     * @return string $url Member URI
     */
    protected function build_member_url($params)
    {
        return kolab_storage_config::build_member_url($params);
    }
}
