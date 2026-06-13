<?php
declare(strict_types=1);

/**
 * Handles LDIF serialization and deserialization.
 */
class LdapSerializer
{
    public function __construct(
        private LDAP $ldap
    ) {
    }

    public function generateLdif(string $dn, string $filter = '(objectClass=*)', string $scope = 'sub', int $limit = 0, ?int $wrap = NULL): string
    {
        return $this->ldap->generateLdif($dn, $filter, $scope, $limit, $wrap);
    }

    public function parseLdif(string $str_attr): array
    {
        return $this->ldap->parseLdif($str_attr);
    }

    public function importCompleteLdif(int $srp, string $str_attr, bool $justModify, bool $deleteOldEntries): bool
    {
        return $this->ldap->import_complete_ldif($srp, $str_attr, $justModify, $deleteOldEntries);
    }
}
