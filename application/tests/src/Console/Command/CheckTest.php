<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\CheckTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\Check;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\Check
 */
class CheckTest extends \PHPUnit_Framework_TestCase {

  /**
   * The number of items in the queue that should trigger an alert log item.
   *
   * @var int
   */
  protected $alertThreshold;

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\Check
   */
  protected $command;

  /**
   * The number of items in the queue that should trigger an error log item.
   *
   * @var int
   */
  protected $errorThreshold;

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
   * The process manager.
   *
   * @var \Triquanta\AccessibilityMonitor\ProcessInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $processManager;

  /**
   * The Quail manager.
   *
   * @var \Triquanta\AccessibilityMonitor\QuailInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $quail;

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
    $this->alertThreshold = mt_rand();

    $this->errorThreshold = mt_rand();

    $this->logger = $this->getMock('\Psr\Log\LoggerInterface');

    $this->noticeThreshold = mt_rand();

    $this->processManager = $this->getMock('\Triquanta\AccessibilityMonitor\ProcessInterface');

    $this->quail = $this->getMock('\Triquanta\AccessibilityMonitor\QuailInterface');

    $this->storage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

    $this->command = new Check($this->processManager, $this->storage, $this->quail, $this->logger, $this->noticeThreshold, $this->errorThreshold, $this->alertThreshold);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   * @covers ::configure
   */
  public function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('logger', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->logger),
      array('process', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->processManager),
      array('quail', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->quail),
      array('storage', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->storage),
    );
    $container->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap($map);
    $map = array(
      array('queue.threshold.alert', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->alertThreshold),
      array('queue.threshold.error', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->errorThreshold),
      array('queue.threshold.notice', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->noticeThreshold),
    );
    $container->expects($this->atLeastOnce())
      ->method('getParameter')
      ->willReturnMap($map);

    $command = Check::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\Check', $command);
  }

  /**
   * @covers ::execute
   *
   * @dataProvider providerTestExecute
   */
  public function testExecute($is_another_process_registered, $last_analysis_timestamp, $kill_other_process, $test) {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $this->processManager->expects($this->once())
      ->method('isAnotherProcessRegistered')
      ->willReturn($is_another_process_registered);
    $this->processManager->expects($kill_other_process ? $this->once() : $this->never())
      ->method('killOtherProcess')
      ->willReturn($kill_other_process);

    $this->storage->expects($is_another_process_registered ? $this->once() : $this->never())
      ->method('getUrlLastAnalysisDateTime')
      ->willReturn($last_analysis_timestamp);

    $this->quail->expects($test ? $this->once() : $this->never())
      ->method('test');

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

  /**
   * Provides data to self::testExecute().
   */
  public function providerTestExecute() {
    return array(
      array(TRUE, time() - (Check::MAX_RUN_TIME / 2), FALSE, FALSE),
      array(TRUE, time() - (Check::MAX_RUN_TIME * 2), TRUE, TRUE),
      array(FALSE, mt_rand(), FALSE, TRUE),
    );
  }

}
