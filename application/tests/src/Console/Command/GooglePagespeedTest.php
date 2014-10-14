<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\GooglePagespeedTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\GooglePagespeed;
use Triquanta\AccessibilityMonitor\Url;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\GooglePagespeed
 */
class GooglePagespeedTest extends \PHPUnit_Framework_TestCase {

  /**
   * The API fetch limit.
   *
   * @var string
   */
  protected $apiFetchLimit;

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\GooglePagespeed
   */
  protected $command;

  /**
   * The Google Pagespeed tester.
   *
   * @var \Triquanta\AccessibilityMonitor\GooglePagespeedInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $googlePagespeed;

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
    $this->apiFetchLimit = mt_rand();

    $this->googlePagespeed = $this->getMock('\Triquanta\AccessibilityMonitor\GooglePagespeedInterface');

    $this->storage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

    $this->command = new GooglePagespeed($this->storage, $this->googlePagespeed, $this->apiFetchLimit);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   * @covers ::configure
   */
  public function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('google_pagespeed', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->googlePagespeed),
      array('storage', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->storage),
    );
    $container->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap($map);
    $map = array(
      array('google_pagespeed.api_fetch_limit', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->apiFetchLimit),
    );
    $container->expects($this->atLeastOnce())
      ->method('getParameter')
      ->willReturnMap($map);

    $command = GooglePagespeed::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\GooglePagespeed', $command);
  }

  /**
   * @covers ::execute
   *
   * @dataProvider providerTestExecute
   */
  public function testExecute($http_response_code) {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $response_data = (object) array(
      'responseCode' => $http_response_code,
      'score' => mt_rand(),
    );

    $url_url = 'http://example.com/foo/bar';
    $url = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Url')
      ->disableOriginalConstructor()
      ->getMock();
    $url->expects($http_response_code === 200 ? $this->once() : $this->never())
      ->method('setGooglePagespeedResult')
      ->with($response_data->score);
    $url->expects($this->atLeastOnce())
      ->method('getUrl')
      ->willReturn($url_url);

    $this->storage->expects($this->once())
      ->method('getUrlsWithoutGooglePagespeedScore')
      ->willReturn(array($url));

    $this->googlePagespeed->expects($this->once())
      ->method('test')
      ->with($url_url)
      ->willReturn($response_data);

    $this->storage->expects($http_response_code === 200 ? $this->once() : $this->never())
      ->method('saveUrl')
      ->with($url);

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

  /**
   * Provides data to self::testExecute().
   */
  public function providerTestExecute() {
    return array(
      array(200),
      array(404),
      array(403),
      array(mt_rand()),
    );
  }

}
