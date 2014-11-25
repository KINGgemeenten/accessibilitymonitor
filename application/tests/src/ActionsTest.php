<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\ActionsTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor;

use Triquanta\AccessibilityMonitor\Actions;
use Triquanta\AccessibilityMonitor\TestingStatusInterface;
use Triquanta\AccessibilityMonitor\Url;
use Triquanta\AccessibilityMonitor\Website;

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
   * The Solr client.
   *
   * @var \Solarium\Core\Client\Client|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $solrClient;

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

    $this->solrClient = $this->getMockBuilder('\Solarium\Client')
      ->disableOriginalConstructor()
      ->getMock();

    $this->storage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

    $this->actions = new Actions($this->storage, $this->solrClient, $this->logger);
  }

  /**
   * @covers ::addWebsite
   */
  public function testAddWebsite() {
    $website = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Website')
      ->disableOriginalConstructor()
      ->getMock();
    $website->expects($this->atLeastOnce())
      ->method('setTestingStatus')
      ->with(TestingStatusInterface::STATUS_SCHEDULED);

    $this->storage->expects($this->once())
      ->method('saveWebsite')
      ->with($website);

    $this->actions->addWebsite($website);
  }

  /**
   * @covers ::rescanWebsite
   */
  public function testRescanWebsite() {
    $website_id = mt_rand();

    $website = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Website')
      ->disableOriginalConstructor()
      ->getMock();
    $website->expects($this->atLeastOnce())
      ->method('getId')
      ->willReturn($website_id);
    $website->expects($this->atLeastOnce())
      ->method('setTestingStatus')
      ->with(TestingStatusInterface::STATUS_SCHEDULED);

    $url_testing_status_a = Url::STATUS_SCHEDULED;
    $url_entity_a = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Url')
      ->disableOriginalConstructor()
      ->getMock();
    $url_entity_a->expects($this->atLeastOnce())
      ->method('getTestingStatus')
      ->willReturn($url_testing_status_a);
    $url_testing_status_b = Url::STATUS_TESTED;
    $url_entity_b = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Url')
      ->disableOriginalConstructor()
      ->getMock();
    $url_entity_b->expects($this->atLeastOnce())
      ->method('getTestingStatus')
      ->willReturn($url_testing_status_b);
    $url_entity_b->expects($this->atLeastOnce())
      ->method('setTestingStatus')
      ->with(TestingStatusInterface::STATUS_SCHEDULED);
    /** @var \Triquanta\AccessibilityMonitor\Url[]|\PHPUnit_Framework_MockObject_MockObject[] $url_entities */
    $url_entities = array($url_entity_a, $url_entity_b);

    $this->storage->expects($this->atLeastOnce())
      ->method('getUrlsByWebsiteId')
      ->with($website_id)
      ->willReturn($url_entities);
    $this->storage->expects($this->once())
      ->method('saveWebsite')
      ->with($website);
    $with = array();
    foreach ($url_entities as $url_entity) {
      $with[] = array($url_entity);
    }
    $invocation_mocker = $this->storage->expects($this->once())
      ->method('saveUrl');
    call_user_func_array(array($invocation_mocker, 'withConsecutive'), $with);

    $this->actions->rescanWebsite($website);
  }

  /**
   * @covers ::excludeWebsite
   */
  public function testExcludeWebsiteWithNonExistingWebsite() {
    $website = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Website')
      ->disableOriginalConstructor()
      ->getMock();
    $website->expects($this->atLeastOnce())
      ->method('setTestingStatus')
      ->with(TestingStatusInterface::STATUS_EXCLUDED);

    $this->storage->expects($this->once())
      ->method('saveWebsite')
      ->with($website);

    $this->actions->excludeWebsite($website);
  }

  /**
   * @covers ::excludeWebsite
   */
  public function testExcludeWebsiteWithExistingWebsite() {
    $website_id = mt_rand();

    $website = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Website')
      ->disableOriginalConstructor()
      ->getMock();
    $website->expects($this->atLeastOnce())
      ->method('getId')
      ->willReturn($website_id);
    $website->expects($this->atLeastOnce())
      ->method('setTestingStatus')
      ->with(TestingStatusInterface::STATUS_EXCLUDED);

    $url_entity_a = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Url')
      ->disableOriginalConstructor()
      ->getMock();
    $url_entity_b = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Url')
      ->disableOriginalConstructor()
      ->getMock();
    /** @var \Triquanta\AccessibilityMonitor\Url[]|\PHPUnit_Framework_MockObject_MockObject[] $url_entities */
    $url_entities = array($url_entity_a, $url_entity_b);
    foreach ($url_entities as $url_entity) {
      $url_entity->expects($this->atLeastOnce())
        ->method('setTestingStatus')
        ->with(TestingStatusInterface::STATUS_EXCLUDED);
    }

    $with = array();
    foreach ($url_entities as $url_entity) {
      $with[] = array($url_entity);
    }
    $invocation_mocker = $this->storage->expects($this->exactly(count($url_entities)))
      ->method('saveUrl');
    call_user_func_array(array($invocation_mocker, 'withConsecutive'), $with);
    $this->storage->expects($this->once())
      ->method('getUrlsByWebsiteId')
      ->with($website_id)
      ->willReturn($url_entities);
    $this->storage->expects($this->once())
      ->method('saveWebsite')
      ->with($website);

    $update_query = $this->getMockBuilder('\Solarium\QueryType\Update\Query\Query')
      ->disableOriginalConstructor()
      ->getMock();

    $this->solrClient->expects($this->atLeastOnce())
      ->method('createUpdate')
      ->willReturn($update_query);

    $this->actions->excludeWebsite($website);
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
  public function testAddUrlWithNonExistingWebsiteId() {
    $website_id = mt_rand();

    $url = new Url();
    $url->setWebsiteId($website_id);

    $this->logger->expects($this->atLeastOnce())
      ->method('warning');

    $this->storage->expects($this->once())
      ->method('getWebsiteById')
      ->willReturn(NULL);
    $this->storage->expects($this->never())
      ->method('saveUrl');

    $this->actions->addUrl($url);
  }

  /**
   * @covers ::addUrl
   */
  public function testAddUrlWithNonMatchingWebsiteUrl() {
    $website_id = mt_rand();
    $website_url = 'http://example.com/foo';
    $url_url = 'http://example.com/bar';

    $website = new Website();
    $website->setUrl($website_url);

    $url = new Url();
    $url->setWebsiteId($website_id)
      ->setUrl($url_url);

    $this->logger->expects($this->atLeastOnce())
      ->method('warning');

    $this->storage->expects($this->once())
      ->method('getWebsiteById')
      ->willReturn($website);
    $this->storage->expects($this->never())
      ->method('saveUrl');

    $this->actions->addUrl($url);
  }

  /**
   * @covers ::addUrl
   */
  public function testAddUrlWithExistingUrl() {
    $website_id = mt_rand();
    $website_url = 'http://example.com/foo';
    $url_url = 'http://example.com/foo/bar';

    $website = new Website();
    $website->setUrl($website_url);

    $url = new Url();
    $url->setWebsiteId($website_id)
      ->setUrl($url_url);

    $this->logger->expects($this->atLeastOnce())
      ->method('warning');

    $this->storage->expects($this->once())
      ->method('getWebsiteById')
      ->willReturn($website);
    $this->storage->expects($this->atLeastOnce())
      ->method('getUrlByUrl')
      ->with($url_url)
      ->willReturn($url);
    $this->storage->expects($this->never())
      ->method('saveUrl');

    $this->actions->addUrl($url);
  }

  /**
   * @covers ::addUrl
   */
  public function testAddUrl() {
    $website_id = mt_rand();
    $website_url = 'http://example.com/foo';
    $url_url = 'http://example.com/foo/bar';

    $website = new Website();
    $website->setUrl($website_url);

    $url = new Url();
    $url->setWebsiteId($website_id)
      ->setUrl($url_url);

    $this->storage->expects($this->once())
      ->method('getWebsiteById')
      ->willReturn($website);
    $this->storage->expects($this->atLeastOnce())
      ->method('getUrlByUrl')
      ->with($url_url)
      ->willReturn(NULL);
    $this->storage->expects($this->once())
      ->method('saveUrl')
      ->with($url);

    $this->actions->addUrl($url);
  }

  /**
   * @covers ::excludeUrl
   *
   * @dataProvider providerTestExcludeUrl
   */
  public function testExcludeUrl(Url $actual_url = NULL) {
    $url_url = 'http://example.com/foo/bar';

    $url = new Url();
    $url->setUrl($url_url);

    $this->storage->expects($this->atLeastOnce())
      ->method('getUrlByUrl')
      ->with($url_url)
      ->willReturn($actual_url);
    $this->storage->expects($this->once())
      ->method('saveUrl')
      ->with(is_null($actual_url) ? $url: $actual_url);

    $this->actions->excludeUrl($url);
  }

  /**
   * Provides data to self::testExcludeUrl().
   */
  public function providerTestExcludeUrl() {
    return array(
      array(NULL),
      array(new Url()),
    );
  }

  /**
   * @covers ::deleteWebsiteResults
   */
  public function testDeleteWebsiteResults() {
    $url = 'http://example.com/' . mt_rand();

    $website = new Website();
    $website->setUrl($url);

    $update_query = $this->getMockBuilder('\Solarium\QueryType\Update\Query\Query')
      ->disableOriginalConstructor()
      ->getMock();
    $update_query->expects($this->once())
      ->method('addDeleteQuery')
      ->with('url_sub:' . $url);

    $this->solrClient->expects($this->atLeastOnce())
      ->method('createUpdate')
      ->willReturn($update_query);
    $this->solrClient->expects($this->atLeastOnce())
      ->method('update')
      ->with($update_query);

    $this->actions->deleteWebsiteResults($website);
  }

}
