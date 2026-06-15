<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for PostalAddressAttribute — verifying the LDAP postal address
 * format conversion works correctly.
 */
class PostalAddressAttributeTest extends TestCase
{
    /**
     * Test inputValue converts $-encoded LDAP format to newlines.
     */
    public function testInputValueConvertsToNewlines(): void
    {
        require_once __DIR__ . '/../include/simpleplugin/attributes/class_PostalAddressAttribute.inc';

        $attr = new PostalAddressAttribute('Address', 'Postal address', 'postalAddress', false);

        $ldapValue = '123 Main St$Suite 100$Anytown';
        $result = $attr->inputValue($ldapValue);

        $this->assertStringContainsString("\n", $result);
        $this->assertStringContainsString('123 Main St', $result);
        $this->assertStringContainsString('Suite 100', $result);
        $this->assertStringContainsString('Anytown', $result);
    }

    /**
     * Test inputValue handles escaped dollar signs and backslashes.
     */
    public function testInputValueHandlesEscapedChars(): void
    {
        require_once __DIR__ . '/../include/simpleplugin/attributes/class_PostalAddressAttribute.inc';

        $attr = new PostalAddressAttribute('Address', 'Postal address', 'postalAddress', false);

        // Test escaped dollar sign
        $result = $attr->inputValue('Price: \24100');
        $this->assertStringContainsString('$100', $result);

        // Test escaped backslash
        $result = $attr->inputValue('Path: \5Cserver');
        $this->assertStringContainsString('\\server', $result);
    }
}
