<?php
declare(strict_types=1);

/**
 * Handles hooks and event processing for a SimplePlugin instance.
 */
class PluginHookManager
{
    public function __construct(
        private SimplePlugin $plugin
    ) {
    }

    /*! \brief Forward command execution requests
     *         to the pre/post hook execution method.
     *
     * \param  string  $when must be PRE or POST
     *
     * \param  string  $mode add, remove or modify
     *
     * \param  array  $addAttrs
     */
    public function handleHooks (string $when, string $mode, array $addAttrs = []): array
    {
        switch ($mode) {
            case 'add':
                return $this->callHook($when . 'CREATE', $addAttrs);

            case 'modify':
                return $this->callHook($when . 'MODIFY', $addAttrs);

            case 'remove':
                return $this->callHook($when . 'REMOVE', $addAttrs);

            default:
                trigger_error(sprintf('Invalid %s event type given: "%s"! Valid types are: add, modify, remove.', strtolower($when), $mode));
                return [];
        }
    }

    /*! \brief Forward command execution requests
     *         to the post hook execution method.
     */
    public function handlePostEvents (string $mode, array $addAttrs = []): array
    {
        /* Update foreign keys */
        if ($mode == 'remove') {
            $this->plugin->handleForeignKeys($this->plugin->dn, NULL, $mode);
        } elseif ($mode == 'modify') {
            $this->plugin->handleForeignKeys();
        }
        return $this->handleHooks('POST', $mode, $addAttrs);
    }

    /*!
     *  \brief Forward command execution requests
     *         to the pre hook execution method.
     */
    public function handlePreEvents (string $mode, array $addAttrs = []): array
    {
        $this->plugin->ldap_error = '';
        if ($this->plugin->mainTab && ($mode == 'remove')) {
            /* Store information if there was subobjects before deletion */
            $ldap = config()->getLdapLink();
            $ldap->cd($this->plugin->dn);
            $ldap->search('(objectClass=*)', ['dn'], 'one');
            $this->plugin->hadSubobjects = ($ldap->count() > 0);
        }
        return $this->handleHooks('PRE', $mode, $addAttrs);
    }

    public function fillHookAttrs (array &$addAttrs)
    {
        // Walk trough attributes list and add the plugins attributes.
        foreach ($this->plugin->attributes as $attr) {
            if (!isset($addAttrs[$attr])) {
                $addAttrs[$attr] = $this->plugin->$attr;
            }
        }
    }

    /*!
     * \brief    Calls external hooks which are defined for this plugin (fusiondirectory.conf)
     *           Replaces placeholder by class values of this plugin instance.
     *       Allows to a add special replacements.
     */
    public function callHook ($cmd, array $addAttrs = [], &$returnOutput = [], &$returnCode = NULL): array
    {
        if ($this->plugin->is_template) {
            return [];
        }

        $commands = config()->searchHooks(get_class($this->plugin), $cmd);
        $messages = [];

        foreach ($commands as $command) {
            $this->fillHookAttrs($addAttrs);

            $ui = get_userinfo();

            $addAttrs['callerDN']        = $ui->dn;
            $addAttrs['callerCN']        = $ui->cn;
            $addAttrs['callerUID']       = $ui->uid;
            $addAttrs['callerSN']        = $ui->sn;
            $addAttrs['callerGIVENNAME'] = $ui->givenName;
            $addAttrs['callerMAIL']      = $ui->mail;

            $addAttrs['dn']       = $this->plugin->dn;
            $addAttrs['location'] = config()->current['NAME'];

            if (isset($this->plugin->parent->by_object)) {
                foreach ($this->plugin->parent->by_object as $class => $object) {
                    if ($class != get_class($this->plugin)) {
                        $object->fillHookAttrs($addAttrs);
                    }
                }
            }

            if (!isset($addAttrs['base']) && isset($this->plugin->base)) {
                $addAttrs['base'] = $this->plugin->base;
            }

            $command = TemplateHandling::parseString($command, $addAttrs, 'escapeshellarg');
            Logging::debug(DEBUG_SHELL, __LINE__, __FUNCTION__, __FILE__, $command, 'Execute');
            exec($command, $arr, $returnCode);

            $command = static::passwordProtect($command);

            $returnOutput = $arr;

            if ($returnCode != 0) {
                $str = implode("\n", $arr);
                $str = static::passwordProtect($str);
                Logging::debug(DEBUG_SHELL, __LINE__, __FUNCTION__, __FILE__, $command, 'Execution failed code: ' . $returnCode);
                Logging::debug(DEBUG_SHELL, __LINE__, __FUNCTION__, __FILE__, $command, 'Output: ' . $str);
                $messages[] = new SimplePluginHookError(
                    $this->plugin,
                    $cmd,
                    $str,
                    $returnCode
                );
            } elseif (is_array($arr)) {
                $str = implode("\n", $arr);
                $str = static::passwordProtect($str);
                Logging::debug(DEBUG_SHELL, __LINE__, __FUNCTION__, __FILE__, $command, 'Output: ' . $str);
                if (!empty($str) && config()->getCfgValue('displayHookOutput', 'FALSE') == 'TRUE') {
                    MsgDialog::display('[' . get_class($this->plugin) . ' ' . strtolower($cmd) . 'trigger] ' . $command, htmlescape($str), INFO_DIALOG);
                }
            }
            unset($arr, $command, $returnCode);
        }
        return $messages;
    }

    /*! \brief This function protect the clear string password by replacing char.
     */
    public static function passwordProtect (?string $hookCommand = NULL): string
    {
        if (isset($_POST["userPassword_password"]) && !empty($_POST["userPassword_password"])) {
            if (strpos($hookCommand, $_POST["userPassword_password"]) !== FALSE) {
                $hookCommand = str_replace($_POST["userPassword_password"], '*******', $hookCommand);
            }
        }
        return $hookCommand;
    }
}
