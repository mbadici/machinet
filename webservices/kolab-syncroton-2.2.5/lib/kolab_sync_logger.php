<?php
/*
 +--------------------------------------------------------------------------+
 | Kolab Sync (ActiveSync for Kolab)                                        |
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
 +--------------------------------------------------------------------------+
*/

/**
 * Class for logging messages into log file(s)
 */
class kolab_sync_logger extends Zend_Log
{
    public $mode;

    /**
     * Constructor
     */
    function __construct($mode = null)
    {
        $this->mode = intval($mode);

        $r = new ReflectionClass($this);
        $this->_priorities = $r->getConstants();
    }

    public function __call($method, $params)
    {
        $method = strtoupper($method);
        if ($this->_priorities[$method] <= $this->mode) {
            $this->log(array_shift($params), $method);
        }
    }

    /**
     * Message logger
     *
     * @param string     $message Log message
     * @param int|string $method  Message severity
     */
    public function log($message, $method)
    {
        $rcube   = rcube::get_instance();
        $logfile = $rcube->config->get('activesync_log_file');
        $format  = $rcube->config->get('log_date_format', 'd-M-Y H:i:s O');
        $log_dir = $rcube->config->get('log_dir');

        if (is_numeric($method)) {
            $mode   = $method;
            $method = array_search($method, $this->_priorities);
        }
        else {
            $mode = $this->_priorities[$method];
        }

        if ($mode > $this->mode) {
            return;
        }

        // if log_file is configured all logs will go to it
        // otherwise use separate file for info/debug and warning/error
        if (!$logfile) {
            switch ($mode) {
            case self::DEBUG:
            case self::INFO:
            case self::NOTICE:
                $file = 'console';
                break;
            default:
                $file = 'errors';
                break;
            }

            $logfile = $log_dir . DIRECTORY_SEPARATOR . $file;
        }
        else if ($logfile[0] != '/') {
            $logfile = $log_dir . DIRECTORY_SEPARATOR . $logfile;
        }

        if (!is_string($message)) {
            $message = var_export($message, true);
        }

        // add user/request information to the log
        if ($mode <= self::WARN) {
            $device = array();
            $params = array('cmd' => 'Cmd', 'device' => 'DeviceId', 'type' => 'DeviceType');

            if (!empty($this->username)) {
                $device['user'] = $this->username;
            }

            foreach ($params as $key => $val) {
                if ($val = $_GET[$val]) {
                    $device[$key] = $val;
                }
            }

            if (!empty($device)) {
                $message = @json_encode($device) . ' ' . $message;
            }
        }

        $date    = date($format);
        $logline = sprintf("[%s]: [%s] %s\n", $date, $method, $message);

        if ($fp = @fopen($logfile, 'a')) {
            fwrite($fp, $logline);
            fflush($fp);
            fclose($fp);
            return;
        }

        if ($mode <= self::WARN) {
            // send error to PHPs error handler if write to file didn't succeed
            trigger_error($message, E_USER_ERROR);
        }
    }

    /**
     * Set current user name to add into error log
     */
    public function set_username($username)
    {
        $this->username = $username;
    }
}
