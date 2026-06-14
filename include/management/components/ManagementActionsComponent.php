<?php
declare(strict_types=1);

/**
 * Handles CRUD actions for a Management instance.
 */
class ManagementActionsComponent
{
    public function __construct(
        private Management $management
    ) {
    }

    public function detectPostActions (): array
    {
        if (!is_object($this->management->listing)) {
            throw new FusionDirectoryException('No valid listing object');
        }
        $action = ['targets' => [], 'action' => '', 'subaction' => NULL];
        if ($this->management->showTabFooter()) {
            if (isset($_POST['edit_cancel'])) {
                $action['action'] = 'cancel';
            } elseif (isset($_POST['edit_finish'])) {
                $action['action'] = 'save';
            } elseif (isset($_POST['edit_apply'])) {
                $action['action'] = 'apply';
            }
        } elseif (!$this->management->dialogOpened()) {
            if (isset($_POST['delete_confirmed'])) {
                $action['action'] = 'removeConfirmed';
            } elseif (isset($_POST['delete_cancel'])) {
                $action['action'] = 'cancelDelete';
            } elseif (isset($_POST['archive_confirmed'])) {
                $action['action'] = 'archiveConfirmed';
            } elseif (isset($_POST['archive_cancel'])) {
                $action['action'] = 'archiveCancel';
            } else {
                $action = $this->management->listing->getAction();
            }
        }

        return $action;
    }

    function handleAction (array $action)
    {
        if (isset($action['subaction']) && isset($this->management->actionHandlers[$action['action'] . '_' . $action['subaction']])) {
            return $this->management->actionHandlers[$action['action'] . '_' . $action['subaction']]->execute($this->management, $action);
        } elseif (isset($this->management->actionHandlers[$action['action']])) {
            return $this->management->actionHandlers[$action['action']]->execute($this->management, $action);
        }
    }

    protected function handleSubAction (array $action): bool
    {
        if (preg_match('/^tab_/', $action['subaction'])) {
            $tab = preg_replace('/^tab_/', '', $action['subaction']);
            if (isset($this->management->tabObject->by_object[$tab])) {
                $this->management->tabObject->current = $tab;
            } else {
                trigger_error('Unknown tab: ' . $tab);
            }
            return TRUE;
        }
        return FALSE;
    }

