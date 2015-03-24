<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Testing|StorageBasedTesterTest.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Triquanta\AccessibilityMonitor\Url;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Testing\StorageBasedTester
 */
class StorageBasedTesterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * The flooding thresholds.
     *
     * @var int[]
     *   Keys are periods in seconds, values are maximum number of requests.
     *   They represent the maximum number of requests that can be made to a
     *   host in the past period.
     */
    protected $floodingThresholds = [];

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $logger;

    /**
     * The result storage.
     *
     * @var \Triquanta\AccessibilityMonitor\StorageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $resultStorage;

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\StorageBasedTester
     */
    protected $sut;

    /**
     * The tester.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $tester;

    public function setUp()
    {
        $this->floodingThresholds[mt_rand()] = mt_rand();

        $this->logger = $this->getMock('\Psr\Log\LoggerInterface');

        $this->resultStorage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

        $this->tester = $this->getMock('\Triquanta\AccessibilityMonitor\Testing\TesterInterface');

        $this->sut = new StorageBasedTester($this->logger, $this->tester,
          $this->resultStorage, $this->floodingThresholds);
    }

    /**
     * @covers ::__construct
     */
    public function testConstruct()
    {
        $this->sut = new StorageBasedTester($this->logger, $this->tester,
          $this->resultStorage, $this->floodingThresholds);
    }

    /**
     * @covers ::run
     *
     * @dataProvider providerTestRun
     */
    public function testRun($expected, $testSuccess, $storageSuccess = NULL)
    {
        $url = new Url();

        $this->tester->expects($this->once())
          ->method('run')
          ->with($url)
          ->willReturn($testSuccess);

        $this->resultStorage->expects(is_bool($storageSuccess) ? $this->once() : $this->never())
          ->method('saveUrl')
          ->with($url)
          ->willReturn($storageSuccess);

        $this->assertSame($expected, $this->sut->run($url));
    }

    /**
     * Provides data to self::testRun().
     */
    public function providerTestRun() {
        return [
          [TRUE, TRUE, TRUE],
          [FALSE, TRUE, FALSE],
          [FALSE, FALSE, NULL],
        ];
    }

    /**
     * @covers ::run
     */
    public function testRunWithException()
    {
        $url = new Url();

        $this->tester->expects($this->once())
            ->method('run')
            ->with($url)
            ->willThrowException(new \Exception());

        $this->resultStorage->expects($this->never())
          ->method('saveUrl');

        $this->assertFalse($this->sut->run($url));
    }

}
