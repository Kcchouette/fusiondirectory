<?php
declare(strict_types=1);

/**
 * Global container accessor for FusionDirectory.
 *
 * Provides a single entry point to the DI container.
 * Replaces the global variables pattern ($config, $ui, $smarty, etc.)
 *
 * Usage:
 *   $config = config();
 *   $ui = user_info();
 *   $smarty = smarty();
 *   Or: $config = container()->get(Config::class);
 */
function container(): FusionDirectory\Container\Container
{
    static $container = null;

    if ($container === null) {
        $container = new FusionDirectory\Container\Container();
    }

    return $container;
}

/**
 * Get the Config instance (replaces `global $config`).
 */
function config(): Config
{
    global $config;

    if (!is_object($config)) {
        throw new \RuntimeException('Config not initialized');
    }

    return $config;
}

/**
 * Get the UserInfo instance (replaces `global $ui`).
 */
function user_info(): ?UserInfo
{
    global $ui;
    return $ui ?? null;
}

/**
 * Get the Pluglist instance (replaces `global $plist`).
 */
function pluglist(): ?Pluglist
{
    global $plist;
    return $plist ?? null;
}

/**
 * Get the Smarty instance (replaces `global $smarty`).
 */
function smarty(): ?Smarty
{
    global $smarty;
    return $smarty ?? null;
}
