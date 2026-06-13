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

    public function render(): string
    {
        return $this->plugin->render();
    }

    public function showHeader(string $buttonHtmlText, string $htmlText, bool $pluginEnabled, bool $buttonDisabled = false, string $name = 'modify_state'): string
    {
        return $this->plugin->show_header($buttonHtmlText, $htmlText, $pluginEnabled, $buttonDisabled, $name);
    }

    public function renderAttributes(bool $readOnly = false): void
    {
        $this->plugin->renderAttributes($readOnly);
    }

    public function getDisplayHeaderInfos(): array
    {
        return $this->plugin->getDisplayHeaderInfos();
    }

    public function inheritanceDisplay(): string
    {
        return $this->plugin->inheritanceDisplay();
    }

    public function execute(): string
    {
        return $this->plugin->execute();
    }
}
