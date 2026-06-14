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

/**
 * Get the class mapping array (replaces `global $class_mapping`).
 */
function class_mapping(): array
{
    global $class_mapping;
    return $class_mapping ?? [];
}

/**
 * Get the base directory (replaces `global $BASE_DIR`).
 */
function base_dir(): string
{
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
