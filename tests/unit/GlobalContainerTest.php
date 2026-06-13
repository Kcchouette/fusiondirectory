<?php

use PHPUnit\Framework\TestCase;

class GlobalContainerTest extends TestCase
{
    /**
     * Test that container() returns a singleton.
     */
    public function testContainerSingleton(): void
    {
        $a = container();
        $b = container();
        $this->assertSame($a, $b);
    }

    /**
     * Test that container implements ContainerInterface.
     */
    public function testContainerImplementsInterface(): void
    {
        $c = container();
        $this->assertInstanceOf(\FusionDirectory\Container\ContainerInterface::class, $c);
    }

    /**
     * Test that container supports set/get/has.
     */
    public function testContainerSetGetHas(): void
    {
        $c = container();
        $c->set('test_key', 'test_value');
        $this->assertTrue($c->has('test_key'));
        $this->assertEquals('test_value', $c->get('test_key'));
    }
}
