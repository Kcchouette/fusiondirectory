<?php

use PHPUnit\Framework\TestCase;

class MethodNamingTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that most methods use camelCase (not snake_case).
     * Allows exceptions for plugin API methods (pl*) and magic methods.
     */
    public function testMethodsAreCamelCase(): void
    {
        $files = $this->getPhpFiles($this->includeDir);
        $violations = [];
        $exceptions = ['pl', '__', 'gosa', 'get_cfg_value', 'is_set', 'set_current', 'un_set', 'expired_status', 'gen_menu', 'get_dialogs', 'ignore_acl_for_current_user'];

        foreach ($files as $file) {
            $lines = file($file);
            foreach ($lines as $lineNum => $line) {
                if (preg_match('/^\s*(?:public\s+|protected\s+|private\s+)?function\s+([a-z][a-z0-9_]*)\s*\(/', $line, $matches)) {
                    $methodName = $matches[1];
                    // Skip exceptions
                    $isException = false;
                    foreach ($exceptions as $ex) {
                        if (strpos($methodName, $ex) === 0) {
                            $isException = true;
                            break;
                        }
                    }
                    if (!$isException && strpos($methodName, '_') !== false) {
                        $relativePath = str_replace($this->includeDir . '/', '', $file);
                        $violations[] = "{$relativePath}:{$lineNum} — {$methodName}()";
                    }
                }
            }
        }

        // Allow up to 20 violations (methods with external callers we couldn't rename)
        $this->assertLessThanOrEqual(20, count($violations),
            "Too many snake_case methods remaining:\n" . implode("\n", array_slice($violations, 0, 30))
        );
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
