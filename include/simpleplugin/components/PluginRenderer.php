<?php
declare(strict_types=1);

/**
 * Handles rendering and display for a SimplePlugin instance.
 */
class PluginRenderer
{
    public function __construct(
        private SimplePlugin $plugin
    ) {
    }

    public function execute (): string
    {
        trigger_error('obsolete');
        $this->plugin->update();
        return $this->render();
    }

    /*! \brief This function display the plugin and return the html code
     */
    public function render (): string
    {
        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->plugin->dn, 'render');

        /* Reset Lock message POST/GET check array, to prevent preg_match errors */
        Session::set('LOCK_VARS_TO_USE', []);
        Session::set('LOCK_VARS_USED_GET', []);
        Session::set('LOCK_VARS_USED_POST', []);
        Session::set('LOCK_VARS_USED_REQUEST', []);

        $this->plugin->displayPlugin = TRUE;
        $this->plugin->header        = '';

        if (is_object($this->plugin->dialog)) {
            $this->plugin->header        = $this->plugin->dialog->render();
            $this->plugin->displayPlugin = FALSE;
            return $this->plugin->header;
        }

        if ($this->plugin->displayHeader) {
            /* Show tab dialog headers */
            if ($this->plugin->parent !== NULL) {
                list($disabled, $buttonHtmlText, $htmlText) = $this->getDisplayHeaderInfos();
                $this->plugin->header = $this->showHeader(
                    $buttonHtmlText,
                    $htmlText,
                    $this->plugin->is_account,
                    $disabled,
                    get_class($this->plugin) . '_modify_state'
                );
                if (!$this->plugin->is_account) {
                    $this->plugin->displayPlugin = FALSE;
                    return $this->plugin->header . $this->inheritanceDisplay();
                }
            } elseif (!$this->plugin->is_account) {
                $plInfo              = Pluglist::pluginInfos(get_class($this->plugin));
                $this->plugin->header        = '<img alt="' . htmlescape(_('Error')) . '" src="geticon.php?context=status&amp;icon=dialog-error&amp;size=16" align="middle"/>&nbsp;<b>' .
                    MsgPool::noValidExtension($plInfo['plShortName']) . "</b>";
                $this->plugin->displayPlugin = FALSE;
                return $this->plugin->header . $this->inheritanceDisplay();
            }
        }

        $smarty = getSmarty();

        $this->renderAttributes(FALSE);
        $smarty->assign("hiddenPostedInput", get_class($this->plugin) . "_posted");
        if (isset($this->plugin->focusedField)) {
            $smarty->assign("focusedField", $this->plugin->focusedField);
            unset($this->plugin->focusedField);
        } else {
            $smarty->assign("focusedField", key($this->plugin->attributesAccess));
        }

