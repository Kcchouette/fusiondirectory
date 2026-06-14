<?php
declare(strict_types=1);
/*
  This code is part of FusionDirectory (http://www.fusiondirectory.org/)

  Copyright (C) 2012-2019  FusionDirectory

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301, USA.
*/

/*!
 * \file class_simplePlugin.inc
 * Source code for the class SimplePlugin
 */

/*! \brief This class is made for easy plugin creation for editing LDAP attributes
 *
 */

class SimplePlugin implements SimpleTab
{
  /*! \brief This attribute store all information about attributes */
  public array $attributesInfo = [];

  /*! \brief This attribute store references toward attributes
   *
   * associative array that stores attributeLdapName => reference on object
   */
  public array $attributesAccess = [];
  // Thisb bolean allows children class to get readOnly automatically via static state or class-level state.
  private static $user_locked = FALSE;

  public mixed $displayPlugin = null;

  /*!
    \brief Mark plugin as account

    Defines whether this plugin is defined as an account or not.
    This has consequences for the plugin to be saved from tab
    mode. If it is set to 'FALSE' the tab will call the delete
    function, else the save function. Should be set to 'TRUE' if
    the construtor detects a valid LDAP object.

    \sa SimplePlugin::isThisAccount()
   */
  public bool $is_account            = false;
  public bool $initially_was_account = false;
  protected bool $ignore_account     = false;

  public string $acl_category = '';

  /*! \brief dn of the opened object */
  public string $dn = '';

  /*! \brief original dn of the opened object */
  public string $orig_dn = '';

  /*!
   * \brief Reference to parent object
   *
   * This variable is used when the plugin is included in tabs
   * and keeps reference to the tab class. Communication to other
   * tabs is possible by 'name'. So the 'fax' plugin can ask the
   * 'UserInfo' plugin for the fax number.
   *
   * \sa simpleTabs
   */
  public ?object $parent = null;

  /*!
    \brief Mark plugin as template

    Defines whether we are editing a template or a normal object.
    Has consequences on the way execute() shows the formular and how
    save() puts the data to LDAP.
   */
  public bool $is_template = false;

  /*!
    \brief Represent temporary LDAP data

    This should only be used internally.
   */
  public array $attrs = [];

  /*! \brief The objectClasses set by this tab */
  public array $objectclasses = [];

  /*! \brief The state of the attributes when we opened the object */
  protected array $saved_attributes = []; // Note : This is overwritten during postSave logic
  // Requiring therefore a save to threat this during logging mechanism.
  public array $beforeLdapChangeAttributes = [];

  /*! \brief Do we want a header allowing to able/disable this plugin */
  public bool $displayHeader = false;

  /*! \brief Is this plugin the main tab, the one that handle the object itself */
  public bool $mainTab = false;

  public string $header = "";

  public ?string $templatePath = null;

  public bool $dialog = false;

  /*! \brief Are we executed in a edit-mode environment? (this is FALSE if we're called from management, TRUE if we're called from a main.inc)
   */
  public bool $needEditMode = false;

  /*! \brief Attributes that needs to be initialized before the others */
  public array $preInitAttributes = [];

  /*! \brief FALSE to disable inheritance. Array like array ('objectClass' => 'attribute') to specify oc of the groups it might be inherited from
   */
  public string $inheritance     = '';
  public bool $member_of_group = false;
  protected ?string $editingGroup   = null;
  public array $group_attrs     = [];

  /*! \brief Used when the entry is opened as "readonly" due to locks */
  protected bool $read_only = false;

  /*! \brief Last LDAP error (used by logging calls from post_* methods) */
  public ?string $ldap_error = null;

  /*!
   * \brief Object entry CSN
   *
   * If an entry was edited while we have edited the entry too,
   * an error message will be shown.
   * To configure this check correctly read the FAQ.
   */
  public string $entryCSN = '';

   public bool $hadSubobjects = false;

   /** @var AclChecker ACL check operations */
    public AclChecker $acl;

   /** @var PluginRenderer Rendering and display */
    public PluginRenderer $renderer;

   /** @var PluginHookManager Hooks and events */
    public PluginHookManager $hooks;

   /** @var LdapReader LDAP loading and saving */
    public LdapReader $ldapReader;

