<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Testing|WappalyzerTesterTest.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Triquanta\AccessibilityMonitor\Url;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Testing\WappalyzerTester
 */
class WappalyzerTesterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * The Phantom JS manager.
     *
     * @var \Triquanta\AccessibilityMonitor\PhantomJsInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $phantomJs;

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\WappalyzerTester
     */
    protected $sut;

    public function setUp()
    {
        $this->phantomJs = $this->getMock('\Triquanta\AccessibilityMonitor\PhantomJsInterface');

        $this->sut = new WappalyzerTester($this->phantomJs);
    }

    /**
     * @covers ::__construct
     */
    public function testConstruct()
    {
        $this->sut = new WappalyzerTester($this->phantomJs);
    }

    /**
     * @covers ::run
     *
     * @dataProvider providerTestRun
     */
    public function testRun($isRoot)
    {
        $urlString = 'http://example.com/' . mt_rand();

        $url = new Url();
        $url->setUrl($urlString);
        $url->setRoot($isRoot);

        $detectedApps = ['foo', 'bar', 'baz'];
        $detectedAppsString = 'foo|bar|baz';

        $this->phantomJs->expects($isRoot ? $this->once() : $this->never())
          ->method('getDetectedApps')
          ->with($urlString)
          ->willReturn($detectedApps);

        $this->assertInternalType('bool', $this->sut->run($url));
        if ($isRoot) {
            $this->assertSame($detectedAppsString, $url->getCms());
        }
    }

    /**
     * Provides data to self::testRun().
     */
    public function providerTestRun()
    {
        return [
          [true],
          [false],
        ];
    }

}
