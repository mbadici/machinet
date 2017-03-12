<?php

/**
 +--------------------------------------------------------------------------+
 | Kolab File API                                                           |
 |                                                                          |
 | Copyright (C) 2011-2012, Kolab Systems AG <contact@kolabsys.com>         |
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

// Roundcube Framework constants
define('FILE_API_START', microtime(true));
//define('RCUBE_INSTALL_PATH', realpath(dirname(__FILE__)) . '/../');
define('RCUBE_INSTALL_PATH', '/usr/share/chwala/');
define('RCUBE_CONFIG_DIR', RCUBE_INSTALL_PATH . 'config/');
define('RCUBE_PLUGINS_DIR', RCUBE_INSTALL_PATH . 'lib/kolab/plugins');

// Define include path
$include_path  = RCUBE_INSTALL_PATH . '/lib' . PATH_SEPARATOR;
$include_path .= RCUBE_INSTALL_PATH . '/lib/ext' . PATH_SEPARATOR;
$include_path .= RCUBE_INSTALL_PATH . '/lib/client' . PATH_SEPARATOR;
$include_path .= ini_get('include_path');
set_include_path($include_path);

// include global functions from Roundcube Framework
require_once 'Roundcube/bootstrap.php';
