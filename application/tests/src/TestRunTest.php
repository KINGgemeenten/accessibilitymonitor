<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\TestRunTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor;

use Triquanta\AccessibilityMonitor\TestRun;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\TestRun
 */
class TestRunTest extends \PHPUnit_Framework_TestCase
{

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\TestRun
     */
    protected $sut;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->sut = new TestRun();
    }

    /**
     * @covers ::setId
     * @covers ::getId
     */
    public function testGetId() {
        $id = mt_rand();
        $this->assertSame($this->sut, $this->sut->setId($id));
        $this->assertSame($id, $this->sut->getId());
    }

    /**
     * @covers ::setId
     *
     * @depends testGetId
     *
     * @expectedException \BadMethodCallException
     */
    public function testChangeId() {
        $id = mt_rand();
        $this->sut->setId($id);
        $this->sut->setId($id);
    }

    /**
     * @covers ::setWebsiteTestResultsId
     * @covers ::getWebsiteTestResultsId
     */
    public function testGetWebsiteTestResultsId() {
        $id = mt_rand();
        $this->assertSame($this->sut, $this->sut->setWebsiteTestResultsId($id));
        $this->assertSame($id, $this->sut->getWebsiteTestResultsId());
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
     * @covers ::setGroup
     * @covers ::getGroup
     */
    public function testGetGroup() {
        $group = 'foo-bar-' . mt_rand();
        $this->assertSame($this->sut, $this->sut->setGroup($group));
        $this->assertSame($group, $this->sut->getGroup());
    }

}
