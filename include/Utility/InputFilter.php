<?php
declare(strict_types=1);

namespace FusionDirectory\Utility;

/**
 * Input filtering utilities for FusionDirectory.
 * Replaces direct $_GET/$_POST access with filtered input.
 */
class InputFilter
{
    /**
     * Get a filtered GET parameter.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = filter_input(INPUT_GET, $key, FILTER_UNSAFE_RAW);
        return $value !== null && $value !== false ? $value : $default;
    }

    /**
     * Get a filtered POST parameter.
     */
    public static function post(string $key, mixed $default = null): mixed
    {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
        return $value !== null && $value !== false ? $value : $default;
    }

    /**
     * Get a filtered REQUEST parameter.
     */
    public static function request(string $key, mixed $default = null): mixed
    {
        $value = filter_input(INPUT_REQUEST, $key, FILTER_UNSAFE_RAW);
        return $value !== null && $value !== false ? $value : $default;
    }

    /**
     * Get a filtered GET parameter as string.
     */
    public static function getString(string $key, string $default = ''): string
    {
        return (string) self::get($key, $default);
    }

    /**
     * Get a filtered GET parameter as int.
     */
    public static function getInt(string $key, int $default = 0): int
    {
        $value = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
        return $value !== false && $value !== null ? (int) $value : $default;
    }

    /**
     * Check if a GET parameter exists.
     */
    public static function has(string $key): bool
    {
        return filter_input(INPUT_GET, $key) !== null;
    }
}
