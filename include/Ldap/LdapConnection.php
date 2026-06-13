<?php
declare(strict_types=1);

/**
 * Handles LDAP connection lifecycle.
 * Delegates to the main LDAP class for shared state.
 */
class LdapConnection
{
    public function __construct(
        private LDAP $ldap
    ) {
    }

    public function connect(): bool
    {
        return $this->ldap->connect();
    }

    public function rebind($ldap, $referral): void
    {
        $this->ldap->rebind($ldap, $referral);
    }

    public function reconnect(): void
    {
        $this->ldap->reconnect();
    }

    public function unbind(): void
    {
        $this->ldap->unbind();
    }

    public function disconnect(): void
    {
        $this->ldap->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->ldap->hascon;
    }

    public function getCredentials($url, $referrals = NULL): array
    {
        return $this->ldap->getCredentials($url, $referrals);
    }
}
