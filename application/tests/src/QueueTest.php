<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\QueueTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor;

use Triquanta\AccessibilityMonitor\Queue;;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Queue
 */
class QueueTest extends \PHPUnit_Framework_TestCase
{

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\Queue
     */
    protected $sut;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->sut = new Queue();
    }

    /**
     * @covers ::setName
     * @covers ::getName
     */
    public function testGetName() {
        $name = 'FooBar' . mt_rand();
        $this->assertSame($this->sut, $this->sut->setName($name));
        $this->assertSame($name, $this->sut->getName());
    }

    /**
     * @covers ::setName
     *
     * @depends testGetName
     *
     * @expectedException \BadMethodCallException
     */
    public function testChangeName() {
        $name = 'FooBar' . mt_rand();
        $this->sut->setName($name);
        $this->sut->setName($name);
    }

    /**
     * @covers ::setPriority
     * @covers ::getPriority
     */
    public function testGetPriority() {
        $priority = mt_rand();
        $this->assertSame($this->sut, $this->sut->setPriority($priority));
        $this->assertSame($priority, $this->sut->getPriority());
    }

    /**
     * @covers ::setCreated
     * @covers ::getCreated
     */
    public function testGetCreated() {
        $created = mt_rand();
        $this->assertSame($this->sut, $this->sut->setCreated($created));
        $this->assertSame($created, $this->sut->getCreated());
    }

    /**
     * @covers ::setLastRequest
     * @covers ::getLastRequest
     */
    public function testGetLastRequest() {
        $lastRequest = mt_rand();
        $this->assertSame($this->sut, $this->sut->setLastRequest($lastRequest));
        $this->assertSame($lastRequest, $this->sut->getLastRequest());
    }

}
