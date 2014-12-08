<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\ActionsTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor;

use Triquanta\AccessibilityMonitor\Actions;
use Triquanta\AccessibilityMonitor\TestingStatusInterface;
use Triquanta\AccessibilityMonitor\Url;
use Triquanta\AccessibilityMonitor\WebsiteTestResults;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Actions
 */
class ActionsTest extends \PHPUnit_Framework_TestCase {

  /**
   * The class under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Actions
   */
  protected $actions;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $logger;

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
    $this->logger = $this->getMock('\Psr\Log\LoggerInterface');

    $this->storage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

    $this->actions = new Actions($this->storage, $this->logger);
  }

  /**
   * @covers ::addUrl
   */
  public function testAddUrlWithoutWebsiteId() {
    $url = new Url();

    $this->logger->expects($this->atLeastOnce())
      ->method('warning');

    $this->storage->expects($this->never())
      ->method('saveUrl');

    $this->actions->addUrl($url);
  }

  /**
   * @covers ::addUrl
   */
  public function testAddUrlWithExistingUrl() {
    $website_test_results_id = mt_rand();
    $url_url = 'http://example.com/foo/bar';

    $url = new Url();
    $url->setWebsiteTestResultsId($website_test_results_id)
      ->setUrl($url_url);

    $this->logger->expects($this->atLeastOnce())
      ->method('warning');

    $this->storage->expects($this->atLeastOnce())
      ->method('getUrlByUrlAndWebsiteTestResultsId')
      ->with($url_url, $website_test_results_id)
      ->willReturn($url);
    $this->storage->expects($this->never())
      ->method('saveUrl');

    $this->actions->addUrl($url);
  }

  /**
   * @covers ::addUrl
   */
  public function testAddUrl() {
    $website_test_results_id = mt_rand();
    $url_url = 'http://example.com/foo/bar';

    $url = new Url();
    $url->setWebsiteTestResultsId($website_test_results_id)
      ->setUrl($url_url);

    $this->storage->expects($this->atLeastOnce())
      ->method('getUrlByUrlAndWebsiteTestResultsId')
      ->with($url_url, $website_test_results_id)
      ->willReturn(NULL);
    $this->storage->expects($this->once())
      ->method('saveUrl')
      ->with($url);

    $this->actions->addUrl($url);
  }

}
