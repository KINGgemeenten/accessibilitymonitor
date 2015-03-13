<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Testing|GroupedTesterTest.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Triquanta\AccessibilityMonitor\Url;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Testing\GroupedTester
 */
class GroupedTesterTest extends \PHPUnit_Framework_TestCase  {

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\GroupedTester
     */
    protected $sut;

    /**
     * The testers.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface|\PHPUnit_Framework_MockObject_MockObject[]
     */
    protected $testers = [];

    public function setUp() {
        $this->testers[] = $this->getMock('\Triquanta\AccessibilityMonitor\Testing\TesterInterface');
        $this->testers[] = $this->getMock('\Triquanta\AccessibilityMonitor\Testing\TesterInterface');

        $this->sut = new GroupedTester();
        foreach ($this->testers as $tester) {
            $this->sut->addTester($tester);
        }
    }

    /**
     * @covers ::run
     * @covers ::addTester
     */
    public function testRun() {
        $url = new Url();

        foreach ($this->testers as $tester) {
            $tester->expects($this->once())
                ->method('run')
                ->with($url);
        }

        $this->sut->run($url);
    }

}
