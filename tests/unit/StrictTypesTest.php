<?php

use PHPUnit\Framework\TestCase;

class StrictTypesTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that all PHP files in include/ have declare(strict_types=1).
     */
    public function testAllFilesHaveStrictTypes(): void
    {
        $files = $this->getPhpFiles($this->includeDir);
        $missing = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            if (strpos($content, 'declare(strict_types=1)') === false) {
                $missing[] = str_replace($this->includeDir . '/', '', $file);
            }
        }

        $this->assertEmpty(
            $missing,
            "Files missing declare(strict_types=1):\n" . implode("\n", $missing)
        );
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
