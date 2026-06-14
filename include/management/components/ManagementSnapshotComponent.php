<?php
declare(strict_types=1);

/**
 * Handles snapshot operations for a Management instance.
 */
class ManagementSnapshotComponent
{
    public function __construct(
        private Management $management
    ) {
    }

    function createSnapshotDialog (array $action)
    {
        Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $action['targets'], 'Snapshot creation initiated!');

        $this->management->currentDn = array_pop($action['targets']);
        if (empty($this->management->currentDn)) {
            return;
        }
        $entry = $this->management->listing->getEntry($this->management->currentDn);
        if ($entry->snapshotCreationAllowed()) {
            $this->management->dialogObject = new SnapshotCreateDialog($this->management->currentDn, $this->management, '');
        } else {
            $error = new FusionDirectoryError(
                htmlescape(sprintf(
                    _('You are not allowed to create a snapshot for %s.'),
                    $this->management->currentDn
                ))
            );
            $error->display();
        }
    }

    function restoreSnapshotDialog (array $action)
    {
        if (empty($action['targets'])) {
            $this->management->currentDn = $this->management->listing->getBase();
            $aclCategories   = $this->management->listAclCategories();
        } else {
            $this->management->currentDn = $action['targets'][0];
            if (empty($this->management->currentDn)) {
                return;
            }
            $aclCategories = [Objects::infos($this->management->listing->getEntry($this->management->currentDn)->getTemplatedType())['aclCategory']];
        }

        if (user_info()->allowSnapshotRestore($this->management->currentDn, $aclCategories, empty($action['targets']))) {
            Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $this->management->currentDn, 'Snapshot restoring initiated!');
            $this->management->dialogObject = new SnapshotRestoreDialog($this->management->currentDn, $this->management, empty($action['targets']), $aclCategories);
        } else {
            $error = new FusionDirectoryError(
                htmlescape(sprintf(
                    _('You are not allowed to restore a snapshot for %s.'),
                    $this->management->currentDn
                ))
            );
            $error->display();
        }
    }

    function getSnapshotBases (): array
    {
        $bases = [];
        foreach ($this->management->objectTypes as $type) {
            $infos   = Objects::infos($type);
            $bases[] = $infos['ou'] . $this->management->listing->getBase();
        }

        if (!count($bases)) {
            $bases[] = $this->management->listing->getBase();
        }

        return array_unique($bases);
    }

    function getAllDeletedSnapshots (): array
    {
        $bases = $this->getSnapshotBases();
        $tmp   = [];
        foreach ($bases as $base) {
            $tmp = array_merge($tmp, $this->management->snapHandler->getAllDeletedSnapshots($base));
        }
        return $tmp;
    }

    function getAvailableSnapsShots (string $dn): array
    {
        return $this->management->snapHandler->getAvailableSnapsShots($dn);
    }

    function enableSnapshotRestore ($action, ?ListingEntry $entry = NULL): bool
    {
        if ($entry !== NULL) {
            return $this->management->snapHandler->hasSnapshots($entry->dn);
        } else {
            return $this->management->snapHandler->hasDeletedSnapshots($this->getSnapshotBases());
        }
    }

    function createSnapshot (string $dn, string $description, string $snapshotSource = 'FD')
    {
        if (empty($dn) || ($this->management->currentDn !== $dn)) {
            trigger_error('There was a problem with the snapshot workflow');
            return;
        }
        $entry = $this->management->listing->getEntry($dn);
        if ($entry->snapshotCreationAllowed()) {
            $this->management->snapHandler->createSnapshot($dn, $description, $entry->type, $snapshotSource);
            Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $dn, 'Snapshot created!');
        } else {
            $error = new FusionDirectoryPermissionError(htmlescape(sprintf(_('You are not allowed to restore a snapshot for %s.'), $dn)));
            $error->display();
        }
    }

    function restoreSnapshot (string $dn)
    {
        if (!empty($dn) && user_info()->allowSnapshotRestore($dn, $this->management->dialogObject->aclCategory, $this->management->dialogObject->global)) {
            $dn = $this->management->snapHandler->restoreSnapshot($dn);
            Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $dn, 'Snapshot restored');
            $this->management->closeDialogs();
            if ($dn !== FALSE) {
                $this->management->listing->focusDn($dn);
                $entry           = $this->management->listing->getEntry($dn);
                $this->management->currentDn = $dn;
                Lock::add($this->management->currentDn);

                $this->management->openTabObject(Objects::open($this->management->currentDn, $entry->getTemplatedType()));
                $this->management->saveChanges();
            }
        } else {
            $error = new FusionDirectoryPermissionError(htmlescape(sprintf(_('You are not allowed to restore a snapshot for %s.'), $dn)));
            $error->display();
        }
    }

    function removeSnapshot (string $dn)
    {
        if (!empty($dn) && user_info()->allowSnapshotDelete($dn, $this->management->dialogObject->aclCategory)) {
            $this->management->snapHandler->removeSnapshot($dn);
            Logging::debug(DEBUG_TRACE, __LINE__, __FUNCTION__, __FILE__, $dn, 'Snapshot deleted');
        } else {
            $error = new FusionDirectoryPermissionError(htmlescape(sprintf(_('You are not allowed to delete a snapshot for %s.'), $dn)));
            $error->display();
        }
    }
}
