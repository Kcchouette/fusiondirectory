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

    public function detectPostActions(): array
    {
        return $this->management->detectPostActions();
    }

    public function handleAction(array $action): void
    {
        $this->management->handleAction($action);
    }

    protected function handleSubAction(array $action): bool
    {
        return $this->management->handleSubAction($action);
    }

    public function newEntry(array $action): void
    {
        $this->management->newEntry($action);
    }

    public function newEntryTemplate(array $action): void
    {
        $this->management->newEntryTemplate($action);
    }

    public function newEntryFromTemplate(array $action): void
    {
        $this->management->newEntryFromTemplate($action);
    }

    public function applyTemplateToEntry(array $action): void
    {
        $this->management->applyTemplateToEntry($action);
    }

    public function editEntry(array $action): void
    {
        $this->management->editEntry($action);
    }

    public function cancelEdit(): void
    {
        $this->management->cancelEdit();
    }

    public function saveChanges(): void
    {
        $this->management->saveChanges();
    }

    public function applyChanges(): void
    {
        $this->management->applyChanges();
    }

    public function removeRequested(array $action): void
    {
        $this->management->removeRequested($action);
    }

    protected function removeConfirmationDialog(array $objects): void
    {
        $this->management->removeConfirmationDialog($objects);
    }

    public function removeConfirmed(array $action): void
    {
        $this->management->removeConfirmed($action);
    }

    public function archiveRequested(array $action): void
    {
        $this->management->archiveRequested($action);
    }

    public function archiveConfirmed(array $action): void
    {
        $this->management->archiveConfirmed($action);
    }

    public function copyPasteHandler(array $action = []): void
    {
        $this->management->copyPasteHandler($action);
    }

    public function enablePaste($action, ?ListingEntry $entry = null): bool
    {
        return $this->management->enablePaste($action, $entry);
    }
}
