<?php

/**
 * @file
 * Contains \Triquanta\Tests\AccessibilityMonitor\WorkerTest.
 */

namespace Triquanta\Tests\AccessibilityMonitor;

use PhpAmqpLib\Message\AMQPMessage;
use Triquanta\AccessibilityMonitor\Queue;
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
     * The queue.
     *
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $amqpQueue;

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
    protected $workerTtl;

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

        $this->amqpQueue = $this->getMockBuilder('\PhpAmqpLib\Connection\AMQPStreamConnection')
          ->disableOriginalConstructor()
          ->getMock();

        $this->resultStorage = $this->getMock('\Triquanta\AccessibilityMonitor\StorageInterface');

        $this->tester = $this->getMock('\Triquanta\AccessibilityMonitor\Testing\TesterInterface');

        $this->workerTtl = mt_rand(2, 5);

        $this->sut = $this->getMockBuilder('\Triquanta\Tests\AccessibilityMonitor\WorkerTestWorker')
          ->setConstructorArgs([
            $this->logger,
            $this->tester,
            $this->resultStorage,
            $this->amqpQueue,
            $this->workerTtl])
          ->setMethods(['acknowledgeMessage', 'publishMessage'])
          ->getMock();
    }

    /**
     * @covers ::__construct
     */
    public function testConstruct()
    {
        $this->sut = new Worker($this->logger, $this->tester,
          $this->resultStorage, $this->amqpQueue, $this->workerTtl);
    }

    /**
     * @covers ::registerWorker
     *
     * @todo Extend this with some proper assertions.
     */
    public function testRegisterWorkerWithoutAvailableQueues()
    {
        $this->resultStorage->expects($this->atLeastOnce())
          ->method('getQueueToSubscribeTo')
          ->willReturn(null);

        $channel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
          ->disableOriginalConstructor()
          ->getMock();
        $channel->expects($this->never())
          ->method('queue_declare');

        $this->amqpQueue->expects($this->atLeastOnce())
          ->method('channel')
          ->willReturn($channel);

        $this->sut->registerWorker();
    }

    /**
     * @covers ::registerWorker
     *
     * @todo Extend this with some proper assertions.
     */
    public function testRegisterWorkerWithAvailableQueue()
    {
        $queueName = 'foo_bar_' . mt_rand();

        $queue = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Queue')
          ->disableOriginalConstructor()
          ->getMock();
        $queue->expects($this->atLeastOnce())
            ->method('getName')
            ->willReturn($queueName);

        $this->resultStorage->expects($this->atLeastOnce())
            ->method('getQueueToSubscribeTo')
            ->willReturn($queue);

        $channel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
          ->disableOriginalConstructor()
          ->getMock();
        $channel->expects($this->atLeastOnce())
          ->method('queue_declare')
          ->with($queueName);

        $this->amqpQueue->expects($this->atLeastOnce())
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
        $consumerTag = 'FooBar' . mt_rand();
        $urlId = mt_rand();

        $channel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
          ->disableOriginalConstructor()
          ->getMock();
        $channel->expects($this->once())
          ->method('basic_cancel')
          ->with($consumerTag);

        $messageData = new \stdClass();
        $messageData->urlId = $urlId;
        $message = new AMQPMessage(json_encode($messageData));
        $message->delivery_info['consumer_tag'] = $consumerTag;
        $message->delivery_info['channel'] = $channel;

        $url = new Url();

        $queue = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Queue')
          ->disableOriginalConstructor()
          ->getMock();
        $this->sut->setQueue($queue);

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
        $consumerTag = 'FooBar' . mt_rand();
        $urlId = mt_rand();

        $channel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
          ->disableOriginalConstructor()
          ->getMock();
        $channel->expects($this->atLeastOnce())
          ->method('basic_cancel')
          ->with($consumerTag);

        $messageData = new \stdClass();
        $messageData->urlId = $urlId;
        $message = new AMQPMessage(json_encode($messageData));
        $message->delivery_info['consumer_tag'] = $consumerTag;
        $message->delivery_info['channel'] = $channel;

        $queue = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Queue')
          ->disableOriginalConstructor()
          ->getMock();
        $this->sut->setQueue($queue);

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
        $consumerTag = 'FooBar' . mt_rand();

        $channel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
          ->disableOriginalConstructor()
          ->getMock();

        $messageData = new \stdClass();
        $message = new AMQPMessage(json_encode($messageData));
        $message->delivery_info['consumer_tag'] = $consumerTag;
        $message->delivery_info['channel'] = $channel;

        $queue = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Queue')
          ->disableOriginalConstructor()
          ->getMock();
        $this->sut->setQueue($queue);

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

}

/**
 * Provides a worker with an injectable queue.
 */
class WorkerTestWorker extends Worker {

    /**
     * Sets the queue.
     *
     * @param \Triquanta\AccessibilityMonitor\Queue $queue
     */
    public function setQueue(Queue $queue) {
        $this->queue = $queue;
    }

}
