<?php

use PHPUnit\Framework\TestCase;

class NamingTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that all class declarations use PascalCase.
     */
    public function testClassDeclarationsArePascalCase(): void
    {
        $files = $this->getPhpFiles($this->includeDir);
        $violations = [];

        foreach ($files as $file) {
            $lines = file($file);
            foreach ($lines as $lineNum => $line) {
                if (preg_match('/^class\s+([a-z][a-zA-Z0-9]*)/', $line, $matches)) {
                    $className = $matches[1];
                    $relativePath = str_replace($this->includeDir . '/', '', $file);
                    $violations[] = "{$relativePath}:{$lineNum} — class {$className}";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found lowercase class declarations:\n" . implode("\n", $violations)
        );
    }

    /**
     * Verify that key files have been renamed to PascalCase.php.
     */
    public function testKeyFilesArePascalCase(): void
    {
        $expectedFiles = [
            'Config.php',
            'UserInfo.php',
            'Session.php',
            'Pluglist.php',
            'Ldap.php',
            'simpleplugin/SimplePlugin.php',
            'management/Management.php',
        ];

        foreach ($expectedFiles as $filename) {
            $file = $this->includeDir . '/' . $filename;
            $this->assertFileExists($file, "File {$filename} should exist with PascalCase name");
        }
    }

    /**
     * Verify that old class_*.inc files no longer exist for key classes.
     */
    public function testOldFilesRemoved(): void
    {
        $oldFiles = [
            'class_config.inc',
            'class_userinfo.inc',
            'class_session.inc',
            'class_pluglist.inc',
            'class_ldap.inc',
            'simpleplugin/class_simplePlugin.inc',
            'management/class_management.inc',
        ];

        foreach ($oldFiles as $filename) {
            $file = $this->includeDir . '/' . $filename;
            $this->assertFileDoesNotExist($file, "Old file {$filename} should be removed");
        }
    }

    private function getPhpFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'inc'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
