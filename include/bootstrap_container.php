<?php
declare(strict_types=1);

/**
 * Bootstrap the FusionDirectory container with core services.
 *
 * Called once during application initialization.
 * Registers services that replace global variables.
 */
function bootstrapContainer(): void
{
    $container = container();

    /* Register base_dir */
    global $BASE_DIR;
    $container->set('base_dir', $BASE_DIR ?? dirname(__DIR__));

    /* Register class_mapping */
    global $class_mapping;
    $container->set('class_mapping', $class_mapping ?? []);

    /* Register Config */
    global $config;
    if (is_object($config)) {
        $container->set(Config::class, $config);
    }

    /* Register UserInfo */
    global $ui;
    if (is_object($ui)) {
        $container->set(UserInfo::class, $ui);
    }

    /* Register Pluglist */
    global $plist;
    if (is_object($plist)) {
        $container->set(Pluglist::class, $plist);
    }

    /* Register Smarty */
    global $smarty;
    if (is_object($smarty)) {
        $container->set('smarty', $smarty);
    }

    /* Register other globals */
    global $message, $ssl, $error_collector, $error_collector_mailto;
    $container->set('message', $message ?? null);
    $container->set('ssl', $ssl ?? false);
    $container->set('error_collector', $error_collector ?? null);
    $container->set('error_collector_mailto', $error_collector_mailto ?? null);
}