   /*! \brief constructor
   *
   *  \param string $dn The dn of this instance
   *  \param Object $object An object to copy values from
   *  \param Object $parent A parent instance, usually a simpleTabs instance.
   *  \param boolean $mainTab Whether or not this is the main tab
   *  \param array $attributesInfo An attributesInfo array, if NULL, getAttributesInfo will be used.
   *
   */
   function __construct (?string $dn = NULL, $object = NULL, $parent = NULL, bool $mainTab = FALSE, ?array $attributesInfo = NULL)
   {
     /* Initialize facade components */
    $this->acl       = new AclChecker($this);
    $this->renderer  = new PluginRenderer($this);
    $this->hooks     = new PluginHookManager($this);
    $this->ldapReader = new LdapReader($this);

    $this->dn      = $dn;
    $this->parent  = $parent;
    $this->mainTab = $mainTab;

    // This class-level state allows children to get readOnly automatically.
    if (self::$user_locked) {
      $this->read_only = TRUE;
    }

    try {
      $plInfo = Pluglist::pluginInfos(get_class($this));
    } catch (UnknownClassException $e) {
      /* May happen in special cases like setup */
      $plInfo = [];
    }

    if (empty($this->objectclasses) && isset($plInfo['plObjectClass'])) {
      $this->objectclasses = $plInfo['plObjectClass'];
    }

    if ($attributesInfo === NULL) {
      $attributesInfo = $this->getAttributesInfo();
    }
    if (!$this->displayHeader) {
      // If we don't display the header to activate/deactive the plugin, that means it's always activated
      $this->ignore_account = TRUE;
    }

    $this->attributesInfo = [];
    foreach ($attributesInfo as $section => $sectionInfo) {
      $attrs = [];
      foreach ($sectionInfo['attrs'] as $attr) {
        $name = $attr->getLdapName();
        if (isset($attrs[$name])) {
          // We check that there is no duplicated attribute name
          trigger_error("Duplicated attribute LDAP name '$name' in a simplePlugin subclass");
        }
        // We make so that attribute have their LDAP name as key
        // That allow the plugin to use $this->attributesInfo[$sectionName]['attrs'][$myLdapName] to retreive the attribute info.
        $attrs[$name] = $attr;
      }
      $sectionInfo['attrs']           = $attrs;
      $this->attributesInfo[$section] = $sectionInfo;
      foreach ($this->attributesInfo[$section]['attrs'] as $name => $attr) {
        if (isset($this->attributesAccess[$name])) {
          // We check that there is no duplicated attribute name
          trigger_error("Duplicated attribute LDAP name '$name' in a simplePlugin subclass");
        }
        $this->attributesAccess[$name] =& $this->attributesInfo[$section]['attrs'][$name];
        unset($this->$name);
      }
    }

    /* Ensure that we've a valid acl_category set */
    if (empty($this->acl_category) && isset($plInfo['plCategory'])) {
      $c = key($plInfo['plCategory']);
      if (is_numeric($c)) {
        $c = $plInfo['plCategory'][$c];
      }
      $this->acl_category = $c . '/';
    }

    /* Check if this entry was opened in read only mode */
    if (($this->dn != 'new') &&
      isset($_POST['open_readonly']) &&
      Session::is_set('LOCK_CACHE')
    ) {
      $cache = Session::get('LOCK_CACHE');
      if (isset($cache['READ_ONLY'][$this->dn])) {
        $this->read_only = TRUE;
      }
    }

    /* Load LDAP data */
    if (($this->dn != 'new' && $this->dn !== NULL) || ($object !== NULL)) {
      /* Load data to 'attrs' */
      if ($object !== NULL) {
        /* From object */
        $this->attrs = $object->attrs;
        if (isset($object->is_template)) {
          $this->setTemplate($object->is_template);
        }
      } else {
        /* From LDAP */
        $ldap = config()->getLdapLink();
        $ldap->cat($this->dn);
        $this->attrs = $ldap->fetch(TRUE);
        if (empty($this->attrs)) {
          throw new NonExistingLdapNodeException($this->dn);
        }
        if ($this->mainTab) {
          $this->entryCSN = getEntryCSN($this->dn);
          /* Make sure that initially_was_account is TRUE if we loaded an LDAP node,
           *  even if it’s missing an objectClass */
          $this->is_account = TRUE;
        }
      }

      /* Set the template flag according to the existence of objectClass fdTemplate */
      if (isset($this->attrs['objectClass']) && in_array_ics('fdTemplate', $this->attrs['objectClass'])) {
        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, 'found', 'Template check');
        $this->setTemplate(TRUE);
        $this->templateLoadAttrs($this->attrs);
      }

      /* Is Account? */
      if ($this->isThisAccount($this->attrs)) {
        $this->is_account = TRUE;
        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, get_class($this), 'Tab active');
      }
    }

    if (is_array($this->inheritance)) {
      /* Check group membership */
      $ldap = config()->getLdapLink();
      $ldap->cd(config()->current['BASE']);
      foreach ($this->inheritance as $oc => $at) {
        if ($this->mainTab) {
          $filter = '(&(objectClass=' . $oc . ')(' . $at . '=' . ldap_escape_f($this->dn) . '))';
        } else {
          $filter = '(&(objectClass=' . $oc . ')' . static::getLdapFilter() . '(' . $at . '=' . ldap_escape_f($this->dn) . '))';
        }
        $ldap->search($filter, $this->attributes);
        if ($ldap->count() == 1) {
          $this->member_of_group = TRUE;
          $attrs                 = $ldap->fetch();
          $this->group_attrs     = $attrs;
          break;
        }
      }
    }

    /* Save initial account state */
    $this->initially_was_account = $this->is_account;

    $this->loadAttributes();

    $this->prepareSavedAttributes();

    $this->orig_dn = $dn;

    if ($this->mainTab) {
      $this->is_account = TRUE;
    }

    if (!isset($this->templatePath)) {
      $this->templatePath = get_template_path('simpleplugin.tpl');
    }
  }


  public static function setUserLocked (bool $locked): void
  {
    self::$user_locked = $locked;
  }

  protected function loadAttributes ()
  {
    $this->ldapReader->loadAttributes();
  }

  function isThisAccount ($attrs)
  {
    return $this->ldapReader->isThisAccount($attrs);
  }

  function setTemplate (bool $bool)
  {
    $this->is_template = $bool;
    if ($this->is_template && $this->mainTab) {
      /* Unshift special section for template infos */
      $this->attributesInfo                   = array_merge(
        [
          '_template'       => [
            'class' => ['fullwidth'],
            'name'  => _('Template settings'),
            'attrs' => [
              '_template_cn' => new StringAttribute(
                _('Template name'), _('This is the name of the template'),
                '_template_cn', TRUE,
                '', 'template_cn'
              )
            ]
          ],
          '_template_dummy' => [
            'class' => ['invisible'],
            'name'  => '_template_dummy',
            'attrs' => []
          ]
        ],
        $this->attributesInfo
      );
      $this->attributesAccess['_template_cn'] =& $this->attributesInfo['_template']['attrs']['_template_cn'];
      $this->attributesAccess['_template_cn']->setInLdap(FALSE);
      $this->attributesAccess['_template_cn']->setValue($this->_template_cn);
      $this->attributesAccess['_template_cn']->setParent($this);
      unset($this->_template_cn);
    }
  }

  protected function templateLoadAttrs (array $template_attrs)
  {
    if ($this->mainTab) {
      $this->_template_cn = $template_attrs['cn'][0];
    }
    $this->attrs = TemplateHandling::fieldsFromLDAP($template_attrs);
  }

  protected function templateSaveAttrs ()
  {
    $ldap = config()->getLdapLink();
    $ldap->cat($this->dn);
    $template_attrs = $ldap->fetch(TRUE);
    if (!$template_attrs) {
      if (!$this->mainTab) {
        trigger_error('It seems main tab has not been saved.');
      }
      $template_attrs = [
        'objectClass'     => ['fdTemplate'],
        'fdTemplateField' => []
      ];
    }
    $template_attrs = TemplateHandling::fieldsToLDAP($template_attrs, $this->attrs);
    if ($this->mainTab) {
      $template_attrs['cn'] = $this->_template_cn;
    }
    return $template_attrs;
  }

  /*! \brief This function returns an LDAP filter for this plugin object classes
   */
  function getObjectClassFilter ()
  {
    return $this->ldapReader->getObjectClassFilter();
  }

  /*! \brief This function allows to use the syntax $plugin->attributeName to get attributes values
   *
   * It calls the getValue method on the concerned attribute
   * It also adds the $plugin->attribtues syntax to get attributes list
   */
  public function __get ($name)
  {
    if ($name == 'attributes') {
      $plugin = $this;
      return array_filter(array_keys($this->attributesAccess),
        function ($a) use ($plugin) {
          return $plugin->attributesAccess[$a]->isInLdap();
        }
      );
    } elseif (isset($this->attributesAccess[$name])) {
      return $this->attributesAccess[$name]->getValue();
    } else {
      /* Calling default behaviour */
      return $this->$name;
    }
  }

  /*! \brief This function allows to use the syntax $plugin->attributeName to set attributes values

    It calls the setValue method on the concerned attribute
   */
  public function __set ($name, $value)
  {
    if ($name == 'attributes') {
      trigger_error('Tried to set obsolete attribute "attributes" (it is now dynamic)');
    } elseif (isset($this->attributesAccess[$name])) {
      $this->attributesAccess[$name]->setValue($value);
    } else {
      /* Calling default behaviour */
      $this->$name = $value;
    }
  }

  /*! \brief This function allows to use the syntax isset($plugin->attributeName)

    It returns FALSE if the attribute has an empty value.
   */
  public function __isset ($name)
  {
    if ($name == 'attributes') {
      return TRUE;
    }
    return isset($this->attributesAccess[$name]);
  }

  /*! \brief This function returns the dn this object should have
   */
  public function computeDn (): string
  {
    return $this->ldapReader->computeDn();
  }

  protected function addAttribute (string $section, \FusionDirectory\Core\SimplePlugin\Attribute $attr)
  {
    $name                                           = $attr->getLdapName();
    $this->attributesInfo[$section]['attrs'][$name] = $attr;
    $this->attributesAccess[$name]                  =& $this->attributesInfo[$section]['attrs'][$name];
    $this->attributesAccess[$name]->setParent($this);
    unset($this->$name);
  }

  protected function removeAttribute (string $section, string $id)
  {
    unset($this->attributesInfo[$section]['attrs'][$id]);
    unset($this->attributesAccess[$id]);
  }

  /*!
   * \brief Returns a list of all available departments for this object.
   *
   * If this object is new, all departments we are allowed to create a new object in are returned.
   * If this is an existing object, return all deps we are allowed to move this object to.
   * Used by BaseSelectorAttribute
   *
   * \return array [dn] => "..name"  // All deps. we are allowed to act on.
  */
  function getAllowedBases (): array
  {
    $deps = [];

    /* Is this a new object ? Or just an edited existing object */
    $departmentTree = config()->getDepartmentTree();
    foreach ($departmentTree as $dn => $name) {
      if (
        (!$this->initially_was_account && $this->aclIsCreateable($dn)) ||
        ($this->initially_was_account && $this->aclIsMoveable($dn))
      ) {
        $deps[$dn] = $name;
      }
    }

    /* Add current base */
    if (isset($this->base) && isset($departmentTree[$this->base])) {
      $deps[$this->base] = $departmentTree[$this->base];
    } elseif (strtolower($this->dn) != strtolower(config()->current['BASE'])) {
      trigger_error('Cannot return list of departments, no default base found in class ' . get_class($this) . '. (base is "' . $this->base . '")');
    }
    return $deps;
  }

  /*!
   * \brief Set acl category
   *
   * \param string $category
   */
  function setAclCategory (string $category)
  {
    $this->acl_category = "$category/";
  }

  /*!
    * \brief Move ldap entries from one place to another
    *
    * \param  string  $src_dn the source DN.
    *
    * \param  string  $dst_dn the destination DN.
    *
    * \return TRUE on success, error string on failure
    */
  function move (string $src_dn, string $dst_dn)
  {
    $ui = user_info();

    /* Do not move if only case has changed */
    if (strtolower($src_dn) == strtolower($dst_dn)) {
      return TRUE;
    }

    /* Try to move with ldap routines */
    $ldap = config()->getLdapLink();
    $ldap->cd(config()->current['BASE']);
    try {
      $ldap->createMissingTrees(preg_replace('/^[^,]+,/', '', $dst_dn));
    } catch (FusionDirectoryError $error) {
      $error->display();
    }
    if (!$ldap->renameDn($src_dn, $dst_dn)) {
      Logging::log('error', 'ldap', "FROM: $src_dn -- TO: $dst_dn", [], 'Ldap Protocol v3 implementation error, ldap_rename failed: ' . $ldap->getError());
      Logging::debug(DEBUG_LDAP, __LINE__, __FUNCTION__, __FILE__, "Rename failed FROM: $src_dn  -- TO:  $dst_dn",
                     'Ldap Protocol v3 implementation error. Error:' . $ldap->getError());
      return $ldap->getError();
    }

    /* Update userinfo if necessary */
    if (preg_match('/' . preg_quote($src_dn, '/') . '$/i', $ui->dn)) {
      $ui->dn = preg_replace('/' . preg_quote($src_dn, '/') . '$/i', $dst_dn, $ui->dn);
    }

    /* Check if departments were moved. If so, force the reload of $config departments cache */
    $ldap->cd($dst_dn);
    $ldap->search('(objectClass=gosaDepartment)', ['dn']);
    if ($ldap->count()) {
      config()->resetDepartmentCache();
      $ui->resetAclCache();
    }

    $this->handleForeignKeys($src_dn, $dst_dn);
    return TRUE;
  }

  function getRequiredAttributes (): array
  {
    $tmp = [];
    foreach ($this->attributesAccess as $attr) {
      if ($attr->isRequired()) {
        $tmp[] = $attr->getLdapName();
      }
    }
    return $tmp;
  }

  function editingGroup ()
  {
    if ($this->editingGroup == NULL) {
      if (isset($this->parent)) {
        $this->editingGroup = (get_class($this->parent->getBaseObject()) == 'ogroup');
      } else {
        return NULL;
      }
    }
    return $this->editingGroup;
  }

  /*! \brief Indicates if this object is opened as read-only (because of locks) */
  function readOnly ()
  {
    return $this->read_only;
  }

  function execute (): string
  {
    return $this->renderer->execute();
  }

  public function update (): bool
  {
    if (is_object($this->dialog)) {
      $dialogState = $this->dialog->update();
      if ($dialogState === FALSE) {
        $this->closeDialog();
      }
    }

    return TRUE;
  }

  /*! \brief This function display the plugin and return the html code
   */
  public function render (): string
  {
    return $this->renderer->render();
  }

  public function getDisplayHeaderInfos (): array
  {
    return $this->renderer->getDisplayHeaderInfos();
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
  function showHeader (string $buttonHtmlText, string $htmlText, bool $plugin_enabled, bool $button_disabled = FALSE, string $name = 'modify_state'): string
  {
    return $this->renderer->showHeader($buttonHtmlText, $htmlText, $plugin_enabled, $button_disabled, $name);
  }

  /*!
   * \brief Test whether a tab is active
   */
  public function isActive (): bool
  {
    return ($this->is_account || $this->ignore_account);
  }

  /*!
   * \brief Test whether a tab can be deactivated
   */
  public function isActivatable (): bool
  {
    return $this->displayHeader;
  }

  /*! \brief Check if logged in user have enough right to read this attribute value
   *
   * \param mixed $attr Attribute object or name (in this case it will be fetched from attributesAccess)
   */
  function attrIsReadable ($attr): bool
  {
    return $this->acl->attrIsReadable($attr);
  }

  /*! \brief Check if logged in user have enough right to write this attribute value
   *
   * \param mixed $attr Attribute object or name (in this case it will be fetched from attributesAccess)
   */
  function attrIsWriteable ($attr): bool
  {
    return $this->acl->attrIsWriteable($attr);
  }

  /*!
   * \brief Get LDAP base to use for ACL checks
   */
  function getAclBase (bool $callParent = TRUE): string
  {
    return $this->acl->getAclBase($callParent);
  }

  function renderAttributes (bool $readOnly = FALSE)
  {
    $this->renderer->renderAttributes($readOnly);
  }

  function inheritanceDisplay (): string
  {
    return $this->renderer->inheritanceDisplay();
  }

  /*! \brief This function allows you to open a dialog
   *
   *  \param FusionDirectoryDialog $dialog The dialog object
   */
  function openDialog (FusionDirectoryDialog $dialog)
  {
    $this->dialog = $dialog;
  }

  /*! \brief This function closes the dialog
   */
  function closeDialog ()
  {
    $this->dialog = NULL;
  }

  public function setNeedEditMode (bool $bool)
  {
    $this->needEditMode = $bool;
  }

  protected function aclSkipWrite (): bool
  {
    return $this->acl->aclSkipWrite();
  }

  /*! \brief Can we write the attribute */
  function aclIsWriteable ($attribute, bool $skipWrite = FALSE): bool
  {
    return $this->acl->aclIsWriteable($attribute, $skipWrite);
  }

  /*!
   * \brief Can we read the acl
   *
   * \param string $attribute
   */
  function aclIsReadable ($attribute): bool
  {
    return $this->acl->aclIsReadable($attribute);
  }

  /*!
   * \brief Can we create the object
   *
   * \param string $base Empty string
   */
  function aclIsCreateable (?string $base = NULL): bool
  {
    return $this->acl->aclIsCreateable($base);
  }

  /*!
   * \brief Can we delete the object
   *
   * \param string $base Empty string
   */
  function aclIsRemoveable (?string $base = NULL): bool
  {
    return $this->acl->aclIsRemoveable($base);
  }

  /*!
   * \brief Can we move the object
   *
   * \param string $base Empty string
   */
  function aclIsMoveable (?string $base = NULL): bool
  {
    return $this->acl->aclIsMoveable($base);
  }

  /*! \brief Test if there are ACLs for this plugin */
  function aclHasPermissions (): bool
  {
    return $this->acl->aclHasPermissions();
  }

  /*! \brief Get the acl permissions for an attribute or the plugin itself */
  function aclGetPermissions ($attribute = '0', ?string $base = NULL, bool $skipWrite = FALSE): string
  {
    return $this->acl->aclGetPermissions($attribute, $base, $skipWrite);
  }

  /*! \brief This function removes the object from LDAP
   */
  function remove (bool $fulldelete = FALSE): array
  {
    if (!$this->initially_was_account) {
      return [];
    }

    if (!$fulldelete && !$this->aclIsRemoveable()) {
      trigger_error('remove was called on a tab without enough ACL rights');
      return [];
    }

    $this->prepareRemove();
    if ($this->is_template) {
      $this->attrs            = $this->templateSaveAttrs();
      $this->saved_attributes = [];
    }
    /* Pre hooks */
    $errors = $this->preRemove();
    if (!empty($errors)) {
      return $errors;
    }
    $errors = $this->ldapRemove();
    if (!empty($errors)) {
      return $errors;
    }
    $this->postRemove();
    return [];
  }

  /* Remove FusionDirectory attributes */
  protected function prepareRemove ()
  {
    $this->ldapReader->prepareRemove();
  }

  protected function preRemove ()
  {
    return $this->ldapReader->preRemove();
  }

  protected function ldapRemove (): array
  {
    return $this->ldapReader->ldapRemove();
  }

  protected function postRemove ()
  {
    $this->ldapReader->postRemove();
  }

  /*! \brief This function handle $_POST informations
   */
  function saveObject ()
  {
    trigger_error('obsolete');
    $this->readPost();
  }

  /*! \brief This function handle $_POST informations
   */
  function readPost ()
  {
    Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->dn, 'readPost');

    if ($this->displayHeader && isset($_POST[get_class($this) . '_modify_state'])) {
      if ($this->is_account && $this->aclIsRemoveable()) {
        $this->is_account = FALSE;
      } elseif (!$this->is_account && $this->aclIsCreateable()) {
        $this->is_account = TRUE;
      }
    }
    if (is_object($this->dialog)) {
      $this->dialog->readPost();
    }
    if (isset($_POST[get_class($this) . '_posted'])) {
      // If our form has been posted
      // A first pass that loads the post values
      foreach ($this->attributesInfo as $sectionInfo) {
        foreach ($sectionInfo['attrs'] as $attr) {
          if ($this->attrIsWriteable($attr)) {
            // Each attribute know how to read its value from POST
            $attr->loadPostValue();
          }
        }
      }
      // A second one that applies them. That allow complex stuff such as attribute disabling
      foreach ($this->attributesInfo as $sectionInfo) {
        foreach ($sectionInfo['attrs'] as $attr) {
          if ($this->attrIsWriteable($attr)) {
            // Each attribute know how to read its value from POST
            $attr->applyPostValue();
          }
        }
      }
    }
  }

  protected function prepareSavedAttributes ()
  {
    /* Prepare saved attributes */
    $this->saved_attributes = $this->attrs;
    // Fill for differenciation in the post save as saved_attributes will be modified.
    $this->beforeLdapChangeAttributes = $this->saved_attributes;

    foreach (array_keys($this->saved_attributes) as $index) {
      if (is_numeric($index)) {
        unset($this->saved_attributes[$index]);
        continue;
      }

      list($attribute,) = explode(';', $index, 2);
      if (!in_array_ics($index, $this->attributes) && !in_array_ics($attribute, $this->attributes) && strcasecmp('objectClass', $attribute)) {
        unset($this->saved_attributes[$index]);
        continue;
      }

      if (isset($this->saved_attributes[$index][0])) {
        if (!isset($this->saved_attributes[$index]['count'])) {
          $this->saved_attributes[$index]['count'] = count($this->saved_attributes[$index]);
        }
        if ($this->saved_attributes[$index]['count'] == 1) {
          $tmp = $this->saved_attributes[$index][0];
          unset($this->saved_attributes[$index]);
          $this->saved_attributes[$index] = $tmp;
          continue;
        }
      }
      unset($this->saved_attributes[$index]['count']);
    }
  }

  /*!
   * \brief Remove attributes, empty arrays, arrays
   * single attributes that do not differ
   */
  function cleanup ()
  {
    foreach ($this->attrs as $index => $value) {
      /* Convert arrays with one element to non arrays, if the saved
         attributes are no array, too */
      if (is_array($this->attrs[$index]) &&
        (count($this->attrs[$index]) == 1) &&
        isset($this->saved_attributes[$index]) &&
        !is_array($this->saved_attributes[$index])) {
        $this->attrs[$index] = $this->attrs[$index][0];
      }

      /* Remove emtpy arrays if they do not differ */
      if (is_array($this->attrs[$index]) &&
        (count($this->attrs[$index]) == 0) &&
        !isset($this->saved_attributes[$index])) {
        unset($this->attrs[$index]);
        continue;
      }

      /* Remove single attributes that do not differ */
      if (!is_array($this->attrs[$index]) &&
        isset($this->saved_attributes[$index]) &&
        !is_array($this->saved_attributes[$index]) &&
        ($this->attrs[$index] == $this->saved_attributes[$index])) {
        unset($this->attrs[$index]);
        continue;
      }

      /* Remove arrays that do not differ */
      if (is_array($this->attrs[$index]) &&
        isset($this->saved_attributes[$index]) &&
        is_array($this->saved_attributes[$index]) &&
        !array_differs($this->attrs[$index], $this->saved_attributes[$index])) {
        unset($this->attrs[$index]);
        continue;
      }
    }
  }

  function prepareNextCleanup ()
  {
    /* Update saved attributes and ensure that next cleanups will be successful too */
    foreach ($this->attrs as $name => $value) {
      $this->saved_attributes[$name] = $value;
    }
  }

  /*! \brief This function saves the object in the LDAP
   */
  function save (): array
  {
    Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->dn, "save");
    $errors = $this->prepareSave();
    if (!empty($errors)) {
      return $errors;
    }
    if ($this->is_template) {
      $errors = TemplateHandling::checkFields($this->attrs);
      if (!empty($errors)) {
        return $errors;
      }
      $this->attrs            = $this->templateSaveAttrs();
      $this->saved_attributes = [];
    }
    $this->cleanup();
    if (!$this->shouldSave()) {
      return []; /* Nothing to do here */
    }
    /* Pre hooks */
    $errors = $this->preSave();
    if (!empty($errors)) {
      return $errors;
    }
    /* LDAP save itself */
    $errors = $this->ldapSave();
    if (!empty($errors)) {
      return $errors;
    }
    $this->prepareNextCleanup();
    /* Post hooks and logging */
    $this->postSave();
    return [];
  }

  protected function shouldSave (): bool
  {
    if ($this->mainTab && !$this->initially_was_account) {
      return TRUE;
    }
    return !empty($this->attrs);
  }

  /* Used by prepareSave and Template::apply */
  public function mergeObjectClasses (array $oc): array
  {
    return array_merge_unique($oc, $this->objectclasses);
  }

  protected function prepareSave (): array
  {
    return $this->ldapReader->prepareSave();
  }

  protected function preSave (): array
  {
    return $this->ldapReader->preSave();
  }

  protected function ldapSave (): array
  {
    return $this->ldapReader->ldapSave();
  }

  protected function postSave ()
  {
    $this->ldapReader->postSave();
  }

  protected function getAuditAttributesListFromConf (): array
  {
    return $this->ldapReader->getAuditAttributesListFromConf();
  }

  private function getModifiedAttributesValues (): array
  {
    return $this->ldapReader->getModifiedAttributesValues();
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
  protected function handleHooks (string $when, string $mode, array $addAttrs = []): array
  {
    return $this->hooks->handleHooks($when, $mode, $addAttrs);
  }

  /*! \brief Forward command execution requests
   *         to the post hook execution method.
   */
  function handlePostEvents (string $mode, array $addAttrs = [])
  {
    return $this->hooks->handlePostEvents($mode, $addAttrs);
  }

  /*!
   *  \brief Forward command execution requests
   *         to the pre hook execution method.
   */
  function handlePreEvents (string $mode, array $addAttrs = []): array
  {
    return $this->hooks->handlePreEvents($mode, $addAttrs);
  }

  function fillHookAttrs (array &$addAttrs)
  {
    $this->hooks->fillHookAttrs($addAttrs);
  }

  /*!
   * \brief    Calls external hooks which are defined for this plugin (fusiondirectory.conf)
   *           Replaces placeholder by class values of this plugin instance.
   *       Allows to a add special replacements.
   */
  function callHook ($cmd, array $addAttrs = [], &$returnOutput = [], &$returnCode = NULL): array
  {
    return $this->hooks->callHook($cmd, $addAttrs, $returnOutput, $returnCode);
  }

  /*! \brief This function protect the clear string password by replacing char.
   */
  protected static function passwordProtect (?string $hookCommand = NULL): string
  {
    return PluginHookManager::passwordProtect($hookCommand);
  }

  /*! \brief This function checks the attributes values and yell if something is wrong
   */
  function check (): array
  {
    Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->dn, 'check');
    $messages = [];

    foreach ($this->attributesInfo as $sectionInfo) {
      foreach ($sectionInfo['attrs'] as $attr) {
        $error = $attr->check();
        if (!empty($error)) {
          if (is_array($error)) {
            $messages = array_merge($messages, $error);
          } else {
            $messages[] = $error;
          }
        }
      }
    }

    $error = $this->callHook('CHECK', ['nbCheckErrors' => count($messages)], $returnOutput);
    if (!empty($error)) {
      $messages = array_merge($messages, $error);
    } elseif (!empty($returnOutput)) {
      $messages[] = join("\n", $returnOutput);
    }

    /* Check entryCSN */
    if (!empty($this->entryCSN)) {
      $current_csn = getEntryCSN($this->dn);
      if (($current_csn != $this->entryCSN) && !empty($current_csn)) {
        $this->entryCSN = $current_csn;
        $messages[]     = _('The object has changed since being opened in FusionDirectory. All changes that may be done by others will get lost if you save this entry!');
      }
    }

    return $messages;
  }

  function handleForeignKeys (?string $olddn = NULL, ?string $newdn = NULL, string $mode = 'move')
  {
    if (($olddn !== NULL) && ($olddn == $newdn)) {
      return;
    }
    if ($this->is_template) {
      return;
    }
    $this->browseForeignKeys(
      'handle_' . $mode,
      $olddn,
      $newdn
    );
  }

  function browseForeignKeys (string $mode, $param1 = NULL, $param2 = NULL)
  {
    $subobjects = FALSE;
    if (preg_match('/^handle_/', $mode)) {
      $olddn   = $param1;
      $newdn   = $param2;
      $classes = [get_class($this)];
      if (($olddn != $newdn) && $this->mainTab) {
        if ($newdn === NULL) {
          $subobjects = $this->hadSubobjects;
        } else {
          $ldap = config()->getLdapLink();
          $ldap->cd($newdn);
          $ldap->search('(objectClass=*)', ['dn'], 'one');
          $subobjects = ($ldap->count() > 0);
        }
      }
    } elseif ($mode == 'references') {
      $classes = array_keys($this->parent->by_object);
    }
    // We group by objectType concerned
    $foreignRefs = [];
    if ($subobjects) {
      $field = 'dn';
      /* Special treatment for foreign keys on DN when moving an object
       * All references on DN are treated on subobjects */
      foreach (pluglist()->dnForeignRefs as $ref) {
        $class     = $ref[0];
        $ofield    = $ref[1];
        $filter    = $ref[2];
        $filtersub = $ref[3];
        if ($filtersub == '*') {
          if (config()->get_cfg_value('wildcardForeignKeys', 'TRUE') == 'TRUE') {
            $filtersub = $ofield . '=*';
          } else {
            continue;
          }
        }
        if ($class == 'aclAssignment') {
          /* Special case: aclAssignment foreignKey is ignored on department types as it’s handled by the aclAssignment objectType */
          $objectTypes = ['ACLASSIGNMENT'];
        } elseif (is_subclass_of($class, 'SimpleService')) {
          $objectTypes = ['SERVER'];
        } else {
          $objectTypes = [];
          $cinfos      = Pluglist::pluginInfos($class);
          foreach ($cinfos['plObjectType'] as $key => $objectType) {
            if (!is_numeric($key)) {
              $objectType = $key;
            }
            if (preg_match('/^ogroup-/i', $objectType)) {
              $objectType = 'OGROUP';
            }
            $objectTypes[] = strtoupper($objectType);
          }
          $objectTypes = array_unique($objectTypes);
        }
        foreach ($objectTypes as $objectType) {
          $oldvalue = $olddn;
          $newvalue = $newdn;

          $foreignRefs[$objectType]['refs'][$class][$ofield][$field]
                  = [
            'tab'      => $classes[0],
            'field'    => $field,
            'oldvalue' => $oldvalue,
            'newvalue' => $newvalue,
          ];
          $filter = TemplateHandling::parseString($filtersub, ['oldvalue' => $oldvalue, 'newvalue' => $newvalue], 'ldap_escape_f');
          if (!preg_match('/^\(.*\)$/', $filter)) {
            $filter = '(' . $filter . ')';
          }
          $foreignRefs[$objectType]['filters'][$filter] = $filter;
        }
      }
    }
    foreach ($classes as $tabclass) {
      try {
        $infos = Pluglist::pluginInfos($tabclass);
        foreach ($infos['plForeignRefs'] as $field => $refs) {
          if (preg_match('/^handle_/', $mode)) {
            if (
              (($newdn !== NULL) && ($field != 'dn') && ($mode == 'handle_move')) ||
              (($newdn === NULL) && ($olddn === NULL) && (($field == 'dn') || (!$this->attributeHaveChanged($field))))
            ) {
              // Move action, ignore other fields than dn
              // Edit action, ignore dn changes or attributes which did not change
              continue;
            }
            // else = delete action, all fields are concerned, nothing to do here
          }
          foreach ($refs as $ref) {
            $class  = $ref[0];
            $ofield = $ref[1];
            $filter = $ref[2];
            $cinfos = Pluglist::pluginInfos($class);
            if ($class == 'aclAssignment') {
              /* Special case: aclAssignment foreignKey is ignored on department types as it’s handled by the aclAssignment objectType */
              $objectTypes = ['ACLASSIGNMENT'];
            } elseif (is_subclass_of($class, 'SimpleService')) {
              $objectTypes = ['SERVER'];
            } else {
              $objectTypes = [];
              foreach ($cinfos['plObjectType'] as $key => $objectType) {
                if (!is_numeric($key)) {
                  $objectType = $key;
                }
                if (preg_match('/^ogroup-/i', $objectType)) {
                  $objectType = 'OGROUP';
                }
                $objectTypes[] = $objectType;
              }
              $objectTypes = array_unique($objectTypes);
            }
            foreach ($objectTypes as $objectType) {
              if (preg_match('/^handle_/', $mode)) {
                if ($field == 'dn') {
                  $oldvalue = $olddn;
                  $newvalue = $newdn;
                } elseif (($olddn !== NULL) && ($newdn === NULL)) {
                  $oldvalue = $this->attributeInitialValue($field);
                  $newvalue = NULL;
                } else {
                  $oldvalue = $this->attributeInitialValue($field);
                  $newvalue = $this->attributeValue($field);
                }
                $foreignRefs[$objectType]['refs'][$class][$ofield][$field]
                        = [
                  'tab'      => $tabclass,
                  'field'    => $field,
                  'oldvalue' => $oldvalue,
                  'newvalue' => $newvalue,
                ];
                $filter = TemplateHandling::parseString($filter, ['oldvalue' => $oldvalue, 'newvalue' => $newvalue], 'ldap_escape_f');
              } elseif ($mode == 'references') {
                $foreignRefs[$objectType]['refs'][$class]['name'] = $cinfos['plShortName'];

                $foreignRefs[$objectType]['refs'][$class]['fields'][$ofield][$field]
                        = [
                  'tab'     => $tabclass,
                  'field'   => $field,
                  'tabname' => $this->parent->by_name[$tabclass],
                  'value'   => $this->parent->by_object[$tabclass]->$field,
                ];
                $filter = TemplateHandling::parseString($filter, ['oldvalue' => $this->parent->by_object[$tabclass]->$field], 'ldap_escape_f');
              }
              if (!preg_match('/^\(.*\)$/', $filter)) {
                $filter = '(' . $filter . ')';
              }
              $foreignRefs[$objectType]['filters'][$filter] = $filter;
            }
          }
        }
      } catch (UnknownClassException $e) {
        /* May happen in special cases like setup */
        continue;
      }
    }

    /* Back up POST content */
    $SAVED_POST = $_POST;
    $refs       = [];
    // For each concerned objectType
    foreach ($foreignRefs as $objectType => $tabRefs) {
      // Compute filter
      $filters = array_values($tabRefs['filters']);
      $filter  = '(|' . join($filters) . ')';
      // Search objects
      try {
        $objects = Objects::ls($objectType, ['dn' => 'raw'], NULL, $filter);
      } catch (NonExistingObjectTypeException $e) {
        continue;
      } catch (EmptyFilterException $e) {
        continue;
      }
      // For each object of this type
      foreach (array_keys($objects) as $dn) {
        // Build the object
        $tabobject = Objects::open($dn, $objectType);
        if (preg_match('/^handle_/', $mode)) {
          // For each tab concerned
          foreach ($tabRefs['refs'] as $tab => $fieldRefs) {
            // If the tab is activated on this object
            $pluginobject = $tabobject->getTabOrServiceObject($tab);
            if ($pluginobject !== FALSE) {
              // For each field
              foreach ($fieldRefs as $ofield => $fields) {
                foreach ($fields as $field) {
                  // call plugin::foreignKeyUpdate(ldapname, oldvalue, newvalue, source) on the object
                  $pluginobject->foreignKeyUpdate(
                    $ofield,
                    $field['oldvalue'],
                    $field['newvalue'],
                    [
                      'CLASS' => $field['tab'],
                      'FIELD' => $field['field'],
                      'MODE'  => preg_replace('/^handle_/', '', $mode),
                      'DN'    => $this->dn,
                    ]
                  );
                }
              }
              $pluginobject->update();
            }
          }
          $errors = $tabobject->save();
          MsgDialog::displayChecks($errors);
        } elseif ($mode == 'references') {
          // For each tab concerned
          foreach ($tabRefs['refs'] as $tab => $tab_infos) {
            // If the tab is activated on this object
            $pluginobject = $tabobject->getTabOrServiceObject($tab);
            if ($pluginobject !== FALSE) {
              // For each field
              foreach ($tab_infos['fields'] as $ofield => $fields) {
                foreach ($fields as $field) {
                  if ($pluginobject->foreignKeyCheck(
                    $ofield,
                    $field['value'],
                    [
                      'CLASS' => $field['tab'],
                      'FIELD' => $field['field'],
                      'DN'    => $this->dn,
                    ]
                  )) {
                    if (!isset($refs[$dn])) {
                      $refs[$dn] = [
                        'link' => '',
                        'tabs' => [],
                      ];
                      try {
                        $refs[$dn]['link'] = Objects::link($dn, $objectType);
                      } catch (FusionDirectoryException $e) {
                        trigger_error("Could not create link to $dn: " . $e->getMessage());
                        $refs[$dn]['link'] = $dn;
                      }
                    }
                    if (!isset($refs[$dn]['tabs'][$tab])) {
                      $refs[$dn]['tabs'][$tab] = [
                        'link'   => '',
                        'fields' => [],
                      ];
                      try {
                        if (is_subclass_of($tab, 'SimpleService')) {
                          $refs[$dn]['tabs'][$tab]['link'] = Objects::link($dn, $objectType, "service_$tab", sprintf(_('Service "%s"'), $tab_infos['name']));
                        } else {
                          $refs[$dn]['tabs'][$tab]['link'] = Objects::link($dn, $objectType, "tab_$tab", sprintf(_('Tab "%s"'), $tab_infos['name']));
                        }
                      } catch (FusionDirectoryException $e) {
                        trigger_error("Could not create link to $dn $tab: " . $e->getMessage());
                        $refs[$dn]['tabs'][$tab]['link'] = $tab;
                      }
                    }
                    $refs[$dn]['tabs'][$tab]['fields'][$ofield] = $field;
                  }
                }
              }
            }
          }
        }
      }
    }
    /* Restore POST */
    $_POST = $SAVED_POST;
    if ($mode == 'references') {
      return $refs;
    }
  }

  /*!
   * \brief Create unique DN
   *
   * \param string $attribute
   *
   * \param string $base
   */
  function createUniqueDn (string $attribute, string $base): string
  {
    $ldap = config()->getLdapLink();
    $base = preg_replace('/^,*/', '', $base);

    /* Try to use plain entry first */
    $dn = $attribute . '=' . ldap_escape_dn($this->$attribute) . ',' . $base;
    if (($dn == $this->orig_dn) || !$ldap->dnExists($dn)) {
      return $dn;
    }

    /* Build DN with multiple attributes */
    $usableAttributes = [];
    foreach ($this->attributes as $attr) {
      if (($attr != $attribute) && is_scalar($this->$attr) && ($this->$attr != '')) {
        $usableAttributes[] = (string)$attr;
      }
    }
    for ($i = 1; $i < count($usableAttributes); $i++) {
      foreach (new Combinations($usableAttributes, $i) as $attrs) {
        $dn = $attribute . '=' . ldap_escape_dn($this->$attribute);
        foreach ($attrs as $attr) {
          $dn .= '+' . $attr . '=' . ldap_escape_dn($this->$attr);
        }
        $dn .= ',' . $base;
        if (($dn == $this->orig_dn) || !$ldap->dnExists($dn)) {
          return $dn;
        }
      }
    }

    /* None found */
    throw new FusionDirectoryException(_('Failed to create a unique DN'));
  }

  /*!
   * \brief Adapt from template
   *
   * Adapts fields to the values from a template.
   * Should not empty any fields, only take values for the ones provided by the caller.
   *
   * \param array $attrs LDAP attributes values for template-modified attributes
   * \param array $skip attributes to leave untouched
   */
  function adaptFromTemplate (array $attrs, array $skip = [])
  {
    $this->attrs = array_merge($this->attrs, $attrs);

    /* Walk through attributes */
    foreach ($this->attributesAccess as $ldapName => &$attr) {
      /* Skip the ones in skip list */
      if (in_array($ldapName, $skip)) {
        continue;
      }
      /* Load values */
      $attr->loadValue($attrs);
    }
    unset($attr);

    /* Is Account? */
    $this->is_account = $this->isThisAccount($this->attrs);
  }

  /*!
   * \brief This function is called on the copied object to set its dn to where it will be saved
   */
  function resetCopyInfos ()
  {
    $this->dn      = 'new';
    $this->orig_dn = $this->dn;

    $this->saved_attributes      = [];
    $this->initially_was_account = FALSE;
  }

  protected function attributeHaveChanged (string $field): bool
  {
    return $this->attributesAccess[$field]->hasChanged();
  }

  protected function attributeValue (string $field)
  {
    return $this->attributesAccess[$field]->getValue();
  }

  protected function attributeInitialValue (string $field)
  {
    return $this->attributesAccess[$field]->getInitialValue();
  }

  function foreignKeyUpdate (string $field, $oldvalue, $newvalue, array $source)
  {
    if (!isset($source['MODE'])) {
      $source['MODE'] = 'move';
    }

    // In case of SetAttribute, value is an array needing to be changed to string.
    if (is_array($oldvalue) && isset($oldvalue[0])) {

      $oldvalue = $oldvalue[0];
    }
    if (is_array($newvalue) && isset($newvalue[0])) {

      $newvalue = $newvalue[0];
    }

    $this->attributesAccess[$field]->foreignKeyUpdate($oldvalue, $newvalue, $source);
  }

  /*
   * Source is an array like this:
   * array(
   *  'CLASS' => class,
   *  'FIELD' => field,
   *  'DN'    => dn,
   *  'MODE'  => mode
   * )
   * mode being either 'copy' or 'move', defaults to 'move'
   */
  function foreignKeyCheck (string $field, $value, array $source)
  {
    // In case of SetAttribute, value is an array needing to be changed to string.
    if (is_array($value) && isset($value[0])) {

      $value = $value[0];
    }
    return $this->attributesAccess[$field]->foreignKeyCheck($value, $source);
  }

  function deserializeValues (array $values, bool $checkAcl = TRUE)
  {
    foreach ($values as $name => $value) {
      if (isset($this->attributesAccess[$name])) {
        if (!$checkAcl || $this->attrIsWriteable($name)) {
          $error = $this->attributesAccess[$name]->deserializeValue($value);
          if (!empty($error)) {
            return $error;
          }
        } else {
          return new SimplePluginPermissionError($this, MsgPool::permModify($this->dn, $name));
        }
      } else {
        return new SimplePluginError(
          $this,
          htmlescape(sprintf(_('Unknown field "%s"'), $name))
        );
      }
    }
    return TRUE;
  }

  /*! \brief Returns TRUE if this attribute should be asked in the creation by template dialog
   *
   * \return bool whether this attribute should be asked
   */
  function showInTemplate (string $attr, array $templateAttrs): bool
  {
    if (isset($templateAttrs[$attr])) {
      return FALSE;
    }
    return TRUE;
  }

  function isModalDialog (): bool
  {
    return (isset($this->dialog) && $this->dialog);
  }

  static function fillAccountAttrsNeeded (&$needed)
  {
    $infos = Pluglist::pluginInfos(get_called_class());
    if (isset($infos['plFilterObject'])) {
      $attrs = $infos['plFilterObject']->listUsedAttributes();
      foreach ($attrs as $attr) {
        if (!isset($needed[$attr])) {
          $needed[$attr] = '*';
        }
      }
    }
  }

  static function isAccount ($attrs)
  {
    $infos = Pluglist::pluginInfos(get_called_class());
    if (isset($infos['plFilterObject'])) {
      return $infos['plFilterObject']($attrs);
    }
    return NULL;
  }

  static function getLdapFilter ()
  {
    $infos = Pluglist::pluginInfos(get_called_class());
    if (isset($infos['plFilter'])) {
      return $infos['plFilter'];
    }
    return NULL;
  }

  static function getLdapFilterObject ()
  {
    $infos = Pluglist::pluginInfos(get_called_class());
    if (isset($infos['plFilterObject'])) {
      return $infos['plFilterObject'];
    }
    return NULL;
  }

  /*!
   * \brief Return plugin informations for acl handling
   *
   * \return an array
   */
  static function plInfo (): array
  {
    return [];
  }

  /*! \brief This function generate the needed ACLs for a given attribtues array
   *
   *  \param array $attributesInfo the attribute array
   *
   *  \param bool? $operationalAttributes Whether to add ACLs for operational attributes. Use NULL for autodetection (default)
   */
  static function generatePlProvidedAcls (array $attributesInfo, ?bool $operationalAttributes = NULL): array
  {
    $plProvidedAcls = [];
    foreach ($attributesInfo as $sectionInfo) {
      foreach ($sectionInfo['attrs'] as $attr) {
        if (($attr->getLdapName() === 'base') && ($operationalAttributes === NULL)) {
          /* If we handle base, we also handle LDAP operational attributes */
          $operationalAttributes = TRUE;
        }
        $aclInfo = $attr->getAclInfo();
        if ($aclInfo !== FALSE) {
          $plProvidedAcls[$aclInfo['name']] = $aclInfo['desc'];
        }
      }
    }
    if ($operationalAttributes) {
      $plProvidedAcls['createTimestamp'] = _('The time the entry was added');
      $plProvidedAcls['modifyTimestamp'] = _('The time the entry was last modified');
    }

    return $plProvidedAcls;
  }

  /*! \brief This function is the needed main.inc for plugins that are not used inside a management class
   *
   *  \param array $classname the class name to read plInfo from. (plIcon, plTitle, plShortname and plObjectType may be used)
   *
   *  \param string $entry_dn the dn of the object to show/edit
   *
   *  \param boolean $tabs TRUE to use tabs, FALSE to show directly the plugin class
   *
   *  \param boolean $edit_mode wether or not this plugin can be edited
   *
   *  \param string $objectType The objectType to use (will be taken in the plInfo if FALSE)
   *
   */
  static function mainInc ($classname = NULL, $entry_dn = NULL, $tabs = FALSE, $edit_mode = TRUE, $objectType = FALSE)
  {
    $remove_lock = &remove_lock();
    $cleanup = &cleanup();
    $display = &display();

    if ($classname === NULL) {
      $classname = get_called_class();
    }

    if ($entry_dn === NULL) {
      $entry_dn = user_info()->dn;
    }

    $plInfo     = Pluglist::pluginInfos($classname);
    $plIcon     = (isset($plInfo['plIcon']) ? $plInfo['plIcon'] : 'plugin.png');
    $plHeadline = $plInfo['plTitle'];
    if ($objectType === FALSE) {
      $key = key($plInfo['plObjectType']);
      if (is_numeric($key)) {
        $key = $plInfo['plObjectType'][$key];
      }
      $objectType = $key;
    }

    $lock_msg = "";
    if ($edit_mode
      && ($remove_lock || (isset($_POST['edit_cancel']) && Session::is_set('edit')))
      && Session::is_set($classname)) {
      /* Remove locks created by this plugin */
      Lock::deleteByObject($entry_dn);
    }

    /* Remove this plugin from session */
    if ($cleanup) {
      Session::un_set($classname);
      Session::un_set('edit');
    } else {
      /* Reset requested? */
      if ($edit_mode && isset($_POST['edit_cancel'])) {
        Session::un_set($classname);
        Session::un_set('edit');
      }

      /* Create tab object on demand */
      if (!Session::is_set($classname) || (isset($_GET['reset']) && $_GET['reset'] == 1)) {
        try {
          $tabObject = Objects::open($entry_dn, $objectType);
        } catch (NonExistingLdapNodeException $e) {
          $tabObject = Objects::open('new', $objectType);
        }
        if ($edit_mode) {
          $tabObject->setNeedEditMode(TRUE);
        }
        if (!$tabs) {
          $tabObject->current = $classname;
        }
        Session::set($classname, $tabObject);
      }
      $tabObject = Session::get($classname);

      if (!$edit_mode || Session::is_set('edit')) {
        /* Save changes back to object */
        $tabObject->readPost();
        $tabObject->update();
      } else {
        /* Allow changing tab before going into edit mode */
        $tabObject->readPostTabChange();
      }

      if ($edit_mode) {
        /* Enter edit mode? */
        if ((isset($_POST['edit'])) && (!Session::is_set('edit'))) {
          /* Check locking */
          if ($locks = Lock::get($entry_dn)) {
            Session::set('LOCK_VARS_TO_USE', ['/^edit$/', '/^plug$/']);
            $lock_msg = Lock::genLockedMessage($locks);
          } else {
            /* Lock the current entry */
            Lock::add($entry_dn);
            Session::set('edit', TRUE);
          }
        }

        /* save changes to LDAP and disable edit mode */
        if (isset($_POST['edit_finish'])) {
          /* Perform checks */
          $errors = $tabObject->save();

          /* No errors, save object */
          if (count($errors) == 0) {
            Lock::deleteByObject($entry_dn);
            Session::un_set('edit');

            /* Remove from session */
            Session::un_set($classname);
          } else {
            /* Errors found, show errors */
            MsgDialog::displayChecks($errors);
          }
        }
      }

      /* Execute formular */
      if ($edit_mode && $lock_msg) {
        $display = $lock_msg;
      } else {
        if ($tabs) {
          $display .= $tabObject->render();
        } else {
          $display .= $tabObject->by_object[$classname]->render();
        }
      }

      /* Store changes  in session */
      if (!$edit_mode || Session::is_set('edit')) {
        Session::set($classname, $tabObject);
      }

      /* Show page footer depending on the mode */
      $info = $entry_dn . '&nbsp;';
      if ($edit_mode && (!$tabObject->dialogOpened()) && empty($lock_msg)) {
        /* Are we in edit mode? */
        if (Session::is_set('edit')) {
          $display .= '<p class="plugbottom">' . "\n";
          $display .= '<input type="submit" name="edit_finish" style="width:80px" value="' . MsgPool::okButton() . '"/>' . "\n";
          $display .= '&nbsp;';
          $display .= '<input type="submit" formnovalidate="formnovalidate" name="edit_cancel" value="' . MsgPool::cancelButton() . '"/>' . "\n";
          $display .= "</p>\n";
        } elseif (strpos($tabObject->by_object[$tabObject->current]->aclGetPermissions(''), 'w') !== FALSE) {
          /* Only display edit button if there is at least one attribute writable */
          $display .= '<p class="plugbottom">' . "\n";
          $info    .= '<div style="float:left;" class="optional"><img class="center" alt="information" ' .
            'src="geticon.php?context=status&amp;icon=dialog-information&amp;size=16"> ' .
            MsgPool::clickEditToChange() . '</div>';
          $display .= '<input type="submit" name="edit" value="' . MsgPool::editButton() . '"/>' . "\n";
          $display .= "</p>\n";
        }
      }

      /* Page header */
      if (!preg_match('/^geticon/', $plIcon)) {
        $plIcon = get_template_path($plIcon);
      }
      smarty()->assign('headline', $plHeadline);
      smarty()->assign('headline_image', $plIcon);
      $display = '<div class="pluginfo">' . $info . "</div>\n" . $display;
    }
  }
}
