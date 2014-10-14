<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\GooglePagespeedTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor;

use Triquanta\AccessibilityMonitor\GooglePagespeed;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\GooglePagespeed
 */
class GooglePagespeedTest extends \PHPUnit_Framework_TestCase {

  /**
   * The API fetch limit.
   *
   * @var string
   */
  protected $apiFetchLimit;

  /**
   * The API key.
   *
   * @var string
   */
  protected $apiKey;

  /**
   * The API strategy.
   *
   * @var string
   */
  protected $apiStrategy;

  /**
   * The API URL.
   *
   * @var string
   */
  protected $apiUrl;

  /**
   * The class under test.
   *
   * @var \Triquanta\AccessibilityMonitor\GooglePagespeed
   */
  protected $command;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $httpClient;

  /**
   * The storage manager.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $storage;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setUp() {
    $this->httpClient = $this->getMock('\GuzzleHttp\ClientInterface');

    $this->command = new GooglePagespeed($this->httpClient, $this->apiUrl, $this->apiKey, $this->apiStrategy, $this->apiFetchLimit);
  }

  /**
   * @covers ::test
   */
  public function testTest() {
    $method = new \ReflectionMethod($this->command, 'test');
    $method->setAccessible(TRUE);

    $query = $this->getMockBuilder('\GuzzleHttp\Query')
      ->disableOriginalConstructor()
      ->getMock();

    $request = $this->getMock('\GuzzleHttp\Message\RequestInterface');
    $request->expects($this->atLeastOnce())
      ->method('getQuery')
      ->willReturn($query);

    $response_data = (object) array(
      'score' => mt_rand(),
    );

    $response = $this->getMock('\GuzzleHttp\Message\ResponseInterface');
    $response->expects($this->atLeastOnce())
      ->method('getBody')
      ->willReturn(json_encode($response_data));

    $this->httpClient->expects($this->once())
      ->method('createRequest')
      ->with('GET', $this->apiUrl)
      ->willReturn($request);
    $this->httpClient->expects($this->once())
      ->method('send')
      ->with($request)
      ->willReturn($response);

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

}
