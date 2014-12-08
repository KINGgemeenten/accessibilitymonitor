<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\AddUrlTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\AddUrl;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\AddUrl
 */
class AddUrlTest extends \PHPUnit_Framework_TestCase {

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\AddUrl
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

    $this->command = new AddUrl($this->actions);
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

    $command = AddUrl::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\AddUrl', $command);
  }

  /**
   * @covers ::execute
   */
  public function testExecute() {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $this->actions->expects($this->once())
      ->method('addUrl')
      ->with($this->isInstanceOf('\Triquanta\AccessibilityMonitor\Url'));

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');
    $map = array(
      array('url', 'http://example.com'),
      array('website-test-results-id', mt_rand()),
    );
    $input->expects($this->atLeastOnce())
      ->method('getArgument')
      ->willReturnMap($map);

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

}
