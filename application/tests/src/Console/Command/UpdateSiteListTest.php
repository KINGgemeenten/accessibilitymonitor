<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\UpdateSiteListTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\UpdateSiteList;
use Triquanta\AccessibilityMonitor\Url;
use Triquanta\AccessibilityMonitor\Website;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\UpdateSiteList
 */
class UpdateSiteListTest extends \PHPUnit_Framework_TestCase {

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\UpdateSiteList
   */
  protected $command;

  /**
   * The storage manager.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $storage;

  /**
   * The Solr client.
   *
   * @var \Solarium\Core\Client\Client|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $solrClient;

  /**
   * The number of URLs to process per batch.
   *
   * @var int
   */
  protected $urlsPerSample;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->storage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

    $this->solrClient = $this->getMockBuilder('\Solarium\Core\Client\Client')
      ->setMethods(array('select'))
      ->getMock();

    $this->urlsPerSample = mt_rand();

    $this->command = new UpdateSiteList($this->storage, $this->solrClient, $this->urlsPerSample);
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
      array('solr.client.nutch', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->solrClient),
    );
    $container->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap($map);
    $container->expects($this->atLeastOnce())
      ->method('getParameter')
      ->with('urls_per_sample')
      ->willReturn($this->urlsPerSample);

    $command = UpdateSiteList::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\UpdateSiteList', $command);
  }

  /**
   * @covers ::execute
   */
  public function testExecute() {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $document = (object) array(
      'url' => 'http://example.com',
    );

    $document_exists = FALSE;

    $this->solrClient->expects($this->once())
      ->method('select')
      ->with($this->isInstanceOf('\Solarium\QueryType\Select\Query\Query'))
      ->willReturn(array($document));

    $website = (new Website())->setId(mt_rand())
      ->setUrl('http://example.com')
      ->setTestingStatus(mt_rand())
      ->setLastAnalysis(mt_rand());

    $number_of_urls = mt_rand(5, 9);
    $number_of_urls_scheduled = 0;

    $this->storage->expects($this->once())
      ->method('getWebsitesByStatuses')
      ->with(array(Url::STATUS_SCHEDULED, Url::STATUS_TESTED))
      ->willReturn(array($website));
    $this->storage->expects($this->once())
      ->method('countUrlsByStatusAndWebsiteId')
      ->with(Url::STATUS_SCHEDULED, $website->getId())
      ->willReturn($number_of_urls_scheduled);
    $this->storage->expects($this->once())
      ->method('countUrlsByWebsiteId')
      ->with($website->getId())
      ->willReturn($number_of_urls);
    $this->storage->expects($this->once())
      ->method('countUrlsByWebsiteIdAndFullUrl')
      ->with($website->getId(), $document->url)
      ->willReturn((int) $document_exists);
    $this->storage->expects($this->once())
      ->method('saveUrl')
      ->with($this->isInstanceOf('\Triquanta\AccessibilityMonitor\Url'));

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

}
