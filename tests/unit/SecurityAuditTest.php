<?php

use PHPUnit\Framework\TestCase;

class SecurityAuditTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that raw $_GET is not used in library code for HTML output.
     */
    public function testNoRawGetInHtmlOutput(): void
    {
        $file = $this->includeDir . '/management/columns/class_LinkColumn.php';
        $content = file_get_contents($file);

        $this->assertStringNotContainsString("\$_GET[", $content,
            'LinkColumn should not use raw $_GET');
    }

    /**
     * Verify that session has secure cookie settings.
     */
    public function testSessionHasSecureCookieSettings(): void
    {
        $file = $this->includeDir . '/Session.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('session.cookie_secure', $content);
        $this->assertStringContainsString('session.cookie_samesite', $content);
        $this->assertStringContainsString('session.use_strict_mode', $content);
    }

    /**
     * Verify that CSP does not contain unsafe-eval.
     */
    public function testCspNoUnsafeEval(): void
    {
        $file = $this->includeDir . '/SecurityHeaders.php';
        $content = file_get_contents($file);

        $this->assertStringNotContainsString("'unsafe-eval'", $content,
            'CSP should not allow unsafe-eval');
    }

    /**
     * Verify that SMD5 uses hash_equals.
     */
    public function testSmd5UsesHashEquals(): void
    {
        $file = $this->includeDir . '/password-methods/PasswordMethodSmd5.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('hash_equals', $content,
            'SMD5 should use hash_equals() instead of ==');
        $this->assertStringNotContainsString('== $hash', $content,
            'SMD5 should not use loose comparison');
    }

    /**
     * Verify that InputFilter is used in Pluglist.
     */
    public function testPluglistUsesInputFilter(): void
    {
        $file = $this->includeDir . '/Pluglist.php';
        $content = file_get_contents($file);

        $this->assertStringNotContainsString("\$_GET['plug']", $content,
            'Pluglist should not use raw $_GET');
    }

    /**
     * Verify that InputFilter is used in ManagementListing.
     */
    public function testManagementListingUsesInputFilter(): void
    {
        $file = $this->includeDir . '/management/ManagementListing.php';
        $content = file_get_contents($file);

        $this->assertStringNotContainsString("\$_GET['plug']", $content,
            'ManagementListing should not use raw $_GET');
    }
}
