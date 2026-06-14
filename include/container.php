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
    $c = container();

    if ($c->has(Config::class)) {
        return $c->get(Config::class);
    }

    /* Fallback during bootstrap before container is populated */
    global $config;
    if (is_object($config)) {
        return $config;
    }

    throw new \RuntimeException('Config not initialized');
}

/**
 * Get the UserInfo instance (replaces `global $ui`).
 */
function user_info(): ?UserInfo
{
    $c = container();

    if ($c->has(UserInfo::class)) {
        return $c->get(UserInfo::class);
    }

    global $ui;
    return $ui ?? null;
}

/**
 * Get the Pluglist instance (replaces `global $plist`).
 */
function pluglist(): ?Pluglist
{
    $c = container();

    if ($c->has(Pluglist::class)) {
        return $c->get(Pluglist::class);
    }

    global $plist;
    return $plist ?? null;
}

/**
 * Get the Smarty instance (replaces `global $smarty`).
 */
function smarty(): ?Smarty
{
    $c = container();

    if ($c->has('smarty')) {
        return $c->get('smarty');
    }

    global $smarty;
    return $smarty ?? null;
}

/**
 * Get the class mapping array (replaces `global $class_mapping`).
 */
function class_mapping(): array
{
    $c = container();

    if ($c->has('class_mapping')) {
        return $c->get('class_mapping');
    }

    global $class_mapping;
    return $class_mapping ?? [];
}

/**
 * Get the base directory (replaces `global $BASE_DIR`).
 */
function base_dir(): string
{
    $c = container();

    if ($c->has('base_dir')) {
        return $c->get('base_dir');
    }

    global $BASE_DIR;
    return $BASE_DIR ?? '';
}

/**
 * Get the message variable (replaces `global $message`).
 */
function &message(): mixed
{
    global $message;
    return $message;
}

/**
 * Get SSL status (replaces `global $ssl`).
 */
function ssl(): bool
{
    $c = container();

    if ($c->has('ssl')) {
        return $c->get('ssl');
    }

    global $ssl;
    return $ssl ?? false;
}

/**
 * Get error collector (replaces `global $error_collector`).
 */
function &error_collector(): mixed
{
    global $error_collector;
    return $error_collector;
}

/**
 * Get error collector mailto (replaces `global $error_collector_mailto`).
 */
function &error_collector_mailto(): mixed
{
    global $error_collector_mailto;
    return $error_collector_mailto;
}

/**
 * Get position DN (replaces `global $positionDN`).
 */
function &position_dn(): mixed
{
    global $positionDN;
    return $positionDN;
}

/**
 * Get remove_lock flag (replaces `global $remove_lock`).
 */
function &remove_lock(): mixed
{
    global $remove_lock;
    return $remove_lock;
}

/**
 * Get cleanup flag (replaces `global $cleanup`).
 */
function &cleanup(): mixed
{
    global $cleanup;
    return $cleanup;
}

/**
 * Get display variable (replaces `global $display`).
 */
function &display(): mixed
{
    global $display;
    return $display;
}

/**
 * Get plug variable (replaces `global $plug`).
 */
function &plug(): mixed
{
    global $plug;
    return $plug;
}