        return $this->plugin->header . $smarty->fetch($this->plugin->templatePath);
    }

    public function getDisplayHeaderInfos (): array
    {
        $plInfo   = Pluglist::pluginInfos(get_class($this->plugin));
        $disabled = $this->plugin->aclSkipWrite();
        if ($this->plugin->is_account) {
            $depends = [];
            if (isset($plInfo['plDepending'])) {
                foreach ($plInfo['plDepending'] as $plugin) {
                    if (isset($this->plugin->parent->by_object[$plugin]) &&
                        $this->plugin->parent->by_object[$plugin]->is_account) {
                        $disabled      = TRUE;
                        $dependPlInfos = Pluglist::pluginInfos($plugin);
                        $depends[]     = $dependPlInfos['plShortName'];
                    }
                }
            }
            $buttonHtmlText = MsgPool::removeFeaturesButton($plInfo['plShortName']);
            $htmlText       = MsgPool::featuresEnabled($plInfo['plShortName'], $depends);
        } else {
            $depends   = [];
            $conflicts = [];
            if (isset($plInfo['plDepends'])) {
                foreach ($plInfo['plDepends'] as $plugin) {
                    if (isset($this->plugin->parent->by_object[$plugin]) &&
                        !$this->plugin->parent->by_object[$plugin]->is_account) {
                        $disabled      = TRUE;
                        $dependPlInfos = Pluglist::pluginInfos($plugin);
                        $depends[]     = $dependPlInfos['plShortName'];
                    }
                }
            }
            if (isset($plInfo['plConflicts'])) {
                foreach ($plInfo['plConflicts'] as $plugin) {
                    if (isset($this->plugin->parent->by_object[$plugin]) &&
                        $this->plugin->parent->by_object[$plugin]->is_account) {
                        $disabled        = TRUE;
                        $conflictPlInfos = Pluglist::pluginInfos($plugin);
                        $conflicts[]     = $conflictPlInfos['plShortName'];
                    }
                }
            }
            $buttonHtmlText = MsgPool::addFeaturesButton($plInfo['plShortName']);
            $htmlText       = MsgPool::featuresDisabled($plInfo['plShortName'], $depends, $conflicts);
        }
        return [$disabled, $buttonHtmlText, $htmlText];
    }

    /*!
     * \brief Show header message for tab dialogs
     *
     * \param string $buttonHtmlText The button text, escaped for HTML output
     *
     * \param string $htmlText The text to show, as HTML code
     *
     * \param boolean $plugin_enabled Is the plugin/tab activated
     *
     * \param boolean $button_disabled Is the button disabled
     *
     * \param string $name The html name of the input, defaults to modify_state
     */
    public function showHeader (string $buttonHtmlText, string $htmlText, bool $plugin_enabled, bool $button_disabled = FALSE, string $name = 'modify_state'): string
    {
        if ($button_disabled || ((!$this->plugin->aclIsCreateable() && !$plugin_enabled) || (!$this->plugin->aclIsRemoveable() && $plugin_enabled))) {
            $state = 'disabled="disabled"';
        } else {
            $state = '';
        }
        $display = '<div width="100%"><p><b>' . $htmlText . '</b><br/>' . "\n";
        $display .= '<input type="submit" formnovalidate="formnovalidate" value="' . $buttonHtmlText . '" name="' . $name . '" ' . $state . '></p></div><hr class="separator"/>';

        return $display;
    }

    public function renderAttributes (bool $readOnly = FALSE)
    {
        $ui = user_info();
        $smarty = getSmarty();

        if ($this->plugin->is_template) {
            $smarty->assign('template_cnACL', $ui->getPermissions($this->plugin->aclGetBase(), $this->plugin->acl_category . 'Template', 'template_cn', $this->plugin->aclSkipWrite()));
        }

        /* Handle rights to modify the base */
        if (isset($this->plugin->attributesAccess['base'])) {
            if ($this->plugin->attrIsWriteable('base')) {
                $smarty->assign('baseACL', 'rw');
            } else {
                $smarty->assign('baseACL', 'r');
            }
        }

        $sections = [];
        foreach ($this->plugin->attributesInfo as $section => $sectionInfo) {
            $smarty->assign('section', $sectionInfo['name']);
            $smarty->assign('sectionIcon', ($sectionInfo['icon'] ?? NULL));
            $smarty->assign('sectionId', $section);
            $sectionClasses = '';
            if (isset($sectionInfo['class'])) {
                $sectionClasses .= ' ' . join(' ', $sectionInfo['class']);
            }
            $attributes      = [];
            $readableSection = FALSE;
            foreach ($sectionInfo['attrs'] as $attr) {
                if ($attr->getAclInfo() !== FALSE) {
                    // We assign ACLs so that attributes can use them in their template code
                    $smarty->assign($attr->getAcl() . 'ACL', $this->plugin->aclGetPermissions($attr->getAcl(), NULL, $this->plugin->aclSkipWrite()));
                }
                $readable = $this->plugin->attrIsReadable($attr);
                $writable = $this->plugin->attrIsWriteable($attr);
                if (!$readableSection && ($readable || $writable)) {
                    $readableSection = TRUE;
                }
                $attr->renderAttribute($attributes, $readOnly, $readable, $writable);
            }
            $smarty->assign('attributes', $attributes);
            if (!$readableSection) {
                $sectionClasses .= ' nonreadable';
            }
            $smarty->assign('sectionClasses', $sectionClasses);
            // We fetch each section with the section template
            if (isset($sectionInfo['Template'])) {
                $displaySection = $smarty->fetch($sectionInfo['Template']);
            } else {
                $displaySection = $smarty->fetch(getTemplatePath('simpleplugin_section.tpl'));
            }
            $sections[$section] = $displaySection;
        }
        $smarty->assign("sections", $sections);
    }

    public function inheritanceDisplay (): string
    {
        if (!$this->plugin->member_of_group) {
            return "";
        }
        $class               = get_class($this->plugin);
        $attrsWrapper        = new stdClass();
        $attrsWrapper->attrs = $this->plugin->group_attrs;
        $group               = new $class($this->plugin->group_attrs['dn'], $attrsWrapper, $this->plugin->parent, $this->plugin->mainTab);
        $smarty              = getSmarty();

        $group->renderAttributes(TRUE);
        $smarty->assign("hiddenPostedInput", get_class($this->plugin) . "_posted");

        return "<h1>Inherited information:</h1><div></div>\n" . $smarty->fetch($this->plugin->templatePath);
    }
}
