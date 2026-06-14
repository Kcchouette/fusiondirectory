<?php
declare(strict_types=1);
/*
  This code is part of FusionDirectory (http://www.fusiondirectory.org/)
  Copyright (C) 2017-2020  FusionDirectory

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
 * \brief Management base class
 */

class Management implements FusionDirectoryDialog
{
  /* Object types we are currently managing */
  public array $objectTypes = [];

  /* managementListing instance which manages the entries */
  public ?ManagementListing $listing = null;

  /* managementFilter instance which manages the filters */
  public ?ManagementFilter $filter = null;

  /* Copy&Paste */
  public ?CopyPasteHandler $cpHandler = null;
  public bool $cpPastingStarted = false;
  protected bool $skipCpHandler = false;

  /* Snapshots */
  public ?object $snapHandler = null;
  public static bool $skipSnapshots = FALSE;

  // The currently used object(s) (e.g. in edit, removal)
  public string $currentDn = '';
  public array $currentDns = [];

  // The last used object(s).
  protected string $previousDn = '';
  protected array $previousDns = [];

  // The opened object.
  /**
   * @var ?simpleTabs
   */
  public ?object $tabObject = null;
  public ?object $dialogObject = null;

  // The last opened object.
  public ?object $last_tabObject = null;
  protected ?object $last_dialogObject = null;

  protected mixed $renderCache = null;

  public string $headline = '';
  public string $title = '';
  public string $icon = '';

  public array $actions = [];
  public array $actionHandlers = [];

  public array $neededAttrs = [];

  public static bool $skipTemplates = TRUE;

  /* Disable and hide configuration system */
  public bool $skipConfiguration = false;

  public mixed $columnConfiguration = null;

   /* Default columns */
   public static array $columns = [
    ['ObjectTypeColumn', []],
    ['LinkColumn', ['attributes' => 'nameAttr', 'label' => 'Name']],
    ['LinkColumn', ['attributes' => 'description', 'label' => 'Description']],
    ['ActionsColumn', ['label' => 'Actions']],
   ];

   /** @var ManagementListingComponent Listing display and filtering */
    public ManagementListingComponent $listingComponent;

   /** @var ManagementActionsComponent CRUD actions */
    public ManagementActionsComponent $actionsComponent;

   /** @var ManagementSnapshotComponent Snapshot operations */
    public ManagementSnapshotComponent $snapshotComponent;

   function __construct (
    $objectTypes = FALSE,
    array $filterElementDefinitions = [
      ['TabFilterElement', []],
    ]
  )
  {
    /* Initialize facade components */
    $this->listingComponent  = new ManagementListingComponent($this);
    $this->actionsComponent  = new ManagementActionsComponent($this);
    $this->snapshotComponent = new ManagementSnapshotComponent($this);

    if ($objectTypes === FALSE) {
      $plInfos     = Pluglist::pluginInfos(get_class($this));
      $objectTypes = $plInfos['plManages'];
    }

    if (isset($this->icon)) {
      if (!preg_match('/^geticon/', $this->icon)) {
        $this->icon = get_template_path($this->icon);
      }
    }

    /* Ignore non existing objectTypes. This happens when an optional plugin is missing. */
    foreach ($objectTypes as $key => $type) {
      try {
        Objects::infos($type);
        $objectTypes[$key] = strtoupper($type);
      } catch (NonExistingObjectTypeException $e) {
        unset($objectTypes[$key]);
      }
    }

    $this->objectTypes = array_values($objectTypes);

    $this->setUpHeadline();
    $this->setUpListing();
    $this->setUpFilter($filterElementDefinitions);

    // Add copy&paste and snapshot handler.
    if (!$this->skipCpHandler) {
      $this->cpHandler = new CopyPasteHandler();
    }
    if (!static::$skipSnapshots && (config()->getCfgValue('enableSnapshots') == 'TRUE')) {
      $this->snapHandler = new SnapshotHandler();
    }

    $this->configureActions();
  }

  protected function setUpListing ()
  {
    $this->listingComponent->setUpListing();
  }

  protected function setUpFilter (array $filterElementDefinitions)
  {
    $this->listingComponent->setUpFilter($filterElementDefinitions);
  }

  protected function setUpHeadline ()
  {
    $plInfos = Pluglist::pluginInfos(get_class($this));

    $this->headline = $plInfos['plShortName'];
    $this->title    = $plInfos['plTitle'];
    $this->icon     = $plInfos['plIcon'];
  }

