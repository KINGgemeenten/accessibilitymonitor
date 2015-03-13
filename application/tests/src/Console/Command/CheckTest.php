<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\Console\Command\CheckTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor\Console\Command;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Triquanta\AccessibilityMonitor\Console\Command\Check;
use Triquanta\AccessibilityMonitor\Url;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Console\Command\Check
 */
class CheckTest extends \PHPUnit_Framework_TestCase {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $logger;

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\Console\Command\Check
     */
    protected $sut;

    /**
     * The tester.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $tester;

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    $this->logger = $this->getMock('\Psr\Log\LoggerInterface');

      $this->tester = $this->getMock('\Triquanta\AccessibilityMonitor\Testing\TesterInterface');

    $this->sut = new Check($this->logger, $this->tester);
  }

  /**
   * @covers ::create
   * @covers ::__construct
   * @covers ::configure
   */
  public function testCreate() {
    $container = $this->getMock('\Symfony\Component\DependencyInjection\ContainerInterface');
    $map = array(
      array('logger', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->logger),
      array('testing.tester.grouped', ContainerInterface::EXCEPTION_ON_INVALID_REFERENCE, $this->tester),
    );
    $container->expects($this->atLeastOnce())
      ->method('get')
      ->willReturnMap($map);

    $command = Check::create($container);
    $this->assertInstanceOf('\Triquanta\AccessibilityMonitor\Console\Command\Check', $command);
  }

    /**
     * @covers ::execute
     */
    public function testExecute() {
        $urlString = 'http://example.com/' . mt_rand();

        $input = $this->getMock('\Symfony\Component\Console\Input\InputInterface');
        $input->expects($this->atLeastOnce())
            ->method('getArgument')
            ->with('url')
            ->willReturn($urlString);
        $output = $this->getMock('\Symfony\Component\Console\Output\OutputInterface');

        $this->tester->expects($this->once())
            ->method('run')
            ->with(new \PHPUnit_Framework_Constraint_Callback(function (Url $url) use ($urlString) {
                return $url->getUrl() === $urlString;
            }));


        $this->sut->run($input, $output);
    }

}
