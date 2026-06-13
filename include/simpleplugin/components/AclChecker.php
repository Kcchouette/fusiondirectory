<?php
declare(strict_types=1);

/**
 * Handles ACL checks for a SimplePlugin instance.
 */
class AclChecker
{
    public function __construct(
        private SimplePlugin $plugin
    ) {
    }

    public function isWriteable(string $attribute, bool $skipWrite = false): bool
    {
        return $this->plugin->aclIsWriteable($attribute, $skipWrite);
    }

    public function isReadable(string $attribute): bool
    {
        return $this->plugin->aclIsReadable($attribute);
    }

    public function isCreateable(?string $base = null): bool
    {
        return $this->plugin->aclIsCreateable($base);
    }

    public function isRemoveable(?string $base = null): bool
    {
        return $this->plugin->aclIsRemoveable($base);
    }

    public function isMoveable(?string $base = null): bool
    {
        return $this->plugin->aclIsMoveable($base);
    }

    public function hasPermissions(): bool
    {
        return $this->plugin->aclHasPermissions();
    }

    public function getPermissions(string $attribute = '0', ?string $base = null, bool $skipWrite = false): string
    {
        return $this->plugin->aclGetPermissions($attribute, $base, $skipWrite);
    }

    public function getBase(bool $callParent = true): string
    {
        return $this->plugin->getAclBase($callParent);
    }

    public function attrIsReadable($attr): bool
    {
        return $this->plugin->attrIsReadable($attr);
    }

    public function attrIsWriteable($attr): bool
    {
        return $this->plugin->attrIsWriteable($attr);
    }
}
