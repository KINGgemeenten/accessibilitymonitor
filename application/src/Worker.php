<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\WorkerInterface.
 */

namespace Triquanta\AccessibilityMonitor;

use JsonSchema\RefResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator as SchemaValidator;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPBasicCancelException;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Triquanta\AccessibilityMonitor\Testing\TesterInterface;

/**
 * Provides a queue worker.
 *
 * This worker spins up AMQP consumers as needed.
 */
class Worker implements WorkerInterface {

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The AMQP queue.
     *
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    protected $amqpQueue;

    /**
     * The queue.
     *
     * @var \Triquanta\AccessibilityMonitor\Queue
     */
    protected $queue;

    /**
     * The result storage.
     *
     * @var \Triquanta\AccessibilityMonitor\StorageInterface
     */
    protected $resultStorage;

    /**
     * The tester.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface
     */
    protected $tester;

    /**
     * The worker's TTL in seconds.
     *
     * @var int
     */
    protected $workerTtl;

    /**
     * Constructs a new instance.
     *
     * @param \Psr\Log\LoggerInterface
     * @param \Triquanta\AccessibilityMonitor\Testing\TesterInterface $tester
     * @param \Triquanta\AccessibilityMonitor\StorageInterface $resultStorage
     * @param \PhpAmqpLib\Connection\AMQPStreamConnection $queue
     * @param int $workerTtl
     */
    public function __construct(
      LoggerInterface $logger,
      TesterInterface $tester,
      StorageInterface $resultStorage,
      AMQPStreamConnection $queue,
      $workerTtl
    ) {
        $this->logger = $logger;
        $this->amqpQueue = $queue;
        $this->resultStorage = $resultStorage;
        $this->tester = $tester;
        $this->workerTtl = $workerTtl;
    }

    public function registerWorker()
    {
        $this->logger->info(sprintf('Starting worker. It will be shut down in %d seconds or when no queues are available.', $this->workerTtl));
        $queueChannel = $this->amqpQueue->channel();
        $workerStart = time();

        while ($workerStart + $this->workerTtl > time()) {
            $consumerTtl = 3;

            $this->queue = $this->resultStorage->getQueueToSubscribeTo();
            if (!$this->queue) {
                // Wait before trying to find a queue again.
                sleep($consumerTtl);
                break;
            }

            // Register the current script as a consumer.
            $this->logger->info(sprintf('Registering consumer with queue %s.', $this->queue->getName()));
            $consumerStart = time();
            AmqpQueueHelper::declareQueue($queueChannel, $this->queue->getName());
            $queueChannel->basic_consume($this->queue->getName(), '', false, false, false, false, [$this, 'processMessage']);

            // Wait for push messages, but only until the TTL.
            while (count($queueChannel->callbacks) && $consumerStart + $consumerTtl > time()) {
                try {
                    // The wait must be non-blocking to allow us to reliably
                    // enforce the TTL. Blocking waits can exceed the TTL when
                    // no frames are received.
                    $queueChannel->wait(null, true, $consumerTtl);
                }
                // The queue can be deleted while the consumer is still
                // listening to it. This is expected application behavior, so
                // prevent the exception from bubbling up and stop waiting for
                // messages.
                catch (AMQPBasicCancelException $e) {
                    break;
                }
                // The wait timeout was reached. This should not happen, but we
                // want to fail gracefully.
                // @todo Consider raising the log message's severity as a
                //   timeout can indicate a connection error, among other
                //   things.
                catch (AMQPTimeoutException $e) {
                    $this->logger->debug(sprintf('The consumer wait timeout of %d seconds was reached.', $consumerTtl));
                    $this->cancelConsumer($queueChannel, $consumerStart);
                }
            }
        }

        $this->logger->info(sprintf('Shutting down worker, because its TTL of %d seconds has been reached or there are no available queues.', $this->workerTtl));
        $queueChannel->close();
    }

    /**
     * Processes a queue message.
     *
     * This is the actual AMQP consumer.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function processMessage(AMQPMessage $message) {
        // Register this test run.
        $this->queue->setLastRequest(time());
        $this->resultStorage->saveQueue($this->queue);

        // Check message integrity.
        if (!$this->validateMessage($message)) {
            $this->logger->emergency(sprintf('"%s" is not a valid message.', $message->body));
            $this->acknowledgeMessage($message);
            $this->cancelConsumer($message->delivery_info['channel'], $message->delivery_info['consumer_tag']);
            return;
        }

        // For $messageData's structure, see queue_message_schema.json.
        $messageData = json_decode($message->body);
        $urlId = $messageData->urlId;
        $url = $this->resultStorage->getUrlById($urlId);

        // Check if the message referenced an existing URL.
        if (!$url) {
            $this->logger->emergency(sprintf('URL %s does not exist.', $urlId));
            $this->acknowledgeMessage($message);
            $this->cancelConsumer($message->delivery_info['channel'], $message->delivery_info['consumer_tag']);
            return;
        }

        // Run the actual tests.
        $this->logger->info(sprintf('Testing %s.', $url->getUrl()));
        $start = microtime(true);
        try {
            $this->tester->run($url);
        }
        catch (\Exception $e) {
            $this->logger->emergency(sprintf('%s on line %d in %s when testing %s.', $e->getMessage(), $e->getLine(), $e->getFile(), $url->getUrl()));
        }
        $end = microtime(true);
        $duration = $end - $start;
        $this->logger->info(sprintf('Done testing %s (%s seconds)', $url->getUrl(), $duration));

        $this->acknowledgeMessage($message);

        $this->cancelConsumer($message->delivery_info['channel'], $message->delivery_info['consumer_tag']);
    }

    /**
     * Cancels a consumer.
     *
     * @param \PhpAmqpLib\Channel\AMQPChannel $queueChannel
     * @param string $consumerTag
     */
    protected function cancelConsumer(AMQPChannel $queueChannel, $consumerTag) {
        $this->logger->info(sprintf('Cancelling the consumer for queue %s.', $this->queue->getName()));
        $queueChannel->basic_cancel($consumerTag);
    }

    /**
     * Acknowledges a queue message.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    protected function acknowledgeMessage(AMQPMessage $message) {
        $message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);
    }

    /**
     * Validates a queue message.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     *
     * @return bool
     *   Whether the message is valid.
     */
    protected function validateMessage(AMQPMessage $message) {
        $schemaPath = __DIR__ . '/../queue_message_schema.json';
        $schemaUri = 'file://' . $schemaPath;
        $schemaRetriever = new UriRetriever();
        $schema = $schemaRetriever->retrieve($schemaUri);

        $schemaReferenceResolver = new RefResolver($schemaRetriever);
        $schemaReferenceResolver->resolve($schema, $schemaUri);

        $schemaValidator = new SchemaValidator();
        $schemaValidator->check(json_decode($message->body), $schema);

        return $schemaValidator->isValid();
    }

}
