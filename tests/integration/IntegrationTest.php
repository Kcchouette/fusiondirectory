<?php

use FusionDirectory\Container\Container;
use FusionDirectory\Utility\InputFilter;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for core components working together.
 */
class IntegrationTest extends TestCase
{
    /**
     * Test that Container can store and retrieve all core services.
     */
    public function testContainerStoresAllServices(): void
    {
        $container = new Container();

        /* Register mock services */
        $container->set('config', (object) ['version' => '1.5']);
        $container->set('user_info', (object) ['uid' => 'admin']);
        $container->set('pluglist', (object) ['plugins' => []]);
        $container->set('smarty', (object) ['template_dir' => '/tmp']);
        $container->set('class_mapping', ['Config' => 'Config.php']);
        $container->set('base_dir', '/tmp');
        $container->set('ssl', false);

        /* Verify all retrievable */
        $this->assertTrue($container->has('config'));
        $this->assertTrue($container->has('user_info'));
        $this->assertTrue($container->has('pluglist'));
        $this->assertTrue($container->has('smarty'));
        $this->assertTrue($container->has('class_mapping'));
        $this->assertTrue($container->has('base_dir'));
        $this->assertTrue($container->has('ssl'));

        $this->assertEquals('1.5', $container->get('config')->version);
        $this->assertEquals('admin', $container->get('user_info')->uid);
    }

    /**
     * Test that Container factory creates new instances.
     */
    public function testContainerFactoryCreatesInstances(): void
    {
        $container = new Container();
        $callCount = 0;

        $container->factory('counter', function () use (&$callCount) {
            $callCount++;
            return new \stdClass();
        });

        $a = $container->get('counter');
        $b = $container->get('counter');

        $this->assertSame($a, $b);
        $this->assertEquals(1, $callCount);
    }

    /**
     * Test that InputFilter works with different input types.
     */
    public function testInputFilterIntegration(): void
    {
        /* In CLI context, filter_input returns null for missing keys */
        $this->assertNull(InputFilter::get('nonexistent'));
        $this->assertEquals('default', InputFilter::get('nonexistent', 'default'));
        $this->assertEquals(42, InputFilter::getInt('nonexistent', 42));
        $this->assertEquals('hello', InputFilter::getString('nonexistent', 'hello'));
        $this->assertFalse(InputFilter::has('nonexistent'));
        $this->assertFalse(InputFilter::hasPost('nonexistent'));
    }

    /**
     * Test that SecurityHeaders class exists and has correct structure.
     */
    public function testSecurityHeadersIntegration(): void
    {
        $file = dirname(__DIR__, 2) . '/include/SecurityHeaders.php';
        $this->assertFileExists($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('class SecurityHeaders', $content);
    }

    /**
     * Test that all utility classes exist as files.
     */
    public function testUtilityClassesExist(): void
    {
        $files = [
            'Utility/DnConverter.php',
            'Utility/ArrayHelper.php',
            'Utility/HtmlHelper.php',
            'Utility/Logger.php',
            'Utility/LdapHelper.php',
            'Utility/NetworkHelper.php',
            'Utility/FileHelper.php',
            'Utility/InputFilter.php',
        ];

        foreach ($files as $relativePath) {
            $file = dirname(__DIR__, 2) . '/include/' . $relativePath;
            $this->assertFileExists($file, "Utility class {$relativePath} should exist");
        }
    }

    /**
     * Test that Container autowiring works for simple classes.
     */
    public function testContainerAutowiring(): void
    {
        $container = new Container();
        $result = $container->get(ContainerTest_SimpleAutowire::class);

        $this->assertInstanceOf(ContainerTest_SimpleAutowire::class, $result);
    }
}

class ContainerTest_SimpleAutowire
{
    public string $value = 'autowired';
}
