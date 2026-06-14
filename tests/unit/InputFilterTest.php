<?php

use FusionDirectory\Utility\InputFilter;
use PHPUnit\Framework\TestCase;

class InputFilterTest extends TestCase
{
    public function testGetReturnsDefaultForMissingKey(): void
    {
        $this->assertNull(InputFilter::get('nonexistent'));
        $this->assertEquals('default', InputFilter::get('nonexistent', 'default'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse(InputFilter::has('nonexistent'));
    }

    public function testGetIntReturnsDefaultForMissingKey(): void
    {
        $this->assertEquals(42, InputFilter::getInt('nonexistent', 42));
    }

    public function testGetStringReturnsDefaultForMissingKey(): void
    {
        $this->assertEquals('default', InputFilter::getString('nonexistent', 'default'));
    }
}
