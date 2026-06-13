<?php

/* BASE_DIR: go up from tests/ to repo root */
define('BASE_DIR', dirname(__DIR__));

/* Safety check: verify we're in the right place */
if (!is_dir(BASE_DIR . '/include')) {
    throw new \RuntimeException('BASE_DIR (' . BASE_DIR . ') does not point to the FusionDirectory repo root');
}

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

/*
 * Minimal autoloader for tests — resolves FusionDirectory\* namespaces
 * without loading the full application (which has side effects like _()).
 */
$autoloaderBaseDir = BASE_DIR;
spl_autoload_register(function (string $className) use ($autoloaderBaseDir): void {
    global $class_mapping;

    if (strpos($className, 'Smarty_') === 0) {
        return;
    }

    $legacyName = $className;
    if (strpos($className, 'FusionDirectory\\') === 0) {
        $legacyName = preg_replace('/^.+\\\\([^\\\\]+)$/', '\\1', $className);
    }

    if (isset($class_mapping[$legacyName])) {
        require_once $autoloaderBaseDir . '/' . $class_mapping[$legacyName];
        return;
    }

    if (strpos($className, 'FusionDirectory\\') === 0) {
        $relative = str_replace('\\', '/', substr($className, strlen('FusionDirectory\\')));
        $candidates = [
            $autoloaderBaseDir . '/src/' . $relative . '.php',
            $autoloaderBaseDir . '/include/' . $relative . '.php',
        ];
        foreach ($candidates as $file) {
            if (is_file($file)) {
                require_once $file;
                return;
            }
        }
    }
});
