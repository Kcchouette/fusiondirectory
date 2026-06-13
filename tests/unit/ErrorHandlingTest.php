<?php

use PHPUnit\Framework\TestCase;

class ErrorHandlingTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that die() is no longer used in library code.
     * Entry points (html/) and login methods may still use exit/die intentionally.
     */
    public function testNoDieInLibraryCode(): void
    {
        $libraryFiles = [
            'Pluglist.php',
            'Config.php',
            'LdapFilter.php',
            'IconTheme.php',
            'simpleplugin/attributes/dialog/DialogOrderedArrayAttribute.php',
        ];

        foreach ($libraryFiles as $relativePath) {
            $file = $this->includeDir . '/' . $relativePath;
            $this->assertFileExists($file);

            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/\bdie\s*\(/',
                $content,
                "{$relativePath} still contains die() calls"
            );
        }
    }

    /**
     * Verify that the autoloader no longer uses exit().
     */
    public function testAutoloaderNoExit(): void
    {
        $file = $this->includeDir . '/functions.php';
        $content = file_get_contents($file);

        // Find the autoload function
        preg_match('/function fusiondirectory_autoload.*?\n\}/s', $content, $matches);
        $this->assertNotEmpty($matches, 'fusiondirectory_autoload function not found');

        $this->assertDoesNotMatchRegularExpression(
            '/\bexit\s*\(/',
            $matches[0],
            'fusiondirectory_autoload still contains exit()'
        );
    }
}
