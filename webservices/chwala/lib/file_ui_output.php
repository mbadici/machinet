<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG                                |
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
 | Author: Jeroen van Meeuwen <vanmeeuwen@kolabsys.com>                     |
 +--------------------------------------------------------------------------+
*/

/**
 * Output functionality for Kolab File API UI
 */
class file_ui_output
{
    private $tpl_vars = array();
    private $env = array();
    private $objects = array();
    private $commands = array();
    private $labels = array();
    private $skin;

    /**
     * Class constructor.
     *
     * @param string $skin Interface skin name
     */
    public function __construct($skin = null)
    {
        $this->skin = $skin ? $skin : 'default';
        $this->init();
    }

    /**
     * Initialization.
     */
    private function init()
    {
        $conf = rcube::get_instance()->config;

        $smarty_path = array('Smarty', 'smarty3', 'smarty');

        if ($path = $conf->get('smarty_path')) {
            array_unshift($smarty_path, $path);
        }

        foreach ($smarty_path as $path) {
            @include_once "$path/Smarty.class.php";
            if (class_exists('Smarty', false)) {
                break;
            }
        }

        $SMARTY = new Smarty;

        $SMARTY->template_dir = 'skins/' . $this->skin . '/templates';
        $SMARTY->compile_dir  = RCUBE_INSTALL_PATH . '/cache';
        $SMARTY->plugins_dir  = RCUBE_INSTALL_PATH . '/lib/ext/Smarty/plugins/';
        $SMARTY->debugging    = false;

        $this->tpl = $SMARTY;
    }

    /**
     * Sends output to the browser.
     *
     * @param string $template HTML template name
     */
    public function send($template = null)
    {
        if ($this->is_ajax()) {
            echo $this->send_json();
        }
        else {
            $this->send_tpl($template);
        }
    }

    /**
     * JSON output.
     */
    private function send_json()
    {
        header('Content-Type: application/json');

        $response = array(
            'objects' => $this->objects,
            'env'     => array(),
        );

        foreach ($this->env as $name => $value) {
            $response['env'][$name] = $value;
        }

        foreach ($this->commands as $command) {
            $cname = array_shift($command);
            $args  = array();

            foreach ($command as $arg) {
                $args[] = json_encode($arg);
            }

            $commands[] = sprintf('ui.%s(%s);', $cname, implode(',', $args));
        }

        if (!empty($commands)) {
            $response['exec'] = implode("\n", $commands);
        }

        $this->labels = array_unique($this->labels);
        foreach ($this->labels as $label) {
            $response['labels'][$label] = file_ui::translate($label);
        }

        return json_encode($response);
    }

    /**
     * HTML output.
     *
     * @param string $template HTML template name
     */
    private function send_tpl($template)
    {
        if (!$template) {
            return;
        }

        foreach ($this->tpl_vars as $name => $value) {
            $this->tpl->assign($name, $value);
        }

        $script = '';

        if (!empty($this->env)) {
            $script[] = 'ui.set_env(' . json_encode($this->env) . ');';
        }

        $this->labels = array_unique($this->labels);
        if (!empty($this->labels)) {
            foreach ($this->labels as $label) {
                $labels[$label] = file_ui::translate($label);
            }
            $script[] = 'ui.tdef(' . json_encode($labels) . ');';
        }

        foreach ($this->commands as $command) {
            $cname = array_shift($command);
            $args  = array();

            foreach ($command as $arg) {
                $args[] = json_encode($arg);
            }

            $script[] = sprintf('ui.%s(%s);', $cname, implode(',', $args));
        }

        $this->tpl->assign('skin_path', 'skins/' . $this->skin . '/');
        if ($script) {
            $script = "<script type=\"text/javascript\">\n" . implode("\n", $script) . "\n</script>";
            $this->tpl->assign('script', $script);
        }

        $this->tpl->display($template . '.html');
    }

    /**
     * Request type checker.
     *
     * @return bool True on AJAX request, False otherwise
     */
    public function is_ajax()
    {
        return !empty($_REQUEST['remote']);
    }

    /**
     * Assigns value to a template variable.
     *
     * @param string $name  Variable name
     * @param mixed  $value Variable value
     */
    public function assign($name, $value)
    {
        $this->tpl_vars[$name] = $value;
    }

    /**
     * Get the value from the environment to be sent to the browser.
     *
     * @param string $name  Variable name
     *
     */
    public function get_env($name)
    {
        if (empty($this->env[$name])) {
            return null;
        } else {
            return $this->env[$name];
        }
    }

    /**
     * Assigns value to browser environment.
     *
     * @param string $name  Variable name
     * @param mixed  $value Variable value
     */
    public function set_env($name, $value)
    {
        $this->env[$name] = $value;
    }

    /**
     * Sets content of a HTML object.
     *
     * @param string $name        Object's identifier (HTML ID attribute)
     * @param string $content     Object's content
     * @param bool   $is_template Set to true if $content is a template name
     */
    public function set_object($name, $content, $is_template = false)
    {
        if ($is_template) {
            $content = $this->get_template($content);
        }

        $this->objects[$name] = $content;
    }

    /**
     * Returns content of a HTML object (set with set_object())
     *
     * @param string $name Object's identifier (HTML ID attribute)
     *
     * @return string Object content
     */
    public function get_object($name)
    {
        return $this->objects[$name];
    }

    /**
     * Returns HTML template output.
     *
     * @param string $name Template name
     *
     * @return string Template output
     */
    public function get_template($name)
    {
        ob_start();
        $this->send_tpl($name);
        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }

    /**
     * Sets javascript command (to be added to the request).
     */
    public function command()
    {
        $this->commands[] = func_get_args();
    }

    /**
     * Adds one or more translation labels to the browser env.
     */
    public function add_translation()
    {
        $this->labels = array_merge($this->labels, func_get_args());
    }

}
