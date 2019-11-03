<?php

/*
  Mattermost integration.
  -----------------------

  0. Current implementation requires user credentials to be the same
    as in the Kolab server. Thanks to this we can auto-login users.
  1. It has to use the same domain, if it's using different we have to use a proxy:
    Following Apache config worked for me with kolab_chat_url=https://kolab.example.com/mattermost

    ProxyPreserveHost Off
    RewriteEngine On
    RewriteCond %{REQUEST_URI} (/mattermost)?/api/v[0-9]+/(users/)?websocket [NC,OR]
    RewriteCond %{HTTP:UPGRADE} ^WebSocket$ [NC,OR]
    RewriteCond %{HTTP:CONNECTION} ^Upgrade$ [NC]
    RewriteRule (/mattermost)?(/api/v[0-9]+/(users/)?websocket) ws://mattermost.example.com:8065$2 [P,QSA,L]
    ProxyPass         /mattermost http://mattermost.example.com:8065

    // replace Mattermost security headers allowing the webmail domain
    Header set X-Frame-Options "allow-from https://webmail.example.com";
    Header set Content-Security-Policy "frame-ancestors https://webmail.example.com";

  2. Enabling CORS connections in Mattermost config: AllowCorsFrom:"webmail.example.com" (or "*")
*/

// Chat application name. For now only 'mattermost' is supported.
$config['kolab_chat_driver'] = 'mattermost';

// Chat application URL
$config['kolab_chat_url'] = 'https://mattermost.example.com';

// Optional chat application domain (for session cookies)
$config['kolab_chat_session_domain'] = null;

// Enables opening chat in a new window (or tab)
$config['kolab_chat_extwin'] = false;

// Default channel to select when opening the chat app.
// Note: This has to be a channel ID.
$config['kolab_chat_channel'] = null;
