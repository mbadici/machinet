<?php

/**
 +--------------------------------------------------------------------------+
 | Kolab Sync (ActiveSync for Kolab)                                        |
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

define('KOLAB_SYNC_START', microtime(true));

// Roundcube Framework constants
define('RCUBE_INSTALL_PATH', realpath(dirname(__FILE__) . '/../') . '/');
define('RCUBE_PLUGINS_DIR', RCUBE_INSTALL_PATH . 'lib/plugins/');

// Define include path
$include_path  = RCUBE_INSTALL_PATH . 'lib' . PATH_SEPARATOR;
$include_path .= RCUBE_INSTALL_PATH . 'lib/ext' . PATH_SEPARATOR;
$include_path .= ini_get('include_path');
set_include_path($include_path);

// @TODO: what is a reasonable value for ActiveSync?
@set_time_limit(600);

// include global functions from Roundcube Framework
require_once 'Roundcube/bootstrap.php';

// Register main autoloader
spl_autoload_register('kolab_sync_autoload');

// Autoloader for Syncroton
//require_once 'Zend/Loader/Autoloader.php';
//$autoloader = Zend_Loader_Autoloader::getInstance();
//$autoloader->setFallbackAutoloader(true);

/**
 * Use PHP5 autoload for dynamic class loading
 */
function kolab_sync_autoload($classname)
{
    // Syncroton, replacement for Zend autoloader
    $filename = str_replace('_', DIRECTORY_SEPARATOR, $classname);

    if ($fp = @fopen("$filename.php", 'r', true)) {
        fclose($fp);
        include_once "$filename.php";
        return true;
    }

    return false;
}
