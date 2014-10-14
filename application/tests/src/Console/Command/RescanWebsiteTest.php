<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\RescanWebsiteTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\RescanWebsite;
use Triquanta\AccessibilityMonitor\Website;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\RescanWebsite
 */
class RescanWebsiteTest extends \PHPUnit_Framework_TestCase {

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\RescanWebsite
   */
  protected $command;

  /**
   * The actions manager.
   *
   * @var \Triquanta\AccessibilityMonitor\ActionsInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $actions;

  /**
   * The storage.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $storage;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->actions = $this->getMock('\Triquanta\AccessibilityMonitor\ActionsInterface');

    $this->storage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

    $this->command = new RescanWebsite($this->actions, $this->storage);
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
      array('storage', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->storage),
    );
    $container->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap($map);

    $command = RescanWebsite::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\RescanWebsite', $command);
  }

  /**
   * @covers ::execute
   *
   * @dataProvider providerTestExecute
   */
  public function testExecute($id, Website $website = NULL) {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $this->actions->expects(is_null($website) ? $this->never() : $this->once())
      ->method('rescanWebsite')
      ->with($website);

    $this->storage->expects($this->once())
      ->method(is_numeric($id) ? 'getWebsiteById' : 'getWebsiteByUrl')
      ->with($id)
      ->willReturn($website);

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');
    $input->expects($this->once())
      ->method('getArgument')
      ->with('id')
      ->willReturn($id);

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    try {
      $method->invoke($this->command, $input, $output);
      $exception = FALSE;
    }
    catch (\InvalidArgumentException $e) {
      $exception = TRUE;
    }
    if (is_null($website) && !$exception) {
      // If no website was supposed be loaded, an exception must have been
      // thrown.
      $this->fail();
    }
  }

  /**
   * Provides data to self::testExecute().
   */
  public function providerTestExecute() {
    return array(
      array(543, NULL),
      array(543, new Website()),
      array('http://example.com/' . mt_rand(), NULL),
      array('http://example.com/' . mt_rand(), new Website()),
    );
  }

}
