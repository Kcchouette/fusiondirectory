<?php

define('BASE_DIR', dirname(__DIR__));
define('CONFIG_DIR', '/etc/fusiondirectory');
define('CACHE_DIR', sys_get_temp_dir() . '/fusiondirectory-test');
define('CLASS_CACHE', 'class.cache');
define('SPOOL_DIR', sys_get_temp_dir() . '/fusiondirectory-test/spool');
define('SMARTY', '/usr/share/php/smarty4/Smarty.class.php');

if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0750, true);
}
if (!is_dir(SPOOL_DIR)) {
    mkdir(SPOOL_DIR, 0750, true);
}

$class_mapping = [];

require_once BASE_DIR . '/include/functions.inc';
