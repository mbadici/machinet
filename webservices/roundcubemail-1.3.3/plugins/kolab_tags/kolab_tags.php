<?php

/**
 * Kolab Tags
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

class kolab_tags extends rcube_plugin
{
    public $task = 'mail';
    public $rc;
    public $home;

    private $engine;

    public function init()
    {
        $this->rc = rcube::get_instance();

        // Register hooks to display tags in message subject
        $this->add_hook('messages_list', array($this, 'messages_list'));
        $this->add_hook('message_headers_output', array($this, 'message_headers_output'));

        // Searching by tags
        $this->add_hook('imap_search_before', array($this, 'imap_search_before'));

        // Plugin actions for tag management
        $this->register_action('plugin.kolab_tags', array($this, 'actions'));

        // Load UI from startup hook
        $this->add_hook('startup', array($this, 'startup'));
    }

    /**
     * Creates kolab_files_engine instance
     */
    private function engine()
    {
        if ($this->engine === null) {
            // the files module can be enabled/disabled by the kolab_auth plugin
            if ($this->rc->config->get('kolab_tags_disabled') || !$this->rc->config->get('kolab_tags_enabled', true)) {
                return $this->engine = false;
            }

//            $this->load_config();

            require_once $this->home . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'kolab_tags_engine.php';

            $this->engine = new kolab_tags_engine($this);
        }

        return $this->engine;
    }

    /**
     * Startup hook handler, initializes/enables Files UI
     */
    public function startup($args)
    {
        // call this from startup to give a chance to set
        // kolab_files_enabled/disabled in kolab_auth plugin
        if ($this->rc->output->type != 'html') {
            return;
        }

        if ($engine = $this->engine()) {
            $engine->ui();
        }
    }

    /**
     * Engine actions handler
     */
    public function actions()
    {
        if ($engine = $this->engine()) {
            $engine->actions();
        }
    }

    /**
     * Handler for messages list
     */
    public function messages_list($args)
    {
        if ($engine = $this->engine()) {
            $args = $engine->messages_list_handler($args);
        }

        return $args;
    }

    /**
     * Handler for message headers
     */
    public function message_headers_output($args)
    {
        // this hook can be executed many times
        if ($this->mail_headers_done) {
            return $args;
        }

        if ($this->rc->action == 'print') {
            return;
        }

        $this->mail_headers_done = true;

        if ($engine = $this->engine()) {
            $args = $engine->message_headers_handler($args);
        }

        return $args;
    }

    /**
     * Handler for messages searching
     */
    public function imap_search_before($args)
    {
        // if search filter contains tag mark
        if (preg_match('/^(kolab_tags_[0-9]{10,}:([^:]+):)/', $args['search'], $m) && ($engine = $this->engine())) {
            $this->current_tags   = $args['search_tags'] = explode(',', $m[2]);
            $this->current_filter = $args['search']      = substr($args['search'], strlen($m[1]));

            // modify search arguments
            $args = $engine->imap_search_handler($args);

            unset($args['search_tags']);

            // send current search properties to the browser
            $this->rc->output->set_env('search_filter_selected', $this->current_filter);
            $this->rc->output->set_env('selected_tags', $this->current_tags);
        }

        return $args;
    }
}