  protected function configureActions ()
  {
    $positionDN = &position_dn();

    // Register default actions
    $createMenu = [];

    if (!static::$skipTemplates) {
      $templateMenu     = [];
      $fromTemplateMenu = [];
    }

    foreach ($this->objectTypes as $type) {
      $infos = Objects::infos($type);
      $img   = 'geticon.php?context=actions&icon=document-new&size=16';
      if (isset($infos['icon'])) {
        $img = $infos['icon'];
      }

      if (!isset($positionDN)) {
        $positionDN = user_info()->dn;
      }

      if (!preg_match('/t/', user_info()->getPermissions($positionDN, $infos['aclCategory'] . '/' . $infos['mainTab']))) {
        $createMenu[] = new Action(
        'new_' . $type, $infos['name'], $img,
        '0', 'newEntry',
        [$infos['aclCategory'] . '/' . $infos['mainTab'] . '/c']
        );
      }
      if (!static::$skipTemplates) {
        $templateMenu[]     = new Action(
        'new_template_' . $type, $infos['name'], $img,
        '0', 'newEntryTemplate',
        [$infos['aclCategory'] . '/template/c']
        );
        $fromTemplateMenu[] = new Action(
          'template_apply_' . $type, $infos['name'], $img,
          '0', 'newEntryFromTemplate',
          [$infos['aclCategory'] . '/template/r', $infos['aclCategory'] . '/' . $infos['mainTab'] . '/c']
        );
      }
    }

    if (!static::$skipTemplates) {
      $createMenu =
        array_merge(
          [
            new SubMenuAction(
              'Template', _('Template'), 'geticon.php?context=devices&icon=template&size=16',
              $templateMenu
            ),
            new SubMenuAction(
              'fromtemplate', _('From template'), 'geticon.php?context=actions&icon=document-new&size=16',
              $fromTemplateMenu
            ),
          ],
          $createMenu
        );
    }

    $this->registerAction(
      new SubMenuAction(
        'new', _('Create'), 'geticon.php?context=actions&icon=document-new&size=16',
        $createMenu
      )
    );

    $this->registerAction(
      new Action(
        'edit', _('Edit'), 'geticon.php?context=actions&icon=document-edit&size=16',
        '+', 'editEntry'
      )
    );
    $this->actions['edit']->setSeparator(TRUE);

    if (!$this->skipCpHandler) {
      $this->registerAction(
        new Action(
          'cut', _('Cut'), 'geticon.php?context=actions&icon=edit-cut&size=16',
          '+', 'copyPasteHandler',
          ['dr']
        )
      );
      $this->registerAction(
        new Action(
          'copy', _('Copy'), 'geticon.php?context=actions&icon=edit-copy&size=16',
          '+', 'copyPasteHandler',
          ['r']
        )
      );
      $this->registerAction(
        new Action(
          'paste', _('Paste'), 'geticon.php?context=actions&icon=edit-paste&size=16',
          '0', 'copyPasteHandler',
          ['w']
        )
      );
      $this->actions['paste']->setEnableFunction([$this->actionsComponent, 'enablePaste']);
    }

    if (!static::$skipTemplates) {
      $this->registerAction(
        new Action(
          'template_apply_to', _('Apply template'), 'geticon.php?context=actions&icon=tools-wizard&size=16',
          '+', 'applyTemplateToEntry',
          ['/template/r', 'c'],
          TRUE,
          FALSE
        )
      );
    }

    if (class_available('archivedObject')) {
      $action = archivedObject::getManagementAction($this->objectTypes, 'archiveRequested');
      if ($action !== NULL) {
        $this->registerAction($action);
        $this->registerAction(new HiddenAction('archiveConfirmed', 'archiveConfirmed'));
        $this->registerAction(new HiddenAction('archiveCancel', 'cancelEdit'));
      }
    }

    $this->registerAction(
      new Action(
        'remove', _('Remove'), 'geticon.php?context=actions&icon=edit-delete&size=16',
        '+', 'removeRequested',
        ['d']
      )
    );

    if (!static::$skipSnapshots && (config()->getCfgValue('enableSnapshots') == 'TRUE')) {
      $this->registerAction(
        new Action(
          'snapshot', _('Create snapshot'), 'geticon.php?context=actions&icon=snapshot&size=16',
          '1', 'createSnapshotDialog',
          ['/SnapshotHandler/c']
        )
      );
      $this->registerAction(
        new Action(
          'restore', _('Restore snapshot'), 'geticon.php?context=actions&icon=document-restore&size=16',
          '*', 'restoreSnapshotDialog',
          ['w', '/SnapshotHandler/r']
        )
      );
      $this->actions['snapshot']->setSeparator(TRUE);
      $this->actions['restore']->setEnableFunction([$this->snapshotComponent, 'enableSnapshotRestore']);
    }

    if (!static::$skipTemplates) {
      $this->registerAction(
        new Action(
          'template_apply', _('Create an object from this template'), 'geticon.php?context=actions&icon=document-new&size=16',
          '1', 'newEntryFromTemplate',
          ['/template/r', 'c'],
          FALSE,
          TRUE,
          ['Template']
        )
      );
    }

    /* Actions from footer are not in any menus and do not need a label */
    $this->registerAction(new HiddenAction('apply', 'applyChanges'));
    $this->registerAction(new HiddenAction('save', 'saveChanges'));
    $this->registerAction(new HiddenAction('cancel', 'cancelEdit'));
    $this->registerAction(new HiddenAction('cancelDelete', 'cancelEdit'));
    $this->registerAction(new HiddenAction('removeConfirmed', 'removeConfirmed'));
    if (!$this->skipConfiguration) {
      $this->registerAction(new HiddenAction('configure', 'configureDialog'));
    }
  }

