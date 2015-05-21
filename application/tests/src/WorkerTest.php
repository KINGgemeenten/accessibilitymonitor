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

        $this->sut = new WorkerTestWorker($this->logger, $this->tester,
          $this->resultStorage, $this->amqpQueue, $this->workerTtl);
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
     * @covers ::run
     *
     * @todo Extend this with some proper assertions.
     */
    public function testrunWithoutAvailableQueues()
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

        $this->sut->run();
    }

    /**
     * @covers ::run
     *
     * @todo Extend this with some proper assertions.
     */
    public function testrunWithAvailableQueue()
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

        $this->sut->run();
    }

    /**
     * @covers ::processMessage
     * @covers ::validateMessage
     */
    public function testProcessMessage()
    {
        $deliveryTag = 'FooBar' . mt_rand();
        $urlId = mt_rand();

        $channel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
          ->disableOriginalConstructor()
          ->getMock();
        $channel->expects($this->once())
          ->method('basic_ack')
          ->with($deliveryTag);

        $messageData = new \stdClass();
        $messageData->urlId = $urlId;
        $message = new AMQPMessage(json_encode($messageData));
        $message->delivery_info['delivery_tag'] = $deliveryTag;
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

        $this->sut->processMessage($channel, $message);
    }

    /**
     * @covers ::processMessage
     * @covers ::validateMessage
     */
    public function testProcessMessageWithNonExistentUrl()
    {
        $deliveryTag = 'FooBar' . mt_rand();
        $urlId = mt_rand();

        $channel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
          ->disableOriginalConstructor()
          ->getMock();
        $channel->expects($this->atLeastOnce())
          ->method('basic_ack')
          ->with($deliveryTag);

        $messageData = new \stdClass();
        $messageData->urlId = $urlId;
        $message = new AMQPMessage(json_encode($messageData));
        $message->delivery_info['delivery_tag'] = $deliveryTag;
        $message->delivery_info['channel'] = $channel;

        $queue = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Queue')
          ->disableOriginalConstructor()
          ->getMock();
        $this->sut->setQueue($queue);

        $this->resultStorage->expects($this->atLeastOnce())
          ->method('getUrlById')
          ->with($urlId);

        $this->tester->expects($this->never())
          ->method('run');;

        $this->sut->processMessage($channel, $message);
    }

    /**
     * @covers ::processMessage
     * @covers ::validateMessage
     */
    public function testProcessMessageWithInvalidMessage()
    {
        $deliveryTag = 'FooBar' . mt_rand();

        $channel = $this->getMockBuilder('\PhpAmqpLib\Channel\AMQPChannel')
          ->disableOriginalConstructor()
          ->getMock();

        $messageData = new \stdClass();
        $message = new AMQPMessage(json_encode($messageData));
        $message->delivery_info['delivery_tag'] = $deliveryTag;
        $message->delivery_info['channel'] = $channel;

        $queue = $this->getMockBuilder('\Triquanta\AccessibilityMonitor\Queue')
          ->disableOriginalConstructor()
          ->getMock();
        $this->sut->setQueue($queue);

        $this->tester->expects($this->never())
            ->method('run');

        $this->sut->processMessage($channel, $message);
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
