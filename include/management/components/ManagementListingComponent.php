<?php
declare(strict_types=1);

/**
 * Handles listing display and filtering for a Management instance.
 */
class ManagementListingComponent
{
    public function __construct(
        private Management $management
    ) {
    }

    public function renderList(): string
    {
        return $this->management->renderList();
    }

    protected function renderFilter(): string
    {
        return $this->management->renderFilter();
    }

    protected function renderActionMenu(): string
    {
        return $this->management->renderActionMenu();
    }

    public function renderActionColumn(ListingEntry $entry): string
    {
        return $this->management->renderActionColumn($entry);
    }

    public function fillActionRowClasses(array &$classes, ListingEntry $entry): void
    {
        $this->management->fillActionRowClasses($classes, $entry);
    }

    public function getColumnConfiguration(): array
    {
        return $this->management->getColumnConfiguration();
    }

    public function setColumnConfiguration($columns): void
    {
        $this->management->setColumnConfiguration($columns);
    }
}
