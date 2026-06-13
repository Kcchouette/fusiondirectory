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

    public function handleHooks(string $when, string $mode, array $addAttrs = []): array
    {
        return $this->plugin->handleHooks($when, $mode, $addAttrs);
    }

    public function handlePostEvents(string $mode, array $addAttrs = []): void
    {
        $this->plugin->handlePostEvents($mode, $addAttrs);
    }

    public function handlePreEvents(string $mode, array $addAttrs = []): array
    {
        return $this->plugin->handlePreEvents($mode, $addAttrs);
    }

    public function fillHookAttrs(array &$addAttrs): void
    {
        $this->plugin->fillHookAttrs($addAttrs);
    }

    public function callHook(string $cmd, array $addAttrs = [], array &$returnOutput = [], ?int &$returnCode = null): array
    {
        return $this->plugin->callHook($cmd, $addAttrs, $returnOutput, $returnCode);
    }
}
