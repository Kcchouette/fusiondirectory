<?php
declare(strict_types=1);

/**
 * LDAP utility functions.
 */
class LdapHelper
{
    /**
     * Escape a string for use in LDAP filters.
     */
    public static function escapeFilter(string $str, string $ignore = ''): string
    {
        return \ldap_escape_f($str, $ignore);
    }

    /**
     * Escape a string for use in LDAP DNs.
     */
    public static function escapeDn(string $str, string $ignore = ''): string
    {
        return \ldap_escape_dn($str, $ignore);
    }

    /**
     * Get the entry CSN for a given DN.
     */
    public static function getEntryCsn(string $dn): string
    {
        return \getEntryCSN($dn);
    }
}