    function newEntry (array $action)
    {
        $type = $action['subaction'];

        $this->management->currentDn = 'new';

        $this->management->openTabObject(Objects::create($type));
        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->management->currentDn, 'Create entry initiated');
    }

    function newEntryTemplate (array $action)
    {
        if (Management::$skipTemplates) {
            return;
        }
        $type = preg_replace('/^template_/', '', $action['subaction']);

        $this->management->currentDn = 'new';

        $this->management->openTabObject(Objects::createTemplate($type));
        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->management->currentDn, 'Create template entry initiated');
    }

    function newEntryFromTemplate (array $action)
    {
        if (Management::$skipTemplates) {
            return;
        }
        if (isset($action['targets'][0])) {
            $dn = $action['targets'][0];
        } else {
            $dn = NULL;
        }
        if ($action['subaction'] == 'apply') {
            if ($dn === NULL) {
                return;
            }
            $type = $this->management->listing->getEntry($dn)->getTemplatedType();
        } else {
            $type = preg_replace('/^apply_/', '', $action['subaction']);
        }
        $this->management->dialogObject = new TemplateDialog($this->management, $type, $dn);
    }

    function applyTemplateToEntry (array $action)
    {
        if (Management::$skipTemplates) {
            return;
        }
        if (empty($action['targets'])) {
            return;
        }
        $this->management->currentDns = $action['targets'];

        if ($locks = Lock::get($this->management->currentDns)) {
            return Lock::genLockedMessage($locks, FALSE, _('Apply anyway'));
        }

        Lock::add($this->management->currentDns);

        $type = NULL;

        foreach ($this->management->currentDns as $dn) {
            $entry = $this->management->listing->getEntry($dn);
            if ($entry === NULL) {
                trigger_error('Could not find ' . $dn . ', action canceled');
                $this->management->currentDns = [];
                return;
            }

            if ($entry->isTemplate()) {
                $error = new FusionDirectoryError(htmlescape(_('Applying a template to a template is not possible')));
                $error->display();
                $this->management->currentDns = [];
                return;
            }

            if (!isset($type)) {
                $type = $entry->type;
            } elseif ($entry->type != $type) {
                $error = new FusionDirectoryError(htmlescape(_('All selected entries need to share the same type to be able to apply a template to them')));
                $error->display();
                $this->management->currentDns = [];
                return;
            }
        }

        $this->management->currentDn = array_shift($this->management->currentDns);

        $this->management->dialogObject = new TemplateDialog($this->management, $type, NULL, $this->management->currentDn);
    }

    function editEntry (array $action)
    {
        if (is_object($this->management->tabObject)) {
            return;
        }

        $target = array_pop($action['targets']);

        $entry = $this->management->listing->getEntry($target);
        if ($entry === NULL) {
            trigger_error('Could not find ' . $target . ', open canceled');
            return;
        }

        $this->management->currentDn = $target;
        if ($locks = Lock::get($this->management->currentDn, TRUE)) {
            return Lock::genLockedMessage($locks, TRUE);
        }
        Lock::add($this->management->currentDn);

        $this->management->openTabObject(Objects::open($this->management->currentDn, $entry->getTemplatedType()));
        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->management->currentDn, 'Edit entry initiated');
        if (isset($action['subaction'])
            && ($this->handleSubAction($action) === FALSE)) {
            trigger_error('Was not able to handle subaction: ' . $action['subaction']);
        }
    }

    function cancelEdit ()
    {
        if (($this->management->tabObject instanceof SimpleTabs) && ($this->management->dialogObject instanceof TemplateDialog)) {
            $this->handleTemplateApply(TRUE);
            return;
        }
        $this->management->removeLocks();
        $this->management->closeDialogs();
    }

    function saveChanges ()
    {
        if ($this->management->tabObject instanceof SimpleTabs) {
            $this->management->tabObject->readPost();
            $this->management->tabObject->update();
            if ($this->management->dialogObject instanceof TemplateDialog) {
                $this->handleTemplateApply();
            } else {
                $msgs = $this->management->tabObject->save();
                if (count($msgs)) {
                    MsgDialog::displayChecks($msgs);
                } else {
                    Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->management->currentDns, 'Entry saved');
                    $this->management->removeLocks();
                    $this->management->closeDialogs();
                }
            }
        }
    }

    function applyChanges ()
    {
        if ($this->management->tabObject instanceof SimpleTabs) {
            $this->management->tabObject->readPost();
            $this->management->tabObject->update();
            $msgs = $this->management->tabObject->save();
            if (count($msgs)) {
                MsgDialog::displayChecks($msgs);
            } else {
                Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->management->currentDns, 'Modifications applied');
                $this->management->tabObject->reInit();
                $_POST = [];
            }
        }
    }

    function removeRequested (array $action)
    {
        $disallowed       = [];
        $this->management->currentDns = [];

        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $action['targets'], 'Entry deletion requested');

        foreach ($action['targets'] as $dn) {
            $entry = $this->management->listing->getEntry($dn);
            try {
                if ($entry->checkAcl('d')) {
                    $this->management->currentDns[] = $dn;
                } else {
                    $disallowed[] = $dn;
                }
            } catch (NonExistingObjectTypeException $e) {
                trigger_error('Unknown object type received :' . $e->getMessage());
            }
        }
        if (count($disallowed)) {
            $error = new FusionDirectoryPermissionError(MsgPool::permDelete($disallowed));
            $error->display();
        }

        if (count($this->management->currentDns)) {
            if ($locks = Lock::get($this->management->currentDns)) {
                return Lock::genLockedMessage($locks, FALSE, _('Delete anyway'));
            }

            Lock::add($this->management->currentDns);

            $objects = [];
            foreach ($this->management->currentDns as $dn) {
                $entry = $this->management->listing->getEntry($dn);
                $infos = Objects::infos($entry->getTemplatedType());
                if ($entry->isTemplate()) {
                    $infos['nameAttr'] = 'cn';
                }
                $objects[] = [
                    'name' => $entry[$infos['nameAttr']][0],
                    'dn'   => $dn,
                    'icon' => $infos['icon'],
                    'type' => $infos['name']
                ];
            }

            return $this->removeConfirmationDialog($objects);
        }
    }

    protected function removeConfirmationDialog (array $objects)
    {
        $smarty = get_smarty();
        $smarty->assign('Objects', $objects);
        $smarty->assign('multiple', TRUE);
        return $smarty->fetch(get_template_path('simple-remove.tpl'));
    }

    function removeConfirmed (array $action)
    {
        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->management->currentDns, 'Entry deletion confirmed');

        $snapshotHandler = new SnapshotHandler();
        foreach ($this->management->currentDns as $dn) {
            $entry = $this->management->listing->getEntry($dn);
            if (empty($entry)) {
                continue;
            }
            if ($entry->checkAcl('d')) {
                $this->management->currentDn = $dn;
                $this->management->openTabObject(Objects::open($this->management->currentDn, $entry->getTemplatedType()));

                if (isset($this->management->tabObject->by_object['user'])) {
                    $this->management->tabObject->by_object['user']->read_only = FALSE;
                }

                $errors = $this->management->tabObject->delete();
                MsgDialog::displayChecks($errors);

                Lock::deleteByObject($this->management->currentDn);

                $dnSnapshotsList = $snapshotHandler->getSnapshots($this->management->currentDn, TRUE);
                foreach ($dnSnapshotsList as $snap) {
                    $snapshotHandler->removeSnapshot($snap['dn']);
                }
            } else {
                $error = new FusionDirectoryPermissionError(MsgPool::permDelete($dn));
                $error->display();
                Logging::log('security', 'management/' . get_class($this->management), $dn, [], 'Tried to trick deletion.');
            }
        }

        $this->management->removeLocks();
        $this->management->closeDialogs();
    }

    public function archiveRequested (array $action)
    {
        if (empty($action['targets'])) {
            return;
        }
        $this->management->currentDns = $action['targets'];

        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $action['targets'], 'Entry archive requested');

        if ($locks = Lock::get($this->management->currentDns)) {
            return Lock::genLockedMessage($locks, FALSE, _('Archive anyway'));
        }

        Lock::add($this->management->currentDns);

        $objects = [];
        foreach ($this->management->currentDns as $dn) {
            $entry = $this->management->listing->getEntry($dn);
            if ($entry->isTemplate()) {
                $error = new FusionDirectoryError(htmlescape(_('Archiving a template is not possible')));
                $error->display();
                $this->management->removeLocks();
                $this->management->currentDns = [];
                return;
            }
            $infos     = Objects::infos($entry->getTemplatedType());
            $objects[] = [
                'name' => $entry[$infos['nameAttr']][0],
                'dn'   => $dn,
                'icon' => $infos['icon'],
                'type' => $infos['name']
            ];
        }

        $smarty = get_smarty();
        $smarty->assign('Objects', $objects);
        return $smarty->fetch(get_template_path('simple-archive.tpl'));
    }

    public function archiveConfirmed (array $action)
    {
        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->management->currentDns, 'Archiving');

        $success = 0;
        foreach ($this->management->currentDns as $dn) {
            $entry = $this->management->listing->getEntry($dn);

            $errors = archivedObject::archiveObject($entry->type, $dn);
            if (empty($errors)) {
                $success++;
            } else {
                MsgDialog::displayChecks($errors);
            }
            Lock::deleteByObject($dn);
        }

        if ($success > 0) {
            MsgDialog::display(
                _('Archive success'),
                htmlescape(sprintf(_('%d entries were successfully archived'), $success)),
                INFO_DIALOG
            );
        }

        $this->management->currentDns = [];
    }

    function copyPasteHandler (array $action = ['action' => ''])
    {
        if (!is_object($this->management->cpHandler)) {
            return FALSE;
        }

        $this->management->cpHandler->readPost();

        if (($action['action'] == 'copy') || ($action['action'] == 'cut')) {
            $this->management->cpHandler->cleanupQueue();
            foreach ($action['targets'] as $dn) {
                $entry = $this->management->listing->getEntry($dn);
                if (($action['action'] == 'copy') && $entry->checkAcl('r')) {
                    $this->management->cpHandler->addToQueue($dn, 'copy', $entry->getTemplatedType());
                    Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $dn, 'Entry copied!');
                }
                if (($action['action'] == 'cut') && $entry->checkAcl('rd')) {
                    $this->management->cpHandler->addToQueue($dn, 'cut', $entry->getTemplatedType());
                    Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $dn, 'Entry cut!');
                }
            }
        }

        if ($action['action'] == 'paste') {
            $this->management->cpPastingStarted = TRUE;
        }

        if ($this->management->cpPastingStarted && $this->management->cpHandler->entriesQueued()) {
            $this->management->cpHandler->update();
            $data = $this->management->cpHandler->render();
            if (!empty($data)) {
                return $data;
            }
        }

        if (!$this->management->cpHandler->entriesQueued()) {
            $this->management->cpPastingStarted = FALSE;
            $this->management->cpHandler->resetPaste();
        }

        return '';
    }

    function enablePaste ($action, ?ListingEntry $entry = NULL): bool
    {
        if ($entry === NULL) {
            return $this->management->cpHandler->entriesQueued();
        } else {
            return FALSE;
        }
    }

    function handleTemplateApply ($cancel = FALSE)
    {
        if (Management::$skipTemplates) {
            return;
        }
        if ($cancel) {
            $msgs = [];
        } else {
            $msgs = $this->management->tabObject->save();
        }
        if (count($msgs)) {
            MsgDialog::displayChecks($msgs);
            return;
        } else {
            if (!$cancel) {
                Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->management->currentDn, 'Template applied!');
            }
            Lock::deleteByObject($this->management->currentDn);
            if (empty($this->management->currentDns)) {
                $this->management->closeDialogs();
            } else {
                $this->management->last_tabObject = $this->management->tabObject;
                $this->management->tabObject      = NULL;
                $this->management->currentDn      = array_shift($this->management->currentDns);
                $this->management->dialogObject->setNextTarget($this->management->currentDn);
                $this->management->dialogObject->readPost();
            }
        }
    }
}
