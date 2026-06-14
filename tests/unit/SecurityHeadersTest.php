<?php

use PHPUnit\Framework\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function testSecurityHeadersClassExists(): void
    {
        $file = dirname(__DIR__, 2) . '/include/SecurityHeaders.php';
        $this->assertFileExists($file);

        $content = file_get_contents($file);
        $this->assertStringContainsString('class SecurityHeaders', $content);
        $this->assertStringContainsString('public static function send', $content);
    }

    public function testSecurityHeadersSendMethod(): void
    {
        $file = dirname(__DIR__, 2) . '/include/SecurityHeaders.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('X-Content-Type-Options', $content);
        $this->assertStringContainsString('X-Frame-Options', $content);
        $this->assertStringContainsString('Referrer-Policy', $content);
        $this->assertStringContainsString('Strict-Transport-Security', $content);
        $this->assertStringContainsString('Content-Security-Policy', $content);
    }

    public function testSecurityHeadersChecksHeadersSent(): void
    {
        $file = dirname(__DIR__, 2) . '/include/SecurityHeaders.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('headers_sent()', $content);
    }
}
