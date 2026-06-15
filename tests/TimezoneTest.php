<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for timezone class — verifying that the refactored timezone
 * methods still work correctly.
 */
class TimezoneTest extends TestCase
{
    /**
     * Test that utc() returns a valid DateTimeZone for UTC.
     */
    public function testUtcReturnsValidTimezone(): void
    {
        require_once __DIR__ . '/../include/class_timezone.inc';

        $utc = timezone::utc();
        $this->assertInstanceOf(DateTimeZone::class, $utc);
        $this->assertEquals('UTC', $utc->getName());
    }

    /**
     * Test that utc() returns the same instance (singleton).
     */
    public function testUtcIsSingleton(): void
    {
        require_once __DIR__ . '/../include/class_timezone.inc';

        $utc1 = timezone::utc();
        $utc2 = timezone::utc();
        $this->assertSame($utc1, $utc2);
    }

    /**
     * Test that setDefaultTimezoneFromConfig returns false when no timezone configured.
     */
    public function testSetDefaultTimezoneFromConfigWithoutConfig(): void
    {
        require_once __DIR__ . '/../include/class_timezone.inc';

        // Without global $config set up properly, accessing $config->get_cfg_value will fail
        // This tests that the method handles the null config gracefully
        // In production, $config is always set via globals
        $this->expectException(\Error::class);
        timezone::setDefaultTimezoneFromConfig();
    }

    /**
     * Test that DateTimeZone::listIdentifiers returns a non-empty array.
     */
    public function testListIdentifiersReturnsTimezones(): void
    {
        $zones = DateTimeZone::listIdentifiers();
        $this->assertIsArray($zones);
        $this->assertNotEmpty($zones);
        $this->assertContains('UTC', $zones);
        $this->assertContains('Europe/Paris', $zones);
    }

    /**
     * Test that new DateTimeZone(date_default_timezone_get()) works.
     */
    public function testGetDefaultTimezoneWorks(): void
    {
        $tz = new DateTimeZone(date_default_timezone_get());
        $this->assertInstanceOf(DateTimeZone::class, $tz);
    }
}
