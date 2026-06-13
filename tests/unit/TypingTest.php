<?php

use PHPUnit\Framework\TestCase;

class TypingTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that all class properties have explicit PHP type declarations.
     * This catches any remaining `public $foo` without a type.
     */
    public function testAllPropertiesHaveTypes(): void
    {
        $files = $this->getPhpFiles($this->includeDir);
        $violations = [];

        foreach ($files as $file) {
            $lines = file($file);
            foreach ($lines as $lineNum => $line) {
                // Match: public/protected/private $var without a type before it
                if (preg_match('/^\s*(public|protected|private)\s+\$/', $line)) {
                    $relativePath = str_replace($this->includeDir . '/', '', $file);
                    $violations[] = sprintf(
                        '%s:%d — %s',
                        $relativePath,
                        $lineNum + 1,
                        trim($line)
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found untyped properties:\n" . implode("\n", $violations)
        );
    }

    /**
     * Spot check: key classes have typed properties.
     */
    public function testKeyClassesAreTyped(): void
    {
        $keyFiles = [
            'Ldap.php',
            'Config.php',
            'UserInfo.php',
            'simpleplugin/SimplePlugin.php',
            'management/Management.php',
        ];

        foreach ($keyFiles as $relativePath) {
            $file = $this->includeDir . '/' . $relativePath;
            $this->assertFileExists($file);

            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/^\s*(public|protected|private)\s+\$/m',
                $content,
                "{$relativePath} still has untyped properties"
            );
        }
    }

    private function getPhpFiles(string $dir): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