  /*!
   *  \brief Register an action to show in the action menu and/or the action column
   */
  function registerAction (Action $action)
  {
    $action->setParent($this);
    $this->actions[$action->getName()] = $action;
    foreach ($action->listActions() as $actionName) {
      $this->actionHandlers[$actionName] = $action;
    }
  }

  public function getColumnConfiguration (): array
  {
    return $this->listingComponent->getColumnConfiguration();
  }

  public function setColumnConfiguration ($columns)
  {
    $this->listingComponent->setColumnConfiguration($columns);
  }

  function detectPostActions (): array
  {
    return $this->actionsComponent->detectPostActions();
  }

  function handleAction (array $action)
  {
    return $this->actionsComponent->handleAction($action);
  }

  protected function handleSubAction (array $action): bool
  {
    return $this->actionsComponent->handleSubAction($action);
  }

  /* For management we have to render directly in readPost in some cases */
  public function readPost ()
  {
    $this->renderCache = $this->execute();
  }

  public function update (): bool
  {
    if ($this->renderCache === NULL) {
      if (!$this->dialogOpened()) {
        // Update list
        $this->listing->update();

        // Special from jonathan to set the positionDN
        $positionDN = &position_dn();
        $positionDN = $this->listing->getBase();
        $this->configureActions();

        // Init snapshot list for renderSnapshotActions
        if (is_object($this->snapHandler)) {
          $this->snapHandler->initSnapshotCache($this->listing->getBase());
        }
      }
    }
    return TRUE;
  }

  public function render (): string
  {
    if ($this->renderCache === NULL) {
      if ($this->tabObject instanceof SimpleTabs) {
        /* Display tab object */
        $display           = $this->tabObject->render();
        $display           .= $this->getTabFooter();
        $this->renderCache = $this->getHeader() . $display;
      } elseif (is_object($this->dialogObject)) {
        /* Display dialog object */
        $display           = $this->dialogObject->render();
        $display           .= $this->getTabFooter();
        $this->renderCache = $this->getHeader() . $display;
      } else {
        /* Display list */
        $this->renderCache = $this->renderList();
      }
    }
    return $this->renderCache;
  }

  /*!
   * \brief  Execute this plugin
   *          Handle actions/events, locking, snapshots, dialogs, tabs,...
   */
  protected function execute ()
  {
    // Ensure that html posts and gets are kept even if we see a 'Entry islocked' dialog.
    Session::set('LOCK_VARS_TO_USE', ['/^act$/', '/^listing/', '/^PID$/']);

    /* Display the copy & paste dialog, if it is currently open */
    $ret = $this->copyPasteHandler();
    if ($ret) {
      return $this->getHeader() . $ret;
    }

    // Handle actions (POSTs and GETs)
    $action = $this->detectPostActions();
    if (!empty($action['action'])) {
      Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $action, 'Action');
      try {
        $str = $this->handleAction($action);
        if (!empty($str)) {
          return $this->getHeader() . $str;
        }
      } catch (FusionDirectoryException $e) {
        $error = new FusionDirectoryError(htmlescape($e->getMessage()), 0, $e);
        $error->display();
      }
    }

