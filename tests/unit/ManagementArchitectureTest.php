<?php

use PHPUnit\Framework\TestCase;

class ManagementArchitectureTest extends TestCase
{
    private string $includeDir;

    protected function setUp(): void
    {
        $this->includeDir = dirname(__DIR__, 2) . '/include';
    }

    /**
     * Verify that Management has the 3 component classes.
     */
    public function testManagementHasComponents(): void
    {
        $file = $this->includeDir . '/management/Management.php';
        $content = file_get_contents($file);

        $this->assertStringContainsString('public ManagementListingComponent $listingComponent', $content);
        $this->assertStringContainsString('public ManagementActionsComponent $actionsComponent', $content);
        $this->assertStringContainsString('public ManagementSnapshotComponent $snapshotComponent', $content);
    }

    /**
     * Verify that the 3 component classes exist.
     */
    public function testComponentClassesExist(): void
    {
        $components = [
            'management/components/ManagementListingComponent.php',
            'management/components/ManagementActionsComponent.php',
            'management/components/ManagementSnapshotComponent.php',
        ];

        foreach ($components as $relativePath) {
            $file = $this->includeDir . '/' . $relativePath;
            $this->assertFileExists($file, "Component class {$relativePath} should exist");
        }
    }
}
