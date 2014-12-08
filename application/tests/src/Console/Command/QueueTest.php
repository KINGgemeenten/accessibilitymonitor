<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\QueueTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Action;
use Triquanta\AccessibilityMonitor\Console\Command\Queue;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\Queue
 */
class QueueTest extends \PHPUnit_Framework_TestCase {

  /**
   * The actions manager.
   *
   * @var \Triquanta\AccessibilityMonitor\ActionsInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $actions;

  /**
   * The number of items in the queue that should trigger an alert log item.
   *
   * @var int
   */
  protected $alertThreshold;

  /**
   * The number of items in the queue that should trigger an error log item.
   *
   * @var int
   */
  protected $errorThreshold;

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\Queue
   */
  protected $command;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $logger;

  /**
   * The number of items in the queue that should trigger a notice log item.
   *
   * @var int
   */
  protected $noticeThreshold;

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

    $this->alertThreshold = mt_rand();

    $this->errorThreshold = mt_rand();

    $this->logger = $this->getMock('\Psr\Log\LoggerInterface');

    $this->noticeThreshold = mt_rand();

    $this->storage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

    $this->command = new Queue($this->actions, $this->storage, $this->logger, $this->noticeThreshold, $this->errorThreshold, $this->alertThreshold);
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
      array('logger', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->logger),
      array('storage', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->storage),
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
  public function testExecuteWithoutPendingActions() {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $this->storage->expects($this->once())
      ->method('getPendingActions')
      ->willReturn(array());
    $this->storage->expects($this->never())
      ->method('saveAction');

    $this->actions->expects($this->never())
      ->method('addUrl');

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

  /**
   * @covers ::execute
   */
  public function testExecuteAddUrl() {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $url = 'http://example.com/' . mt_rand();

    $action = new Action();
    $action->setAction($action::ACTION_ADD_URL)
      ->setUrl($url);

    $this->storage->expects($this->once())
      ->method('getPendingActions')
      ->willReturn(array($action));

    $this->actions->expects($this->once())
      ->method('addUrl')
      ->with($this->isInstanceOf('\Triquanta\AccessibilityMonitor\Url'));

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

}
