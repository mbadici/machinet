<?php

if (php_sapi_name() != 'cli')
    die("Not in shell mode (php-cli)");

define('TESTS_DIR', dirname(__FILE__) . '/');

require_once(TESTS_DIR . '/../lib/init.php');

rcube::get_instance()->config->set('devel_mode', false);
