<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\ExcludeUrlTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\ExcludeUrl;
use Triquanta\AccessibilityMonitor\Url;
use Triquanta\AccessibilityMonitor\Website;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\ExcludeUrl
 */
class ExcludeUrlTest extends \PHPUnit_Framework_TestCase {

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\ExcludeUrl
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

    $this->command = new ExcludeUrl($this->actions, $this->storage);
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

    $command = ExcludeUrl::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\ExcludeUrl', $command);
  }

  /**
   * @covers ::execute
   *
   * @dataProvider providerTestExecute
   */
  public function testExecute($id, Url $url = NULL) {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $this->actions->expects(is_null($url) ? $this->never() : $this->once())
      ->method('excludeUrl')
      ->with($url);

    $this->storage->expects($this->once())
      ->method(is_numeric($id) ? 'getUrlById' : 'getUrlByUrl')
      ->with($id)
      ->willReturn($url);

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
    if (is_null($url) && !$exception) {
      // If no URL was supposed be loaded, an exception must have been thrown.
      $this->fail();
    }
  }

  /**
   * Provides data to self::testExecute().
   */
  public function providerTestExecute() {
    return array(
      array(543, NULL),
      array(543, new Url()),
      array('http://example.com/' . mt_rand(), NULL),
      array('http://example.com/' . mt_rand(), new Url()),
    );
  }

}
