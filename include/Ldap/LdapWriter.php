<?php
declare(strict_types=1);

/**
 * Handles LDAP write operations (add, modify, delete, rename).
 */
class LdapWriter
{
    public function __construct(
        private LDAP $ldap
    ) {
    }

    public function add(array $attrs): bool
    {
        return $this->ldap->add($attrs);
    }

    public function modify(array $attrs): bool
    {
        return $this->ldap->modify($attrs);
    }

    public function modifyBatch(array $changes): bool
    {
        return $this->ldap->modify_batch($changes);
    }

    public function rm(string $attrs = '', string $dn = ''): bool
    {
        return $this->ldap->rm($attrs, $dn);
    }

    public function modAdd(string $attrs = '', string $dn = ''): bool
    {
        return $this->ldap->mod_add($attrs, $dn);
    }

    public function rmdir(string $deletedn): bool
    {
        return $this->ldap->rmdir($deletedn);
    }

    public function rmdirRecursive(int $srp, string $deletedn): bool
    {
        return $this->ldap->rmdir_recursive($srp, $deletedn);
    }

    public function renameDn(string $source, string $dest): bool
    {
        return $this->ldap->rename_dn($source, $dest);
    }

    public function createMissingTrees(int $srp, string $target, bool $ignoreReferralBases = true): bool
    {
        return $this->ldap->create_missing_trees($srp, $target, $ignoreReferralBases);
    }
}
