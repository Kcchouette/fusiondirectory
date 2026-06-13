<?php

/* Define constants BEFORE including the application, to prevent redefinition warnings */
define('BASE_DIR', dirname(__DIR__));
define('CONFIG_DIR', '/etc/fusiondirectory');
define('SPOOL_DIR', sys_get_temp_dir() . '/fusiondirectory-test/spool');
define('CACHE_DIR', sys_get_temp_dir() . '/fusiondirectory-test');
define('CLASS_CACHE', 'class.cache');
define('SMARTY', '/usr/share/php/smarty4/Smarty.class.php');

if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0750, true);
}
if (!is_dir(SPOOL_DIR)) {
    mkdir(SPOOL_DIR, 0750, true);
}

/* Create empty class.cache so functions.inc can load */
$cacheFile = CACHE_DIR . '/' . CLASS_CACHE;
if (!is_file($cacheFile)) {
    file_put_contents($cacheFile, '<?php $class_mapping = [];');
}

$class_mapping = [];
