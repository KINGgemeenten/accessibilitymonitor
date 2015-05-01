<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\ReTestTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\ReTest;
use Triquanta\AccessibilityMonitor\Url;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\ReTest
 */
class ReTestTest extends \PHPUnit_Framework_TestCase
{

    /**
     * The re-tester.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\ReTester|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $reTester;

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\Console\Command\ReTest
     */
    protected $sut;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->reTester = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Testing\ReTester')
          ->disableOriginalConstructor()
          ->getMock();

        $this->sut = new ReTest($this->reTester);
    }

    /**
     * @covers ::create
     * @covers ::__construct
     * @covers ::configure
     */
    public function testCreate()
    {
        $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
        $map = [
          ['testing.retester', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->reTester],
        ];
        $container->expects($this->atLeastOnce())
          ->method('get')
          ->willReturnMap($map);

        $command = ReTest::create($container);
        $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\ReTest',
          $command);
    }

    /**
     * @covers ::execute
     */
    public function testExecute()
    {
        $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');
        $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

        $this->reTester->expects($this->once())
          ->method('retest');


        $this->sut->run($input, $output);
    }

}
