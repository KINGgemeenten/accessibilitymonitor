<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\DetectCmsTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\DetectCms;
use Triquanta\AccessibilityMonitor\Url;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\DetectCms
 */
class DetectCmsTest extends \PHPUnit_Framework_TestCase {

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\DetectCms
   */
  protected $command;

  /**
   * The storage manager.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $storage;

  /**
   * The Phantom JS manager.
   *
   * @var \Triquanta\AccessibilityMonitor\PhantomJsInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $phantomJs;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->storage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

    $this->phantomJs = $this->getMock('\Triquanta\AccessibilityMonitor\PhantomJsInterface');

    $this->command = new DetectCms($this->storage, $this->phantomJs);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   * @covers ::configure
   */
  public function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('storage', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->storage),
      array('phantomjs', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->phantomJs),
    );
    $container->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap($map);

    $command = DetectCms::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\DetectCms', $command);
  }

  /**
   * @covers ::execute
   */
  public function testExecute() {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $detected_apps = array('baz', 'qux');

    /** @var \Triquanta\AccessibilityMonitor\Url|\PHPUnit_Framework_MockObject_MockObject $url */
    $url = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Url')
      ->disableOriginalConstructor()
      ->getMock();
    $url->expects($this->once())
      ->method('setCms')
      ->with(implode('|', $detected_apps));

    $this->phantomJs->expects($this->once())
      ->method('getDetectedApps')
      ->with($url->getUrl())
      ->willReturn($detected_apps);

    $this->storage->expects($this->once())
      ->method('getUrlsByNotStatus')
      ->with(Url::STATUS_EXCLUDED)
      ->willReturn(array($url));
    $this->storage->expects($this->once())
      ->method('saveUrl')
      ->with($url);

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

}
