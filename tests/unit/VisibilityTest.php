<?php

use PHPUnit\Framework\TestCase;

class VisibilityTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that no PHP4-style `var $` declarations remain in the codebase.
     */
    public function testNoVarKeywordInIncludeDirectory(): void
    {
        $files = $this->getPhpFiles($this->includeDir);
        $violations = [];

        foreach ($files as $file) {
            $lines = file($file);
            foreach ($lines as $lineNum => $line) {
                if (preg_match('/^\s*var\s+\$/', $line)) {
                    $violations[] = sprintf(
                        '%s:%d — %s',
                        str_replace($this->includeDir . '/', '', $file),
                        $lineNum + 1,
                        trim($line)
                    );
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "Found PHP4-style 'var' declarations:\n" . implode("\n", $violations)
        );
    }

    /**
     * Spot check: key classes no longer use `var $`.
     */
    public function testKeyClassesHaveVisibleProperties(): void
    {
        $keyFiles = [
            'Ldap.php',
            'Config.php',
            'UserInfo.php',
            'Pluglist.php',
        ];

        foreach ($keyFiles as $filename) {
            $file = $this->includeDir . '/' . $filename;
            $this->assertFileExists($file);

            $content = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression(
                '/^\s*var\s+\$/m',
                $content,
                "{$filename} still contains 'var \$' declarations"
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
            if ($file->isFile() && in_array($file->getExtension(), ['php', 'inc'], true)) {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
