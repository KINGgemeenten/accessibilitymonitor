<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\QuailTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor;

use Triquanta\AccessibilityMonitor\Quail;
use Triquanta\AccessibilityMonitor\TestingStatusInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Quail
 */
class QuailTest extends \PHPUnit_Framework_TestCase {

  /**
   * The number of available CPU cores.
   *
   * @var int
   */
  protected $cpuCount;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $logger;

  /**
   * The maximum test execution time.
   *
   * @var int|float
   *   The time limit in seconds.
   */
  protected $maxExecutionTime;

  /**
   * The Quail manager under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Quail
   */
  protected $quail;

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
   * The number of concurrent Quail workers to use.
   *
   * @var int
   */
  protected $workerCount;

  /**
   * The Quail worker factory.
   *
   * @var \Triquanta\AccessibilityMonitor\QuailWorkerFactoryInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $workerFactory;

  /**
   * {@inheritdoc}
   *
   * @covers ::__construct
   */
  public function setUp() {
    $this->logger = $this->getMock('\Psr\Log\LoggerInterface');

    $this->solrClient = $this->getMockBuilder('\Solarium\Core\Client\Client')
      ->disableOriginalConstructor()
      ->getMock();

    $this->storage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

    $this->workerFactory = $this->getMock('\Triquanta\AccessibilityMonitor\QuailWorkerFactoryInterface');

    $this->quail = new Quail($this->storage, $this->solrClient, $this->logger, $this->workerFactory, $this->maxExecutionTime, $this->workerCount, $this->cpuCount);
  }

  /**
   * @covers ::sendCommitToPhantomcoreSolr
   */
  public function testSendCommitToPhantomcoreSolr() {
    $update_query = $this->getMockBuilder('\Solarium\QueryType\Update\Query\Query')
      ->disableOriginalConstructor()
      ->getMock();
    $update_query->expects($this->once())
      ->method('addCommit');

    $this->solrClient->expects($this->once())
      ->method('createUpdate')
      ->willReturn($update_query);
    $this->solrClient->expects($this->once())
      ->method('update')
      ->with($update_query);

    $method = new \ReflectionMethod($this->quail, 'sendCommitToPhantomcoreSolr');
    $method->setAccessible(TRUE);
    $method->invoke($this->quail);
  }

  /**
   * @covers ::getTestingUrls
   */
  public function testGetTestingUrls() {
    $url_a = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Url')
      ->disableOriginalConstructor()
      ->getMock();
    $url_b = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Url')
      ->disableOriginalConstructor()
      ->getMock();
    /** @var \Triquanta\AccessibilityMonitor\Url[]|\PHPUnit_Framework_MockObject_MockObject[] $urls */
    $urls = array($url_a, $url_b);
    foreach ($urls as $url) {
      $url->expects($this->atLeastOnce())
        ->method('setTestingStatus')
        ->with(TestingStatusInterface::STATUS_TESTING);
    }

    $this->storage->expects($this->once())
      ->method('getUrlsByStatus')
      ->with(Url::STATUS_SCHEDULED, $this->workerCount)
      ->willReturn($urls);
    $with = array();
    foreach ($urls as $url) {
      $with[] = array($url);
    }
    $invocation_mocker = $this->storage->expects($this->exactly(count($urls)))
      ->method('saveUrl');
    call_user_func_array(array($invocation_mocker, 'withConsecutive'), $with);

    $method = new \ReflectionMethod($this->quail, 'getTestingUrls');
    $method->setAccessible(TRUE);
    $method->invoke($this->quail);
  }

  /**
   * @covers ::updateWorkerCount
   */
  public function testUpdateWorkerCount() {
    // @todo Find out how to test this reliably.
    $method = new \ReflectionMethod($this->quail, 'updateWorkerCount');
    $method->setAccessible(TRUE);
    $method->invoke($this->quail);
  }

}
