<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\StartWorkerTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\StartWorker;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\StartWorker
 */
class StartWorkerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\Console\Command\StartWorker
     */
    protected $sut;

    /**
     * The worker.
     *
     * @var \Triquanta\AccessibilityMonitor\WorkerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $worker;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->worker = $this->getMock('\Triquanta\AccessibilityMonitor\WorkerInterface');

        $this->sut = new StartWorker($this->worker);
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
          [
            'worker',
            ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE,
            $this->worker
          ],
        ];
        $container->expects($this->atLeastOnce())
          ->method('get')
          ->willReturnMap($map);

        $command = StartWorker::create($container);
        $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\StartWorker',
          $command);
    }

    /**
     * @covers ::execute
     */
    public function testExecute()
    {
        $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');

        $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

        $this->worker->expects($this->once())
          ->method('registerWorker');


        $this->sut->run($input, $output);
    }

}
