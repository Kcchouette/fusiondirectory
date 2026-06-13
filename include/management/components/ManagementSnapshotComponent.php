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

    public function createSnapshotDialog(array $action): void
    {
        $this->management->createSnapshotDialog($action);
    }

    public function restoreSnapshotDialog(array $action): void
    {
        $this->management->restoreSnapshotDialog($action);
    }

    public function getSnapshotBases(): array
    {
        return $this->management->getSnapshotBases();
    }

    public function getAllDeletedSnapshots(): array
    {
        return $this->management->getAllDeletedSnapshots();
    }

    public function getAvailableSnapsShots(string $dn): array
    {
        return $this->management->getAvailableSnapsShots($dn);
    }

    public function enableSnapshotRestore($action, ?ListingEntry $entry = null): bool
    {
        return $this->management->enableSnapshotRestore($action, $entry);
    }

    public function createSnapshot(string $dn, string $description, string $snapshotSource = 'FD'): void
    {
        $this->management->createSnapshot($dn, $description, $snapshotSource);
    }

    public function restoreSnapshot(string $dn): void
    {
        $this->management->restoreSnapshot($dn);
    }

    public function removeSnapshot(string $dn): void
    {
        $this->management->removeSnapshot($dn);
    }
}
