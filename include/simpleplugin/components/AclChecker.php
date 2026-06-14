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

    public function aclSkipWrite (): bool
    {
        return ($this->plugin->needEditMode && !Session::isSet('edit'));
    }

    /*! \brief Can we write the attribute */
    public function aclIsWriteable ($attribute, bool $skipWrite = FALSE): bool
    {
        return (strpos($this->aclGetPermissions($attribute, NULL, $skipWrite), 'w') !== FALSE);
    }

    /*!
     * \brief Can we read the acl
     *
     * \param string $attribute
     */
    public function aclIsReadable ($attribute): bool
    {
        return (strpos($this->aclGetPermissions($attribute), 'r') !== FALSE);
    }

    /*!
     * \brief Can we create the object
     *
     * \param string $base Empty string
     */
    public function aclIsCreateable (?string $base = NULL): bool
    {
        return (strpos($this->aclGetPermissions('0', $base), 'c') !== FALSE);
    }

    /*!
     * \brief Can we delete the object
     *
     * \param string $base Empty string
     */
    public function aclIsRemoveable (?string $base = NULL): bool
    {
        return (strpos($this->aclGetPermissions('0', $base), 'd') !== FALSE);
    }

    /*!
     * \brief Can we move the object
     *
     * \param string $base Empty string
     */
    public function aclIsMoveable (?string $base = NULL): bool
    {
        return (strpos($this->aclGetPermissions('0', $base), 'm') !== FALSE);
    }

    /*! \brief Test if there are ACLs for this plugin */
    public function aclHasPermissions (): bool
    {
        return in_array(get_class($this->plugin), config()->data['CATEGORIES'][rtrim($this->plugin->acl_category, '/')]['classes']);
    }

    /*! \brief Get the acl permissions for an attribute or the plugin itself */
    public function aclGetPermissions ($attribute = '0', ?string $base = NULL, bool $skipWrite = FALSE): string
    {
        if (isset($this->plugin->parent) && isset($this->plugin->parent->ignoreAcls) && $this->plugin->parent->ignoreAcls) {
            return 'cdmr' . ($skipWrite ? '' : 'w');
        }
        $ui        = getUserInfo();
        $skipWrite |= $this->plugin->readOnly();
        if ($base === NULL) {
            $base = $this->getAclBase();
        }
        return $ui->getPermissions($base, $this->plugin->acl_category . get_class($this->plugin), $attribute, $skipWrite);
    }

    /*!
     * \brief Get LDAP base to use for ACL checks
     */
    public function getAclBase (bool $callParent = TRUE): string
    {
        if (($this->plugin->parent instanceof SimpleTabs) && $callParent) {
            return $this->plugin->parent->getAclBase();
        }
        if (isset($this->plugin->dn) && ($this->plugin->dn != 'new')) {
            return $this->plugin->dn;
        }
        if (isset($this->plugin->base)) {
            return 'new,' . $this->plugin->base;
        }

        return config()->current['BASE'];
    }

    /*! \brief Check if logged in user have enough right to read this attribute value
     *
     * \param mixed $attr Attribute object or name (in this case it will be fetched from attributesAccess)
     */
    public function attrIsReadable ($attr): bool
    {
        if (!is_object($attr)) {
            $attr = $this->plugin->attributesAccess[$attr];
        }
        if ($attr->getLdapName() == 'base') {
            return TRUE;
        }
        if ($attr->getAcl() == 'noacl') {
            return TRUE;
        }
        return $this->aclIsReadable($attr->getAcl());
    }

    /*! \brief Check if logged in user have enough right to write this attribute value
     *
     * \param mixed $attr Attribute object or name (in this case it will be fetched from attributesAccess)
     */
    public function attrIsWriteable ($attr): bool
    {
        if (!is_object($attr)) {
            $attr = $this->plugin->attributesAccess[$attr];
        }
        if ($attr->getLdapName() == 'base') {
            return (
                !$this->aclSkipWrite() &&
                (!$this->plugin->initially_was_account || $this->aclIsMoveable() || $this->aclIsRemoveable())
            );
        }
        if ($attr->getAcl() == 'noacl') {
            return FALSE;
        }
        return $this->aclIsWriteable($attr->getAcl(), $this->aclSkipWrite());
    }
}
