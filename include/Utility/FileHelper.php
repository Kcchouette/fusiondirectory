<?php
declare(strict_types=1);

/**
 * File system utility functions.
 */
class FileHelper
{
    /**
     * Recursively remove a directory.
     */
    public static function rmdirRecursive(string $path, bool $followLinks = false): bool
    {
        return \rmdirRecursive($path, $followLinks);
    }

    /**
     * Scan a directory for files.
     */
    public static function scanDirectory(string $path, bool $sortDesc = false): array
    {
        return \scanDirectory($path, $sortDesc);
    }

    /**
     * Clean the Smarty compile directory.
     */
    public static function cleanSmartyCompileDir(string $directory): void
    {
        \cleanSmartyCompileDir($directory);
    }

    /**
     * Create a revision file.
     */
    public static function createRevision(string $revisionFile, string $revision): bool
    {
        return \createRevision($revisionFile, $revision);
    }

    /**
     * Compare a revision file.
     */
    public static function compareRevision(string $revisionFile, string $revision): bool
    {
        return \compareRevision($revisionFile, $revision);
    }

    /**
     * Send binary content as download.
     */
    public static function sendBinaryContent(string $data, string $name, string $type = 'application/octet-stream'): void
    {
        \send_binary_content($data, $name, $type);
    }
}
