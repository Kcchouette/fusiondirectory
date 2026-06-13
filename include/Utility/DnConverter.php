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
        return \convert_department_dn($dn, $base);
    }

    /**
     * Get the OU for a given name.
     */
    public static function getOu(string $name): string
    {
        return \get_ou($name);
    }

    /**
     * Get the people OU.
     */
    public static function getPeopleOu(): string
    {
        return \get_people_ou();
    }

    /**
     * Get base from people DN.
     */
    public static function getBaseFromPeople(string $dn): string
    {
        return \get_base_from_people($dn);
    }

    /**
     * Extract base from a DN.
     */
    public static function dn2base(string $dn, ?string $ou = null): string
    {
        return \dn2base($dn, $ou);
    }
}
