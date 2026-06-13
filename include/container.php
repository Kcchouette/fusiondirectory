<?php
declare(strict_types=1);

/**
 * Global container accessor for FusionDirectory.
 *
 * Provides a single entry point to the DI container.
 * Replaces the global variables pattern ($config, $ui, $smarty, etc.)
 *
 * Usage:
 *   $config = container()->get(Config::class);
 *   $ui = container()->get(UserInfo::class);
 */
function container(): FusionDirectory\Container\Container
{
    static $container = null;

    if ($container === null) {
        $container = new FusionDirectory\Container\Container();
    }

    return $container;
}
