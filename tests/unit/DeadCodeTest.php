<?php

use PHPUnit\Framework\TestCase;

class DeadCodeTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = dirname(__DIR__, 2);
    }

    /**
     * Verify that track_vars (removed in PHP 5.4) is not used.
     */
    public function testNoTrackVarsUsage(): void
    {
        $output = [];
        $exitCode = 0;
        exec(
            'grep -rn "track_vars" ' . escapeshellarg($this->projectDir) . '/include/ --include="*.inc" --include="*.php" 2>/dev/null',
            $output,
            $exitCode
        );

        // grep exit code 1 = not found (good), 0 = found (bad)
        $this->assertNotEquals(0, $exitCode, 'track_vars was found in codebase: ' . implode("\n", $output));
    }

    /**
     * Verify that deprecated prepare4filter method is removed.
     */
    public function testDeprecatedPrepare4filterRemoved(): void
    {
        $file = $this->projectDir . '/include/class_ldap.inc';
        $content = file_get_contents($file);

        $this->assertDoesNotMatchRegularExpression(
            '/function\s+prepare4filter/',
            $content,
            'Deprecated prepare4filter() should be removed'
        );
    }

    /**
     * Verify that deprecated LDAP::ls method is removed.
     */
    public function testDeprecatedLsMethodRemoved(): void
    {
        $file = $this->projectDir . '/include/class_ldap.inc';
        $content = file_get_contents($file);

        $this->assertDoesNotMatchRegularExpression(
            '/function\s+ls\s*\(/',
            $content,
            'Deprecated ls() method should be removed'
        );
    }

    /**
     * Verify that deprecated normalizeLdap function is removed.
     */
    public function testDeprecatedNormalizeLdapRemoved(): void
    {
        $file = $this->projectDir . '/include/functions.inc';
        $content = file_get_contents($file);

        $this->assertDoesNotMatchRegularExpression(
            '/function\s+normalizeLdap\s*\(/',
            $content,
            'Deprecated normalizeLdap() function should be removed'
        );
    }
}
