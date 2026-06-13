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
        return $this->plugin->acl_is_writeable($attribute, $skipWrite);
    }

    public function isReadable(string $attribute): bool
    {
        return $this->plugin->acl_is_readable($attribute);
    }

    public function isCreateable(?string $base = null): bool
    {
        return $this->plugin->acl_is_createable($base);
    }

    public function isRemoveable(?string $base = null): bool
    {
        return $this->plugin->acl_is_removeable($base);
    }

    public function isMoveable(?string $base = null): bool
    {
        return $this->plugin->acl_is_moveable($base);
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
