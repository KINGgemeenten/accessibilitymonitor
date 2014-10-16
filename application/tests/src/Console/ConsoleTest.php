<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\ConsoleTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Console;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Console
 */
class ConsoleTest extends \PHPUnit_Framework_TestCase {

  /**
   * The command discovery.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\CommandDiscoveryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $commandDiscovery;

  /**
   * The console under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Console
   */
  protected $console;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected $eventDispatcher;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->commandDiscovery = $this->getMock('\Triquanta\AccessibilityMonitor\Console\CommandDiscoveryInterface');
    $this->commandDiscovery->expects($this->atLeastOnce())
      ->method('getCommands')
      ->willReturn(array(
        '\Triquanta\Tests\AccessibilityMonitor\Console\Command\DummyCommand',
        '\Triquanta\Tests\AccessibilityMonitor\Console\Command\ContainerFactoryDummyCommand',
      ));

    $this->eventDispatcher = $this->getMock('\Symfony\Component\EventDispatcher\EventDispatcherInterface');

    $phantom_js = $this->getMock('\Triquanta\AccessibilityMonitor\PhantomJsInterface');

    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('service_container', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $container),
    );
    $container->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap($map);

    $this->command = new Console($container, $this->commandDiscovery, $this->eventDispatcher, $phantom_js);
  }

  /**
   * @covers ::__construct
   */
  public function testConstruct() {
    // @todo This is a ridiculous temporary workaround.
    $this->assertTrue(TRUE);
  }

}
