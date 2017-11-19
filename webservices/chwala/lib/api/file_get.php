<?php
/*
 +--------------------------------------------------------------------------+
 | This file is part of the Kolab File API                                  |
 |                                                                          |
 | Copyright (C) 2012-2014, Kolab Systems AG                                |
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

class file_api_file_get extends file_api_common
{
    protected $driver;

    /**
     * Request handler
     */
    public function handle()
    {
        parent::handle();

        $this->api->output_type = file_api_core::OUTPUT_HTML;

        if (!isset($this->args['file']) || $this->args['file'] === '') {
            header("HTTP/1.0 ".file_api_core::ERROR_CODE." Missing file name");
        }

        $method = $_SERVER['REQUEST_METHOD'];
        if ($method == 'POST' && !empty($_SERVER['HTTP_X_HTTP_METHOD'])) {
            $method = $_SERVER['HTTP_X_HTTP_METHOD'];
        }

        $params = array(
            'force-download' => rcube_utils::get_boolean((string) $this->args['force-download']),
            'force-type'     => $this->args['force-type'],
            'head'           => $this->args['head'] ?: $method == 'HEAD',
        );

        list($this->driver, $path) = $this->api->get_driver($this->args['file']);

        if (!empty($this->args['viewer'])) {
            $this->file_view($path, $this->args, $params);
        }

        try {
            $this->driver->file_get($path, $params);
        }
        catch (Exception $e) {
            header("HTTP/1.0 " . file_api_core::ERROR_CODE . " " . $e->getMessage());
        }

        exit;
    }

    /**
     * File vieweing request handler
     */
    protected function file_view($file, $args, $params)
    {
        $viewer = $args['viewer'];
        $path   = __DIR__ . "/../viewers/$viewer.php";
        $class  = "file_viewer_$viewer";

        if (!file_exists($path)) {
            return;
        }

        // get file info
        try {
            $info = $this->driver->file_info($file);
        }
        catch (Exception $e) {
            header("HTTP/1.0 " . file_api_core::ERROR_CODE . " " . $e->getMessage());
            exit;
        }

        include_once $path;
        $viewer = new $class($this->api);

        // check if specified viewer supports file type
        // otherwise return (fallback to file_get action)
        if (!$viewer->supports($info['type'])) {
            return;
        }

        $viewer->output($args['file'], $info);
        exit;
    }
}
