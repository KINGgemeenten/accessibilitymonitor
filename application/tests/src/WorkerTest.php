<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\WorkerTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor;

use PhpAmqpLib\Message\AMQPMessage;
use Triquanta\AccessibilityMonitor\Url;
use Triquanta\AccessibilityMonitor\Worker;

/**
 * @coversDefaultClass \Triquanta\AccessibilityMonitor\Worker
 */
class WorkerTest extends \PHPUnit_Framework_TestCase
{

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $logger;

    /**
     * The maximum number of failed test runs per URL.
     *
     * @var int
     */
    protected $maxFailedTestRunCount;

    /**
     * The maximum number of failed test runs per time period.
     *
     * @var int
     *   A period in seconds.
     */
    protected $maxFailedTestRunPeriod;

    /**
     * The queue.
     *
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $queue;

    /**
     * The name of the queue.
     *
     * @var string
     */
    protected $queueName;

    /**
     * The result storage.
     *
     * @var \Triquanta\AccessibilityMonitor\StorageInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $resultStorage;

    /**
     * The tester.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $tester;

    /**
     * The worker's TTL in seconds.
     *
     * @var int
     */
    protected $ttl;

    /**
     * The class under test.
     *
     * @var \Triquanta\AccessibilityMonitor\Worker|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $sut;

    /**
     * {@inheritdoc}
     */
    public function setUp()
    {
        $this->logger = $this->getMock('\Psr\Log\LoggerInterface');

        $this->maxFailedTestRunCount = 3;

        $this->maxFailedTestRunPeriod = 86400;

        $this->queue = $this->getMockBuilder('\PhpAmqpLib\Connection\AMQPStreamConnection')
          ->disableOriginalConstructor()
          ->getMock();

        $this->queueName = 'fooBar200' . mt_rand();

        $this->resultStorage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

        $this->tester = $this->getMock('\Triquanta\AccessibilityMonitor\Testing\TesterInterface');

        $this->ttl = mt_rand();

        $this->sut = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Worker')
          ->setConstructorArgs([$this->logger, $this->tester, $this->resultStorage, $this->queue, $this->queueName, $this->ttl, $this->maxFailedTestRunCount, $this->maxFailedTestRunPeriod])
          ->setMethods(['acknowledgeMessage', 'publishMessage'])
          ->getMock();
    }

    /**
     * @covers ::__construct
     */
    public function testConstruct()
    {
        $this->sut = new Worker($this->logger, $this->tester, $this->resultStorage, $this->queue, $this->queueName, $this->ttl, $this->maxFailedTestRunCount, $this->maxFailedTestRunPeriod);
    }

    /**
     * @covers ::registerWorker
     *
     * @todo Extend this with some proper assertions.
     */
    public function testRegisterWorker()
    {
        $channel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
          ->disableOriginalConstructor()
          ->getMock();
        $channel->expects($this->atLeastOnce())
          ->method('queue_declare')
          ->with($this->queueName);

        $this->queue->expects($this->atLeastOnce())
          ->method('channel')
          ->willReturn($channel);

        $this->sut->registerWorker();
    }

    /**
     * @covers ::processMessage
     * @covers ::validateMessage
     */
    public function testProcessMessage()
    {
        $urlId = mt_rand();
        $failedTestRuns = [time(), time()];
        $messageData = new \stdClass();
        $messageData->urlId = $urlId;
        $messageData->failedTestRuns = $failedTestRuns;
        $message = new AMQPMessage(json_encode($messageData));

        $url = new Url();

        $this->resultStorage->expects($this->atLeastOnce())
          ->method('getUrlById')
          ->with($urlId)
          ->willReturn($url);

        $this->tester->expects($this->once())
          ->method('run');

        $this->sut->processMessage($message);
    }

    /**
     * @covers ::processMessage
     * @covers ::validateMessage
     */
    public function testProcessMessageWithNonExistentUrl()
    {
        $urlId = mt_rand();
        $failedTestRuns = [];
        foreach (range(1, 3) as $i) {
            $failedTestRuns[] = time();
        }
        $messageData = new \stdClass();
        $messageData->urlId = $urlId;
        $messageData->failedTestRuns = $failedTestRuns;
        $message = new AMQPMessage(json_encode($messageData));

        $this->resultStorage->expects($this->atLeastOnce())
          ->method('getUrlById')
          ->with($urlId);

        $this->tester->expects($this->never())
          ->method('run');

        $this->sut->expects($this->never())
          ->method('publishMessage')
          ->with($message);

        $this->sut->processMessage($message);
    }

    /**
     * @covers ::processMessage
     * @covers ::validateMessage
     */
    public function testProcessMessageWithInvalidMessage()
    {
        $messageData = new \stdClass();
        $message = new AMQPMessage(json_encode($messageData));

        $this->tester->expects($this->never())
            ->method('run');

        $this->sut->expects($this->once())
          ->method('acknowledgeMessage')
          ->with($message);

        $this->sut->expects($this->never())
          ->method('publishMessage')
          ->with($message);

        $this->sut->processMessage($message);
    }

    /**
     * @covers ::processMessage
     * @covers ::validateMessage
     *
     * @dataProvider providerTestProcessMessageWithNegativeTestOutcome
     */
    public function testProcessMessageWithNegativeTestOutcome($testRunThrowsException, $dismissal)
    {
        $dismissalFailedTestRuns = [
          time() - $this->maxFailedTestRunPeriod - 3,
        ];
        for ($i = 0; $i < $this->maxFailedTestRunCount; $i++) {
            $dismissalFailedTestRuns[] = time() - mt_rand(0, $this->maxFailedTestRunPeriod);
        }

        $urlId = mt_rand();
        $messageData = new \stdClass();
        $messageData->urlId = $urlId;
        $messageData->failedTestRuns = $dismissal ? $dismissalFailedTestRuns : [];
        $message = new AMQPMessage(json_encode($messageData));

        $url = new Url();

        $this->resultStorage->expects($this->atLeastOnce())
          ->method('getUrlById')
          ->with($urlId)
          ->willReturn($url);
        $this->resultStorage->expects($dismissal ? $this->atLeastOnce() : $this->never())
          ->method('saveUrl')
          ->with($url);

        $this->tester->expects($this->once())
          ->method('run')
          ->will($testRunThrowsException ? $this->throwException(new \Exception()) : $this->returnValue(false));

        $this->sut->expects($this->once())
          ->method('acknowledgeMessage')
          ->with($message);

        $this->sut->expects($dismissal ? $this->never() : $this->once())
          ->method('publishMessage')
          ->with($message);

        $this->sut->processMessage($message);
    }

    /**
     * Provides data to self::testProcessMessageWithNegativeTestOutcome().
     */
    public function providerTestProcessMessageWithNegativeTestOutcome() {
        return [
            [true, false],
            [false, false],
            [false, true],
        ];
    }

}
