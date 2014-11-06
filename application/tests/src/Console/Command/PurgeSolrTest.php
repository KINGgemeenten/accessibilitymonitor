<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\PurgeSolrTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\PurgeSolr;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\PurgeSolr
 */
class PurgeSolrTest extends \PHPUnit_Framework_TestCase {

  /**
   * The command under test.
   *
   * @var \Triquanta\AccessibilityMonitor\Console\Command\PurgeSolr
   */
  protected $command;

  /**
   * The Solr client.
   *
   * @var \Solarium\Core\Client\Client|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $solrClient;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->solrClient = $this->getMockBuilder('\Solarium\Core\Client\Client')
      ->disableOriginalConstructor()
      ->getMock();

    $this->command = new PurgeSolr($this->solrClient);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   * @covers ::configure
   */
  public function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('solr.client.phantom', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->solrClient),
    );
    $container->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap($map);

    $command = PurgeSolr::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\PurgeSolr', $command);
  }

  /**
   * @covers ::execute
   */
  public function testExecute() {
    $method = new \ReflectionMethod($this->command, 'execute');
    $method->setAccessible(TRUE);

    $update_query = $this->getMockBuilder('\Solarium\QueryType\Update\Query\Query')
      ->disableOriginalConstructor()
      ->getMock();
    $update_query->expects($this->once())
      ->method('addDeleteQuery')
      ->with('*:*');
    $update_query->expects($this->once())
      ->method('addCommit');

    $this->solrClient->expects($this->once())
      ->method('createUpdate')
      ->willReturn($update_query);
    $this->solrClient->expects($this->once())
      ->method('update')
      ->with($update_query);

    $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');

    $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

    $method->invoke($this->command, $input, $output);
  }

}
