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
        return \ldapEscapeF($str, $ignore);
    }

    /**
     * Escape a string for use in LDAP DNs.
     */
    public static function escapeDn(string $str, string $ignore = ''): string
    {
        return \ldapEscapeDn($str, $ignore);
    }

    /**
     * Get the entry CSN for a given DN.
     */
    public static function getEntryCsn(string $dn): string
    {
        return \getEntryCSN($dn);
    }
}
