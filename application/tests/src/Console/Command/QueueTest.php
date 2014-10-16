<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\QueueTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\Queue;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\Queue
 */
class QueueTest extends \PHPUnit_Framework_TestCase {

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\Queue
   */
  protected $command;

  /**
   * The actions manager.
   *
   * @var \Triquanta\AccessibilityMonitor\ActionsInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $actions;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $logger;

  /**
   * The storage manager.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->actions = $this->getMock('\Triquanta\AccessibilityMonitor\ActionsInterface');

    $this->logger = $this->getMock('\Psr\Log\LoggerInterface');

    $this->storage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

    $this->command = new Queue($this->actions, $this->storage, $this->logger);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   * @covers ::configure
   */
  public function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('actions', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->actions),
    );
    $container->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap($map);

    $command = Queue::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\Queue', $command);
  }

  /**
   * @covers ::execute
   */
  public function testExecute() {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $this->actions->expects($this->once())
      ->method('Queue')
      ->with($this->isInstanceOf('\Triquanta\AccessibilityMonitor\Url'));

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');
    $map = array(
      array('url', 'http://example.com'),
      array('website-id', mt_rand()),
    );
    $input->expects($this->atLeastOnce())
      ->method('getArgument')
      ->willReturnMap($map);

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

}
