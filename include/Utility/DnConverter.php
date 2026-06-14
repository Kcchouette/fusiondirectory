<?php
declare(strict_types=1);

/**
 * DN and OU utility functions.
 */
class DnConverter
{
    /**
     * Convert a department DN to a readable string.
     */
    public static function convertDepartmentDn(string $dn, ?string $base = null): string
    {
        return \convertDepartmentDn($dn, $base);
    }

    /**
     * Get the OU for a given name.
     */
    public static function getOu(string $name): string
    {
        return \getOu($name);
    }

    /**
     * Get the people OU.
     */
    public static function getPeopleOu(): string
    {
        return \getPeopleOu();
    }

    /**
     * Get base from people DN.
     */
    public static function getBaseFromPeople(string $dn): string
    {
        return \getBaseFromPeople($dn);
    }

    /**
     * Extract base from a DN.
     */
    public static function dn2base(string $dn, ?string $ou = null): string
    {
        return \dn2base($dn, $ou);
    }
}
