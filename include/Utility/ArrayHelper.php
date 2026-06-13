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
        return \array_remove_entries($needles, $haystack);
    }

    /**
     * Remove entries from an array by key (case-insensitive).
     */
    public static function removeEntriesIcs(array $needles, array $haystack): array
    {
        return \array_remove_entries_ics($needles, $haystack);
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
        return \in_array_ics($value, $items);
    }

    /**
     * Check if key exists in array (case-insensitive string comparison).
     */
    public static function arrayKeyIcs($ikey, array $items)
    {
        return \array_key_ics($ikey, $items);
    }

    /**
     * Check if two arrays differ.
     */
    public static function differs(array $src, array $dst): bool
    {
        return \array_differs($src, $dst);
    }

    /**
     * Recursively check if two arrays differ.
     */
    public static function differsRecursive($src, $dst): bool
    {
        return \array_differs_recursive($src, $dst);
    }

    /**
     * Recursively compare two arrays.
     */
    public static function cmpRecursive($src, $dst): int
    {
        return \array_cmp_recursive($src, $dst);
    }
}
