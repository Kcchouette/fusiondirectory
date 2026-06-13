<?php
declare(strict_types=1);

/**
 * Handles LDAP read/search operations.
 */
class LdapSearch
{
    public function __construct(
        private LDAP $ldap
    ) {
    }

    public function getSearchResource(): int
    {
        return $this->ldap->getSearchResource();
    }

    public function search(int $srp, string $filter, array $attrs = [], string $scope = 'subtree', ?array $controls = NULL): bool
    {
        return $this->ldap->search($srp, $filter, $attrs, $scope, $controls);
    }

    public function cat(int $srp, string $dn, array $attrs = ['*'], string $filter = '(objectclass=*)'): bool
    {
        return $this->ldap->cat($srp, $dn, $attrs, $filter);
    }

    public function fetch(int $srp, bool $cleanUpNumericIndices = false): array|false
    {
        return $this->ldap->fetch($srp, $cleanUpNumericIndices);
    }

    public function getDN(int $srp): string|false
    {
        return $this->ldap->getDN($srp);
    }

    public function count(int $srp): int|false
    {
        return $this->ldap->count($srp);
    }

    public function parseResult(int $srp): array
    {
        return $this->ldap->parse_result($srp);
    }

    public function resetResult(int $srp): void
    {
        $this->ldap->resetResult($srp);
    }

    public function clearResult(int $srp): void
    {
        $this->ldap->clearResult($srp);
    }

    public function setPointSizeLimit(int $size): void
    {
        $this->ldap->set_size_limit($size);
    }

    public function cd(string $dir): void
    {
        $this->ldap->cd($dir);
    }

    public function getParentDir(string $basedn = ''): string
    {
        return $this->ldap->getParentDir($basedn);
    }

    public function objectMatchFilter(string $dn, string $filter): bool
    {
        return $this->ldap->object_match_filter($dn, $filter);
    }

    public function dnExists(string $dn): bool
    {
        return $this->ldap->dn_exists($dn);
    }
}
