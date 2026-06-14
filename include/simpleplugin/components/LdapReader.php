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

    protected function loadAttributes ()
    {
        // We load attributes values
        // First the one flagged as preInit
        foreach ($this->plugin->preInitAttributes as $attr) {
            $this->plugin->attributesAccess[$attr]->setParent($this->plugin);
            $this->plugin->attributesAccess[$attr]->loadValue($this->plugin->attrs);
        }
        // Then the others
        foreach ($this->plugin->attributesInfo as &$sectionInfo) {
            foreach ($sectionInfo['attrs'] as $name => &$attr) {
                if (in_array($name, $this->plugin->preInitAttributes)) {
                    /* skip the preInit ones */
                    continue;
                }
                $attr->setParent($this->plugin);

                // TOCHECK Convert non array value to an array (fix needed for setup)
                if (!is_array($this->plugin->attrs)) {
                    $this->plugin->attrs = [$this->plugin->attrs];
                }

                $attr->loadValue($this->plugin->attrs);
            }
            unset($attr);
        }
        unset($sectionInfo);
    }

    public function isThisAccount ($attrs)
    {
        $result = get_class($this->plugin)::isAccount($attrs);
        if ($result === NULL) {
            if (!empty($this->plugin->objectclasses)) {
                trigger_error('Deprecated fallback was used for ' . get_class($this->plugin) . '::isThisAccount');
            }
            $found = TRUE;
            foreach ($this->plugin->objectclasses as $obj) {
                if (preg_match('/^top$/i', $obj)) {
                    continue;
                }
                if (!isset($attrs['objectClass']) || !in_array_ics($obj, $attrs['objectClass'])) {
                    $found = FALSE;
                    break;
                }
            }
            return $found;
        }
        return $result;
    }

    /*! \brief This function saves the object in the LDAP
     */
    public function ldapSave (): array
    {
        /* Check if this is a new entry ... add/modify */
        $ldap = config()->getLdapLink();
        if ($this->plugin->mainTab && !$this->plugin->initially_was_account) {
            if ($ldap->dnExists($this->plugin->dn)) {
                return [
                    new SimplePluginError(
                        $this->plugin,
                        htmlescape(sprintf(_('There is already an entry with the same dn: %s'), $this->plugin->dn))
                    )
                ];
            }
            $ldap->cd(config()->current['BASE']);
            try {
                $ldap->createMissingTrees(preg_replace('/^[^,]+,/', '', $this->plugin->dn));
            } catch (FusionDirectoryError $error) {
                return [$error];
            }
            $action = 'add';
        } else {
            if (!$ldap->dnExists($this->plugin->dn)) {
                return [
                    new SimplePluginError(
                        $this->plugin,
                        htmlescape(sprintf(_('The entry %s is not existing'), $this->plugin->dn))
                    )
                ];
            }
            $action = 'modify';
        }

        $ldap->cd($this->plugin->dn);
        $ldap->$action($this->plugin->attrs);
        $this->plugin->ldap_error = $ldap->getError();

        /* Check for errors */
        if (!$ldap->success()) {
            return [
                new SimplePluginLdapError(
                    $this->plugin,
                    $this->plugin->dn,
                    ($action == 'modify' ? LDAP_MOD : LDAP_ADD),
                    $ldap->getError(),
                    $ldap->getErrno()
                )
            ];
        }
        return [];
    }

    protected function ldapRemove (): array
    {
        $ldap = config()->getLdapLink();
        if ($this->plugin->mainTab) {
            $ldap->rmdirRecursive($this->plugin->dn);
        } else {
            $this->plugin->cleanup();
            $ldap->cd($this->plugin->dn);
            $ldap->modify($this->plugin->attrs);
        }
        $this->plugin->ldap_error = $ldap->getError();

        if ($ldap->success()) {
            return [];
        } else {
            return [
                new SimplePluginLdapError(
                    $this->plugin,
                    $this->plugin->dn,
                    ($this->plugin->mainTab ? LDAP_DEL : LDAP_MOD),
                    $ldap->getError(),
                    $ldap->getErrno()
                )
            ];
        }
    }

    /*! \brief This function returns an LDAP filter for this plugin object classes
     */
    public function getObjectClassFilter ()
    {
        trigger_error('Deprecated');
        return get_class($this->plugin)::getLdapFilter();
    }

    /*! \brief This function returns the dn this object should have
     */
    public function computeDn (): string
    {
        if (!$this->plugin->mainTab) {
            throw new FatalError(htmlescape(_('Only main tab can compute dn')));
        }
        if (!isset($this->plugin->parent) || !($this->plugin->parent instanceof SimpleTabs)) {
            throw new FatalError(
                htmlescape(sprintf(
                    _('Could not compute dn: no parent tab class for "%s"'),
                    get_class($this->plugin)
                ))
            );
        }
        $infos = $this->plugin->parent->objectInfos();
        if ($infos === FALSE) {
            throw new FatalError(
                htmlescape(sprintf(
                    _('Could not compute dn: could not find objectType info from tab class "%s"'),
                    get_class($this->plugin->parent)
                ))
            );
        }
        $attr = $infos['mainAttr'];
        $ou   = $infos['ou'];
        if (isset($this->plugin->base)) {
            $base = $this->plugin->base;
        } else {
            $base = config()->current['BASE'];
        }
        if ($this->plugin->is_template) {
            return 'cn=' . ldap_escape_dn($this->plugin->_template_cn) . ',ou=templates,' . $ou . $base;
        }
        return $attr . '=' . ldap_escape_dn($this->plugin->attributesAccess[$attr]->computeLdapValue()) . ',' . $ou . $base;
    }

    /* \!brief Prepare $this->plugin->attrs */
    public function prepareSave (): array
    {
        $this->plugin->entryCSN = '';

        /* Start with empty array */
        $this->plugin->attrs = [];
        $oc          = [];

        if (!$this->plugin->mainTab || $this->plugin->initially_was_account) {
            /* Get current objectClasses in order to add the required ones */
            $ldap = config()->getLdapLink();
            $ldap->cat($this->plugin->dn, ['fdTemplateField', 'objectClass']);

            $tmp = $ldap->fetch();

            if ($this->plugin->is_template) {
                if (isset($tmp['fdTemplateField'])) {
                    foreach ($tmp['fdTemplateField'] as $tpl_field) {
                        if (preg_match('/^objectClass:(.+)$/', $tpl_field, $m)) {
                            $oc[] = $m[1];
                        }
                    }
                }
            } else {
                if (isset($tmp['objectClass'])) {
                    $oc = $tmp['objectClass'];
                    unset($oc['count']);
                }
            }
        }

        $this->plugin->attrs['objectClass'] = $this->plugin->mergeObjectClasses($oc);

        /* Fill attributes LDAP values into the attrs array */
        foreach ($this->plugin->attributesInfo as $sectionInfo) {
            foreach ($sectionInfo['attrs'] as $attr) {
                $attr->fillLdapValue($this->plugin->attrs);
            }
        }
        /* Some of them have post-filling hook */
        foreach ($this->plugin->attributesInfo as $sectionInfo) {
            foreach ($sectionInfo['attrs'] as $attr) {
                $attr->fillLdapValueHook($this->plugin->attrs);
            }
        }

        return [];
    }

    protected function preSave (): array
    {
        if ($this->plugin->initially_was_account) {
            return $this->plugin->handlePreEvents('modify', ['modifiedLdapAttrs' => array_keys($this->plugin->attrs)]);
        } else {
            return $this->plugin->handlePreEvents('add', ['modifiedLdapAttrs' => array_keys($this->plugin->attrs)]);
        }
    }

    /*! \brief This function is called after LDAP save to do some post operations and logging
     *
     * This function calls hooks, update foreign keys and log modification
     */
    public function postSave ()
    {
        $auditAttributesValuesToBeHidden = $this->getAuditAttributesListFromConf();

        if (!empty($auditAttributesValuesToBeHidden)) {
            foreach ($auditAttributesValuesToBeHidden as $key) {
                if (key_exists($key, $this->plugin->attrs)) {
                    $this->plugin->attrs[$key] = 'Value not stored by policy';
                }
            }
        }

        /* Propagate and log the event */
        if ($this->plugin->initially_was_account) {
            $errors = $this->plugin->handlePostEvents('modify', ['modifiedLdapAttrs' => array_keys($this->plugin->attrs)]);

            $modifiedAttrs = $this->getModifiedAttributesValues();
            // We log values of attributes as well if modification occur in order for notification to be aware of the change. (Json allows array to string conversion).
            Logging::log('modify', 'plugin/' . get_class($this->plugin), $this->plugin->dn, [json_encode($modifiedAttrs)], $this->plugin->ldap_error);

        } else {
            $errors = $this->plugin->handlePostEvents('add', ['modifiedLdapAttrs' => array_keys($this->plugin->attrs)]);
            Logging::log('create', 'plugin/' . get_class($this->plugin), $this->plugin->dn, array_keys($this->plugin->attrs), $this->plugin->ldap_error);
        }

        if (!empty($errors)) {
            MsgDialog::displayChecks($errors);
        }
    }

    /**
     * @return array
     * Note: This method is required because setAttribute can contain one value STRING or multiple ARRAY but,
     * selectAttribute only accepts arrays. Its usage is to get audit attributes listed in backend, allowing to hide values from set attributes.
     */
    public function getAuditAttributesListFromConf (): array
    {
        $result = [];

        // If audit plugin is installed only.
        if (class_available('auditConfig')) {
            if (!empty(config()->current['AUDITCONFHIDDENATTRVALUES'])) {
                if (is_string(config()->current['AUDITCONFHIDDENATTRVALUES'])) {
                    $result[] = config()->current['AUDITCONFHIDDENATTRVALUES'];
                } else {
                    $result = config()->current['AUDITCONFHIDDENATTRVALUES'];
                }
            }
        }

        return $result;
    }

    public function getModifiedAttributesValues (): array
    {
        // Initialize result array
        $result = [];

        // Find common keys between old attributes and modified attributes.
        $commonKeys = array_intersect_key($this->plugin->attrs, $this->plugin->beforeLdapChangeAttributes);

        // Iterate over each common key
        foreach ($commonKeys as $key => $value) {
            // Check if the new value differs from the old value
            if ($this->plugin->attrs[$key] !== $this->plugin->beforeLdapChangeAttributes[$key]) {
                $newValues = $this->plugin->attrs[$key];
                $oldValues = $this->plugin->beforeLdapChangeAttributes[$key];

                // Ensure both new and old values are arrays for comparison
                if (is_array($newValues) && is_array($oldValues)) {
                    // Find the new values that are not present in the old values
                    $diffValues = array_diff($newValues, $oldValues);

                    // Store only the new values that are different
                    if (!empty($diffValues)) {
                        $result[$key] = $diffValues;
                    }
                } else {
                    // If values are scalar (non-array), store the new value directly if it differs
                    $result[$key] = $newValues;
                }
            }
        }

        return $result;
    }

    /* Remove FusionDirectory attributes */
    public function prepareRemove ()
    {
        $this->plugin->attrs = [];

        if (!$this->plugin->mainTab) {
            /* include global link_info */
            $ldap = config()->getLdapLink();

            /* Get current objectClasses in order to add the required ones */
            $ldap->cat($this->plugin->dn, ['fdTemplateField', 'objectClass']);
            $tmp = $ldap->fetch();
            $oc  = [];
            if ($this->plugin->is_template) {
                if (isset($tmp['fdTemplateField'])) {
                    foreach ($tmp['fdTemplateField'] as $tpl_field) {
                        if (preg_match('/^objectClass:(.+)$/', $tpl_field, $m)) {
                            $oc[] = $m[1];
                        }
                    }
                }
            } else {
                if (isset($tmp['objectClass'])) {
                    $oc = $tmp['objectClass'];
                    unset($oc['count']);
                }
            }

            /* Remove objectClasses from entry */
            $this->plugin->attrs['objectClass'] = array_remove_entries_ics($this->plugin->objectclasses, $oc);

            /* Unset attributes from entry */
            foreach ($this->plugin->attributes as $val) {
                $this->plugin->attrs["$val"] = [];
            }
        }
    }

    protected function preRemove ()
    {
        if ($this->plugin->initially_was_account) {
            return $this->plugin->handlePreEvents('remove', ['modifiedLdapAttrs' => array_keys($this->plugin->attrs)]);
        }
    }

    public function postRemove ()
    {
        Logging::log('remove', 'plugin/' . get_class($this->plugin), $this->plugin->dn, array_keys($this->plugin->attrs), $this->plugin->ldap_error);

        /* Optionally execute a command after we're done */
        $errors = $this->plugin->handlePostEvents('remove', ['modifiedLdapAttrs' => array_keys($this->plugin->attrs)]);
        if (!empty($errors)) {
            MsgDialog::displayChecks($errors);
        }
    }
}
