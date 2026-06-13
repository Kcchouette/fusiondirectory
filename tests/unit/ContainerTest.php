<?php

use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    private \FusionDirectory\Container\Container $container;

    protected function setUp(): void
    {
        $this->container = new \FusionDirectory\Container\Container();
    }

    public function testSetAndGet(): void
    {
        $this->container->set('foo', 'bar');
        $this->assertEquals('bar', $this->container->get('foo'));
    }

    public function testHas(): void
    {
        $this->container->set('foo', 'bar');
        $this->assertTrue($this->container->has('foo'));
        $this->assertFalse($this->container->has('nonexistent'));
    }

    public function testFactory(): void
    {
        $callCount = 0;
        $this->container->factory('counter', function () use (&$callCount) {
            $callCount++;
            return $callCount;
        });

        $this->assertEquals(1, $this->container->get('counter'));
        $this->assertEquals(1, $this->container->get('counter'));
        $this->assertEquals(1, $callCount);
    }

    public function testFactoryCachesInstance(): void
    {
        $this->container->factory('obj', fn() => new \stdClass());
        $a = $this->container->get('obj');
        $b = $this->container->get('obj');
        $this->assertSame($a, $b);
    }

    public function testAlias(): void
    {
        $this->container->set('real', 'service');
        $this->container->alias('alias', 'real');
        $this->assertEquals('service', $this->container->get('alias'));
        $this->assertTrue($this->container->has('alias'));
    }

    public function testGetUnknownThrowsException(): void
    {
        $this->expectException(\FusionDirectory\Container\NotFoundException::class);
        $this->container->get('nonexistent');
    }

    public function testAutowireSimple(): void
    {
        $result = $this->container->get(ContainerTest_SimpleClass::class);
        $this->assertInstanceOf(ContainerTest_SimpleClass::class, $result);
    }

    public function testAutowireWithDependencies(): void
    {
        $this->container->set('array', ['db' => 'localhost']);
        $result = $this->container->get(ContainerTest_ClassWithDeps::class);
        $this->assertInstanceOf(ContainerTest_ClassWithDeps::class, $result);
        $this->assertEquals(['db' => 'localhost'], $result->config);
    }

    public function testSetOverwrites(): void
    {
        $this->container->set('foo', 'first');
        $this->container->set('foo', 'second');
        $this->assertEquals('second', $this->container->get('foo'));
    }

    public function testAutowireCachesInstance(): void
    {
        $a = $this->container->get(ContainerTest_SimpleClass::class);
        $b = $this->container->get(ContainerTest_SimpleClass::class);
        $this->assertSame($a, $b);
    }
}

class ContainerTest_SimpleClass {}
class ContainerTest_ClassWithDeps
{
    public function __construct(
        public readonly array $config
    ) {}
}
