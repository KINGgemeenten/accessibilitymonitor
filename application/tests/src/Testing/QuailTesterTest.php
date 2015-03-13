<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Testing|QuailTesterTest.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Triquanta\AccessibilityMonitor\Url;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Testing\QuailTester
 */
class QuailTesterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $logger;

    /**
     * The Phantom JS manager.
     *
     * @var \Triquanta\AccessibilityMonitor\PhantomJsInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $phantomJs;

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\QuailTester
     */
    protected $sut;

    public function setUp()
    {
        $this->logger = $this->getMock('\Psr\Log\LoggerInterface');

        $this->phantomJs = $this->getMock('\Triquanta\AccessibilityMonitor\PhantomJsInterface');

        $this->sut = new QuailTester($this->phantomJs, $this->logger);
    }

    /**
     * @covers ::__construct
     */
    public function testConstruct()
    {
        $this->sut = new QuailTester($this->phantomJs, $this->logger);
    }

    /**
     * @covers ::run
     * @covers ::processQuailResult
     * @covers ::getWcag2Mapping
     */
    public function testRun()
    {
        $urlString = 'http://example.com/' . mt_rand();

        $url = new Url();
        $url->setUrl($urlString);

        $json = file_get_contents(__DIR__ . '/../../quail_output.json');

        $this->phantomJs->expects($this->once())
          ->method('getQuailResult')
          ->with($urlString)
          ->willReturn($json);

        $this->sut->run($url);
        $this->assertSame(TestingStatusInterface::STATUS_TESTED,
          $url->getTestingStatus());
        $this->assertNotEmpty($url->getQuailResult());
        $this->assertNotEmpty($url->getQuailResultCases());
    }

    /**
     * @covers ::run
     */
    public function testRunWithPhantomJsException()
    {
        $urlString = 'http://example.com/' . mt_rand();

        $url = new Url();
        $url->setUrl($urlString);

        $this->phantomJs->expects($this->once())
          ->method('getQuailResult')
          ->with($urlString)
          ->willThrowException(new \Exception());

        $this->sut->run($url);
        $this->assertSame(TestingStatusInterface::STATUS_ERROR,
          $url->getTestingStatus());
        $this->assertEmpty($url->getQuailResult());
        $this->assertEmpty($url->getQuailResultCases());
    }

}
