<?php

// This file contains Chwala configuration options.
// Real config file must contain or include Roundcube Framework config.

// ------------------------------------------------
// Global settings
// ------------------------------------------------

// Main files source, backend driver which handles
// authentication and configuration of Chwala
// Note: Currently only 'kolab' is supported
include "/usr/share/roundcubemail/config/config.inc.php";

$config['fileapi_backend'] = 'kolab';

// Enabled external storage drivers
// Note: Currenty only 'seafile' and webdav is available
$config['fileapi_drivers'] = array( 'webdav');

// Pre-defined list of external storage sources.
// Here admins can define sources which will be "mounted" into users folder tree
/*
$config['fileapi_sources'] = array(
    'Seafile' => array(
        'driver' => 'seafile',
        'host'   => 'seacloud.cc',
        // when username is set to '%u' current user name and password
        // will be used to authenticate to this storage source
        'username' => '%u',
    ),
    'Public-Files' => array(
        'driver'   => 'webdav',
        'baseuri'  => 'https://some.host.tld/Files',
        'username' => 'admin',
        'password' => 'pass',
    ),
);
*/

// Default values for sources configuration dialog.
// Note: use driver names as the array keys.
// Note: %u variable will be resolved to the current username.
/*
$config['fileapi_presets'] = array(
    'seafile' => array(
        'host'     => 'seacloud.cc',
        'username' => '%u',
    ),
    'webdav' => array(
        'baseuri'  => 'https://some.host.tld/Files',
        'username' => '%u',
    ),
);
*/

// Manticore service URL. Enables use of WebODF collaborative editor.
// Note: this URL should be accessible from Chwala host and Roundcube host as well.
$config['fileapi_manticore'] = null;

// WOPI/Office service URL. Enables use of collaborative editor supporting WOPI.
// Note: this URL should be accessible from Chwala host and Roundcube host as well.
$config['fileapi_wopi_office'] = null;

// Kolab WOPI service URL. Enables use of collaborative editor supporting WOPI.
// Note: this URL should be accessible from Chwala host and Office host as well.
$config['fileapi_wopi_service'] = null;

// Name of the user interface skin.
$config['file_api_skin'] = 'default';

// Chwala UI communicates with Chwala API via HTTP protocol
// The URL here is a location of Chwala API service. By default
// the UI location is used with addition of /api/ suffix.
$config['file_api_url'] = '';

// Type of Chwala cache. Supported values: 'db', 'apc' and 'memcache'.
// Note: This is only for some additional data like WOPI capabilities.
$config['fileapi_cache'] = 'db';

// lifetime of Chwala cache
// possible units: s, m, h, d, w
$config['fileapi_cache_ttl'] = '1d';

// ------------------------------------------------
// SeaFile driver settings
// ------------------------------------------------

// Enables SeaFile Web API conversation log
$config['fileapi_seafile_debug'] = true;

// Enables caching of some SeaFile information e.g. folders list
// Note: 'db', 'apc' and 'memcache' are supported
$config['fileapi_seafile_cache'] = 'db';

// Expiration time of SeaFile cache entries
$config['fileapi_seafile_cache_ttl'] = '7d';

// Default SeaFile Web API host
// Note: http:// and https:// (default) prefixes can be used here
$config['fileapi_seafile_host'] = 'localhost';

// Enables SSL certificates validation when connecting
// with any SeaFile server
$config['fileapi_seafile_ssl_verify_host'] = false;
$config['fileapi_seafile_ssl_verify_peer'] = false;

// To support various Seafile configurations when fetching a file
// from Seafile server we proxy it via Chwala server.
// Enable this option to allow direct downloading of files
// from Seafile server to user browser.
$config['fileapi_seafile_allow_redirects'] = false;

// ------------------------------------------------
// WebDAV driver settings
// ------------------------------------------------

// Default URI location for WebDAV storage
$config['fileapi_webdav_baseuri'] = 'https://localhost/iRony';
