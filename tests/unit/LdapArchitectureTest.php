<?php

use PHPUnit\Framework\TestCase;

class LdapArchitectureTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that the LDAP facade has the 4 component classes.
     */
    public function testLdapFacadeHasComponents(): void
    {
        $file = $this->includeDir . '/Ldap.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('public LdapConnection $connection', $content);
        $this->assertStringContainsString('public LdapSearch $search', $content);
        $this->assertStringContainsString('public LdapWriter $writer', $content);
        $this->assertStringContainsString('public LdapSerializer $serializer', $content);
    }

    /**
     * Verify that the 4 component classes exist.
     */
    public function testComponentClassesExist(): void
    {
        $components = [
            'Ldap/LdapConnection.php',
            'Ldap/LdapSearch.php',
            'Ldap/LdapWriter.php',
            'Ldap/LdapSerializer.php',
        ];

        foreach ($components as $relativePath) {
            $file = $this->includeDir . '/' . $relativePath;
            $this->assertFileExists($file, "Component class {$relativePath} should exist");
        }
    }

    /**
     * Verify that each component class has proper structure.
     */
    public function testComponentClassesHaveConstructor(): void
    {
        $components = [
            'Ldap/LdapConnection.php',
            'Ldap/LdapSearch.php',
            'Ldap/LdapWriter.php',
            'Ldap/LdapSerializer.php',
        ];

        foreach ($components as $relativePath) {
            $file = $this->includeDir . '/' . $relativePath;
            $content = file_get_contents($file);

            $this->assertStringContainsString('class ', $content, "{$relativePath} should declare a class");
            $this->assertStringContainsString('public function __construct', $content, "{$relativePath} should have a constructor");
        }
    }
}
