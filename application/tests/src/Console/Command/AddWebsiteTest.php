<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\AddWebsiteTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\AddWebsite;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\AddWebsite
 */
class AddWebsiteTest extends \PHPUnit_Framework_TestCase {

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\AddWebsite
   */
  protected $command;

  /**
   * The actions manager.
   *
   * @var \Triquanta\AccessibilityMonitor\ActionsInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $actions;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->actions = $this->getMock('\Triquanta\AccessibilityMonitor\ActionsInterface');

    $this->command = new AddWebsite($this->actions);
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

    $command = AddWebsite::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\AddWebsite', $command);
  }

  /**
   * @covers ::execute
   */
  public function testExecute() {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $this->actions->expects($this->once())
      ->method('addWebsite')
      ->with($this->isInstanceOf('\Triquanta\AccessibilityMonitor\Website'));

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');
    $input->expects($this->atLeastOnce())
      ->method('getArgument')
      ->willReturn('http://example.com');

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

}
