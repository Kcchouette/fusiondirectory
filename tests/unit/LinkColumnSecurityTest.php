<?php

use PHPUnit\Framework\TestCase;

class LinkColumnSecurityTest extends TestCase
{
    public function testLinkColumnDoesNotUseRawGet(): void
    {
        $file = dirname(__DIR__, 2) . '/include/management/columns/class_LinkColumn.php';
        $content = file_get_contents($file);

        $this->assertStringNotContainsString("\$_GET['plug']", $content,
            'LinkColumn should not use raw $_GET directly');
        $this->assertStringContainsString('InputFilter::get', $content,
            'LinkColumn should use InputFilter');
    }

    public function testLinkColumnEscapesPlugValue(): void
    {
        $file = dirname(__DIR__, 2) . '/include/management/columns/class_LinkColumn.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('htmlspecialchars', $content,
            'LinkColumn should escape plug value with htmlspecialchars');
    }
}
