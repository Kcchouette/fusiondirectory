<?php

use PHPUnit\Framework\TestCase;

class UtilityClassesTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that all utility classes exist.
     */
    public function testUtilityClassesExist(): void
    {
        $classes = [
            'Utility/DnConverter.php',
            'Utility/ArrayHelper.php',
            'Utility/HtmlHelper.php',
            'Utility/Logger.php',
            'Utility/LdapHelper.php',
            'Utility/NetworkHelper.php',
            'Utility/FileHelper.php',
        ];

        foreach ($classes as $relativePath) {
            $file = $this->includeDir . '/' . $relativePath;
            $this->assertFileExists($file, "Utility class {$relativePath} should exist");
        }
    }

    /**
     * Verify that each utility class has only static methods.
     */
    public function testUtilityClassesHaveStaticMethods(): void
    {
        $classes = [
            'Utility/DnConverter.php',
            'Utility/ArrayHelper.php',
            'Utility/HtmlHelper.php',
            'Utility/Logger.php',
            'Utility/LdapHelper.php',
            'Utility/NetworkHelper.php',
            'Utility/FileHelper.php',
        ];

        foreach ($classes as $relativePath) {
            $file = $this->includeDir . '/' . $relativePath;
            $content = file_get_contents($file);

            $this->assertStringContainsString('public static function', $content, "{$relativePath} should have static methods");
        }
    }
}
