<?php

use PHPUnit\Framework\TestCase;

class SimplePluginArchitectureTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that SimplePlugin has the 4 component classes.
     */
    public function testSimplePluginHasComponents(): void
    {
        $file = $this->includeDir . '/simpleplugin/SimplePlugin.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('public AclChecker $acl', $content);
        $this->assertStringContainsString('public PluginRenderer $renderer', $content);
        $this->assertStringContainsString('public PluginHookManager $hooks', $content);
        $this->assertStringContainsString('public LdapReader $ldapReader', $content);
    }

    /**
     * Verify that the 4 component classes exist.
     */
    public function testComponentClassesExist(): void
    {
        $components = [
            'simpleplugin/components/AclChecker.php',
            'simpleplugin/components/PluginRenderer.php',
            'simpleplugin/components/PluginHookManager.php',
            'simpleplugin/components/LdapReader.php',
        ];

        foreach ($components as $relativePath) {
            $file = $this->includeDir . '/' . $relativePath;
            $this->assertFileExists($file, "Component class {$relativePath} should exist");
        }
    }
}
