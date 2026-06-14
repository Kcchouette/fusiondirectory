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

    protected function setUpListing ()
    {
        $this->management->listing = new ManagementListing($this->management);
    }

    protected function setUpFilter (array $filterElementDefinitions)
    {
        $this->management->filter = new ManagementFilter($this->management, NULL, $filterElementDefinitions);
    }

    public function renderList (): string
    {
        $listRender   = $this->management->listing->render();
        $filterRender = $this->renderFilter();
        $actionMenu   = $this->renderActionMenu();

        $smarty = getSmarty();
        $smarty->assign('usePrototype', 'true');
        $smarty->assign('LIST', $listRender);
        $smarty->assign('FILTER', $filterRender);
        $smarty->assign('ACTIONS', $actionMenu);
        $smarty->assign('SIZELIMIT', user_info()->getSizeLimitHandler()->renderWarning());
        $smarty->assign('NAVIGATION', $this->management->listing->renderNavigation($this->management->skipConfiguration));
        $smarty->assign('BASE', $this->management->listing->renderBase());
        $smarty->assign('HEADLINE', $this->management->headline);

        return $this->management->getHeader() . $smarty->fetch(getTemplatePath('management/management.tpl'));
    }

    protected function renderFilter (): string
    {
        return $this->management->filter->render();
    }

    protected function renderActionMenu (): string
    {
        $menuActions = [];
        foreach ($this->management->actions as $action) {
            $action->fillMenuItems($menuActions);
        }

        if (empty($menuActions)) {
            return '';
        }

        $smarty = getSmarty();
        $smarty->assign('actions', $menuActions);
        return $smarty->fetch(getTemplatePath('management/actionmenu.tpl'));
    }

    public function renderActionColumn (ListingEntry $entry): string
    {
        $result = '';
        foreach ($this->management->actions as $action) {
            $result .= $action->renderColumnIcons($entry);
        }

        return $result;
    }

    public function fillActionRowClasses (&$classes, ListingEntry $entry)
    {
        foreach ($this->management->actions as $action) {
            $action->fillRowClasses($classes, $entry);
        }
    }

    public function getColumnConfiguration (): array
    {
        if (!isset($this->management->columnConfiguration)) {
            $this->management->columnConfiguration = config()->getManagementConfig(get_class($this->management));
        }

        if (!isset($this->management->columnConfiguration)) {
            $this->management->columnConfiguration = Management::$columns;
        }

        return $this->management->columnConfiguration;
    }

    public function setColumnConfiguration ($columns)
    {
        $this->management->columnConfiguration = $columns;
        $this->management->listing->reloadColumns();
    }
}
