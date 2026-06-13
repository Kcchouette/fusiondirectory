<?php
declare(strict_types=1);

/**
 * Handles LDAP loading and saving for a SimplePlugin instance.
 */
class LdapReader
{
    public function __construct(
        private SimplePlugin $plugin
    ) {
    }

    public function loadAttributes(): void
    {
        $this->plugin->loadAttributes();
    }

    public function isThisAccount(array $attrs): bool
    {
        return $this->plugin->is_this_account($attrs);
    }

    public function ldapSave(): array
    {
        return $this->plugin->ldap_save();
    }

    public function ldapRemove(): array
    {
        return $this->plugin->ldap_remove();
    }

    public function getObjectClassFilter(): string
    {
        return $this->plugin->getObjectClassFilter();
    }

    public function computeDn(): string
    {
        return $this->plugin->compute_dn();
    }
}
