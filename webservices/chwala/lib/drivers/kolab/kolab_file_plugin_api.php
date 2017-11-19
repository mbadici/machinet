<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2013, Kolab Systems AG                                |
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
 * The plugin loader and global API
 *
 * @package PluginAPI
 */
class kolab_file_plugin_api extends rcube_plugin_api
{
    /**
     * This implements the 'singleton' design pattern
     *
     * @return rcube_plugin_api The one and only instance if this class
     */
    static function get_instance()
    {
        if (!self::$instance) {
            self::$instance = new kolab_file_plugin_api();
        }

        return self::$instance;
    }


    /**
     * Initialize plugin engine
     *
     * This has to be done after rcmail::load_gui() or rcmail::json_init()
     * was called because plugins need to have access to rcmail->output
     *
     * @param object rcube Instance of the rcube base class
     * @param string Current application task (used for conditional plugin loading)
     */
    public function init($app, $task = '')
    {
        $this->task = $task;
    }


    /**
     * Register a handler function for template objects
     *
     * @param string $name Object name
     * @param string $owner Plugin name that registers this action
     * @param mixed  $callback Callback: string with global function name or array($obj, 'methodname')
     */
    public function register_handler($name, $owner, $callback)
    {
        // empty
    }


    /**
     * Register this plugin to be responsible for a specific task
     *
     * @param string $task Task name (only characters [a-z0-9_.-] are allowed)
     * @param string $owner Plugin name that registers this action
     */
    public function register_task($task, $owner)
    {
        $this->tasks[$task] = $owner;
    }


    /**
     * Include a plugin script file in the current HTML page
     *
     * @param string $fn Path to script
     */
    public function include_script($fn)
    {
        //empty
    }


    /**
     * Include a plugin stylesheet in the current HTML page
     *
     * @param string $fn Path to stylesheet
     */
    public function include_stylesheet($fn)
    {
        //empty
    }
}
