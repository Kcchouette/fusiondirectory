<?php
declare(strict_types=1);

/**
 * HTML and display utility functions.
 */
class HtmlHelper
{
    /**
     * Get template path for a plugin.
     */
    public static function getTemplatePath(string $filename = '', bool $plugin = false, string $path = ''): string
    {
        return \get_template_path($filename, $plugin, $path);
    }

    /**
     * Escape HTML entities.
     */
    public static function htmlescape(string $str): string
    {
        return \htmlescape($str);
    }

    /**
     * Escape XML entities.
     */
    public static function xmlentities(string $str): string
    {
        return \xmlentities($str);
    }

    /**
     * Mark a needle in a haystack for display.
     */
    public static function mark($needle, string $haystack): string
    {
        return \mark($needle, $haystack);
    }

    /**
     * Format bytes to human readable size.
     */
    public static function humanReadableSize(float $bytes, int $precision = 2): string
    {
        return \humanReadableSize($bytes, $precision);
    }
}
