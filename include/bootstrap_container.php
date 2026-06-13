<?php
declare(strict_types=1);

/**
 * Bootstrap the FusionDirectory container with core services.
 *
 * This replaces the global variable pattern with DI.
 * Called once during application initialization.
 */
function bootstrapContainer(): void
{
    $container = container();

    /* Register Config as a factory (created once, reused) */
    $container->factory(Config::class, function () {
        global $config;
        /* During migration, wrap the existing global */
        return $config;
    });

    /* Register UserInfo as a factory */
    $container->factory(UserInfo::class, function () {
        global $ui;
        return $ui;
    });

    /* Register Pluglist as a factory */
    $container->factory(Pluglist::class, function () {
        global $plist;
        return $plist;
    });

    /* Register Logger */
    $container->set(Logger::class, new Logger());
}