    /* Save tab or dialog object */
    if ($this->tabObject instanceof SimpleTabs) {
      $this->tabObject->readPost();
      $this->tabObject->update();
    } elseif (is_object($this->dialogObject)) {
      try {
        $this->dialogObject->readPost();
        if (is_object($this->dialogObject)) {
          /* Check again as readPost might close it */
          if (!$this->dialogObject->update()) {
            $this->closeDialogs();
          }
        }
      } catch (FusionDirectoryException $e) {
        $error = new FusionDirectoryError(htmlescape($e->getMessage()), 0, $e);
        $error->display();
        $this->closeDialogs();
      }
    }

    return NULL;
  }

  function renderList (): string
  {
    return $this->listingComponent->renderList();
  }

  protected function renderFilter (): string
  {
    return $this->listingComponent->renderFilter();
  }

  protected function renderActionMenu (): string
  {
    return $this->listingComponent->renderActionMenu();
  }

  function renderActionColumn (ListingEntry $entry): string
  {
    return $this->listingComponent->renderActionColumn($entry);
  }

  function fillActionRowClasses (&$classes, ListingEntry $entry)
  {
    $this->listingComponent->fillActionRowClasses($classes, $entry);
  }

  /*!
   * \brief  Removes ldap object locks created by this class.
   *         Whenever an object is edited, we create locks to avoid
   *         concurrent modifications.
   *         This locks will automatically removed here.
   */
  public function removeLocks ()
  {
    if (!empty($this->currentDn) && ($this->currentDn != 'new')) {
      Lock::deleteByObject($this->currentDn);
    }
    if (count($this->currentDns)) {
      Lock::deleteByObject($this->currentDns);
    }
  }

  function dialogOpened (): bool
  {
    return (is_object($this->tabObject) || is_object($this->dialogObject));
  }

  /*!
   * \brief Sets smarty headline and returns the plugin header which is displayed whenever a tab object is opened.
   */
  protected function getHeader (): string
  {
    smarty()->assign('headline', $this->title);
    smarty()->assign('headline_image', $this->icon);

    if (is_object($this->tabObject) && ($this->currentDn != '')) {
      return '<div class="pluginfo">' . $this->currentDn . "</div>\n";
    }
    return '';
  }

  function openTabObject ($object)
  {
    $this->tabObject         = $object;
    $this->tabObject->parent = &$this;
  }

  /*!
   * \brief  This method closes dialogs
   *          and cleans up the cached object info and the ui.
   */
  public function closeDialogs ()
  {
    $this->previousDn  = $this->currentDn;
    $this->currentDn   = '';
    $this->previousDns = $this->currentDns;
    $this->currentDns  = [];

    $this->last_tabObject    = $this->tabObject;
    $this->tabObject         = NULL;
    $this->last_dialogObject = $this->dialogObject;
    $this->dialogObject      = NULL;
  }

  protected function listAclCategories (): array
  {
    $cat = [];
    foreach ($this->objectTypes as $type) {
      $infos = Objects::infos($type);
      $cat[] = $infos['aclCategory'];
    }
    return array_unique($cat);
  }

  /*!
   * \brief Whether footer buttons should appear
   */
  protected function showTabFooter (): bool
  {
    // Do not display tab footer for non tab objects
    if (!($this->tabObject instanceof SimpleTabs)) {
      return FALSE;
    }

    // Check if there is a dialog opened - We don't need any buttons in this case.
    if ($this->tabObject->dialogOpened()) {
      return FALSE;
    }

    return TRUE;
  }

  /*!
   * \brief  Generates the footer which is used whenever a tab object is displayed.
   */
  protected function getTabFooter (): string
  {
    // Do not display tab footer for non tab objects
    if (!$this->showTabFooter()) {
      return '';
    }

    $smarty = get_smarty();
    $smarty->assign('readOnly', $this->tabObject->readOnly());
    $smarty->assign('showApply', ($this->currentDn != 'new'));
    return $smarty->fetch(get_template_path('management/tabfooter.tpl'));
  }

  function handleTemplateApply ($cancel = FALSE)
  {
    return $this->actionsComponent->handleTemplateApply($cancel);
  }

  function enablePaste ($action, ?ListingEntry $entry = NULL): bool
  {
    return $this->actionsComponent->enablePaste($action, $entry);
  }

  /* Action handlers */

  function newEntry (array $action)
  {
    $this->actionsComponent->newEntry($action);
  }

  function newEntryTemplate (array $action)
  {
    $this->actionsComponent->newEntryTemplate($action);
  }

  function newEntryFromTemplate (array $action)
  {
    $this->actionsComponent->newEntryFromTemplate($action);
  }

  function applyTemplateToEntry (array $action)
  {
    $this->actionsComponent->applyTemplateToEntry($action);
  }

  public function archiveRequested (array $action)
  {
    $this->actionsComponent->archiveRequested($action);
  }

  public function archiveConfirmed (array $action)
  {
    $this->actionsComponent->archiveConfirmed($action);
  }

  function editEntry (array $action)
  {
    $this->actionsComponent->editEntry($action);
  }

  function cancelEdit ()
  {
    $this->actionsComponent->cancelEdit();
  }

  function saveChanges ()
  {
    $this->actionsComponent->saveChanges();
  }

  function applyChanges ()
  {
    $this->actionsComponent->applyChanges();
  }

  function removeRequested (array $action)
  {
    $this->actionsComponent->removeRequested($action);
  }

  protected function removeConfirmationDialog (array $objects)
  {
    $this->actionsComponent->removeConfirmationDialog($objects);
  }

  function removeConfirmed (array $action)
  {
    $this->actionsComponent->removeConfirmed($action);
  }

  function configureDialog (array $action)
  {
    if (!$this->skipConfiguration) {
      $this->dialogObject = new ManagementConfigurationDialog($this);
    }
  }

  function copyPasteHandler (array $action = ['action' => ''])
  {
    return $this->actionsComponent->copyPasteHandler($action);
  }

  /* End of action handlers */

  /* Methods related to Snapshots */

  function createSnapshotDialog (array $action)
  {
    $this->snapshotComponent->createSnapshotDialog($action);
  }

  function restoreSnapshotDialog (array $action)
  {
    $this->snapshotComponent->restoreSnapshotDialog($action);
  }

  function getSnapshotBases (): array
  {
    return $this->snapshotComponent->getSnapshotBases();
  }

  function getAllDeletedSnapshots (): array
  {
    return $this->snapshotComponent->getAllDeletedSnapshots();
  }

  function getAvailableSnapsShots (string $dn): array
  {
    return $this->snapshotComponent->getAvailableSnapsShots($dn);
  }

  function enableSnapshotRestore ($action, ?ListingEntry $entry = NULL): bool
  {
    return $this->snapshotComponent->enableSnapshotRestore($action, $entry);
  }

  function createSnapshot (string $dn, string $description, string $snapshotSource = 'FD')
  {
    $this->snapshotComponent->createSnapshot($dn, $description, $snapshotSource);
  }

  function restoreSnapshot (string $dn)
  {
    $this->snapshotComponent->restoreSnapshot($dn);
  }

  function removeSnapshot (string $dn)
  {
    $this->snapshotComponent->removeSnapshot($dn);
  }

  static function mainInc ($classname = NULL, $objectTypes = FALSE)
  {
    $remove_lock = &remove_lock();
    $cleanup = &cleanup();
    $display = &display();

    if ($classname === NULL) {
      $classname = get_called_class();
    }

    /* Remove locks */
    if ($remove_lock && Session::isSet($classname)) {
      $macl = Session::get($classname);
      $macl->removeLocks();
    }

    if ($cleanup) {
      /* Clean up */
      Session::unsetKey($classname);
    } else {
      if (!Session::isSet($classname) || (isset($_GET['reset']) && $_GET['reset'] == 1)) {
        /* Create the object if missing or reset requested */
        $managementObject = new $classname($objectTypes);
      } else {
        /* Retrieve the object from session */
        $managementObject = Session::get($classname);
      }
      /* Execute and display */
      $managementObject->readPost();
      $managementObject->update();
      $display = $managementObject->render();

      /* Store the object in the session */
      Session::set($classname, $managementObject);
    }
  }
}
