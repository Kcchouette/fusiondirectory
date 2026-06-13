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
        return \scan_directory($path, $sortDesc);
    }

    /**
     * Clean the Smarty compile directory.
     */
    public static function cleanSmartyCompileDir(string $directory): void
    {
        \clean_smarty_compile_dir($directory);
    }

    /**
     * Create a revision file.
     */
    public static function createRevision(string $revisionFile, string $revision): bool
    {
        return \create_revision($revisionFile, $revision);
    }

    /**
     * Compare a revision file.
     */
    public static function compareRevision(string $revisionFile, string $revision): bool
    {
        return \compare_revision($revisionFile, $revision);
    }

    /**
     * Send binary content as download.
     */
    public static function sendBinaryContent(string $data, string $name, string $type = 'application/octet-stream'): void
    {
        \send_binary_content($data, $name, $type);
    }
}
