<?php

// Kolab Cockpit API location. Defaults to hostname from the HTTP request
$config['kolab_api_url'] = null;

// Enable logging of Cockpit API requests/responses (to logs/kolab.log)
$config['kolab_api_debug'] = false;

// List of allowed tasks in helpdesk mode. If empty there's no limits.
// For example, to limit user to the Settings section only: ['settings'].
$config['kolab_helpdesk_allowed_tasks'] = [];

// Type of cache for API requests. Supported values: 'db', 'redis' and 'memcache' or 'memcached'.
$config['kolab_client_cache'] = null;

// Lifetime of cache entries. Possible units: s, m, h, d, w
$config['kolab_client_cache_ttl'] = '10m';

// Kolab Cockpit URL - for a button in the task menu
$config['kolab_cockpit_url'] = null;
