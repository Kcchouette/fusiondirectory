<?php

use PHPUnit\Framework\TestCase;

class SessionSecurityTest extends TestCase
{
    public function testSessionCookieSecureIsSet(): void
    {
        $file = dirname(__DIR__, 2) . '/include/Session.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('session.cookie_secure', $content,
            'Session should set cookie_secure when HTTPS is detected');
    }

    public function testSessionCookieSamesiteIsSet(): void
    {
        $file = dirname(__DIR__, 2) . '/include/Session.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('session.cookie_samesite', $content,
            'Session should set cookie_samesite');
    }

    public function testSessionUseStrictModeIsSet(): void
    {
        $file = dirname(__DIR__, 2) . '/include/Session.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('session.use_strict_mode', $content,
            'Session should set use_strict_mode');
    }

    public function testSessionCookieHttponlyIsSet(): void
    {
        $file = dirname(__DIR__, 2) . '/include/Session.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('session.cookie_httponly', $content,
            'Session should set cookie_httponly');
    }
}
