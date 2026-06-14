<?php
declare(strict_types=1);

/**
 * Array utility functions.
 */
class ArrayHelper
{
    /**
     * Remove entries from an array by key.
     */
    public static function removeEntries(array $needles, array $haystack): array
    {
        return \arrayRemoveEntries($needles, $haystack);
    }

    /**
     * Remove entries from an array by key (case-insensitive).
     */
    public static function removeEntriesIcs(array $needles, array $haystack): array
    {
        return \arrayRemoveEntriesIcs($needles, $haystack);
    }

    /**
     * Merge two arrays uniquely.
     */
    public static function mergeUnique(array $ar1, array $ar2): array
    {
        return \array_merge_unique($ar1, $ar2);
    }

    /**
     * Check if value exists in array (case-insensitive string comparison).
     */
    public static function inArrayIcs($value, array $items): bool
    {
        return \inArrayIcs($value, $items);
    }

    /**
     * Check if key exists in array (case-insensitive string comparison).
     */
    public static function arrayKeyIcs($ikey, array $items)
    {
        return \arrayKeyIcs($ikey, $items);
    }

    /**
     * Check if two arrays differ.
     */
    public static function differs(array $src, array $dst): bool
    {
        return \arrayDiffers($src, $dst);
    }

    /**
     * Recursively check if two arrays differ.
     */
    public static function differsRecursive($src, $dst): bool
    {
        return \arrayDiffersRecursive($src, $dst);
    }

    /**
     * Recursively compare two arrays.
     */
    public static function cmpRecursive($src, $dst): int
    {
        return \arrayCmpRecursive($src, $dst);
    }
}
