<?php
declare(strict_types=1);

/**
 * Logging and debug utility functions.
 */
class Logger
{
    /**
     * Debug output.
     */
    public static function debug(int $level, int $line, string $function, string $file, $data, string $info = ''): void
    {
        \DEBUG($level, $line, $function, $file, $data, $info);
    }

    /**
     * Log a message to syslog.
     */
    public static function log(string $message): void
    {
        \fusiondirectoryLog($message);
    }

    /**
     * Get copy notice text.
     */
    public static function copynotice(): string
    {
        return \copynotice();
    }

    /**
     * Reset error collector.
     */
    public static function resetErrors(): void
    {
        \resetErrors();
    }
}
