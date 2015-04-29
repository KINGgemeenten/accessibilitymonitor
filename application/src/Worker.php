<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\WorkerInterface.
 */

namespace Triquanta\AccessibilityMonitor;

use JsonSchema\RefResolver;
use JsonSchema\Uri\UriRetriever;
use JsonSchema\Validator as SchemaValidator;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Triquanta\AccessibilityMonitor\Testing\TesterInterface;
use Triquanta\AccessibilityMonitor\Testing\TestingStatusInterface;

/**
 * Provides a queue worker.
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
    protected $ttl;

    /**
     * Constructs a new instance.
     *
     * @param \Psr\Log\LoggerInterface
     * @param \Triquanta\AccessibilityMonitor\Testing\TesterInterface $tester
     * @param \Triquanta\AccessibilityMonitor\StorageInterface $resultStorage
     * @param \PhpAmqpLib\Connection\AMQPStreamConnection $queue
     * @param int $ttl
     */
    public function __construct(
      LoggerInterface $logger,
      TesterInterface $tester,
      StorageInterface $resultStorage,
      AMQPStreamConnection $queue,
      $ttl
    ) {
        $this->logger = $logger;
        $this->amqpQueue = $queue;
        $this->resultStorage = $resultStorage;
        $this->tester = $tester;
        $this->ttl = $ttl;
    }

    public function registerWorker()
    {
        $start = time();
        $this->logger->info(sprintf('Starting worker. It will be shut down in %d seconds or when no queues are available.', $this->ttl));

        // Register the worker only if there is a queue to process messages from
        // and if the TTL has not been exceeded yet.
        while (($this->queue = $this->resultStorage->getQueueToSubscribeTo())
          && $start + $this->ttl > time()) {
            $this->logger->info(sprintf('Registering with queue %s.', $this->queue->getName()));

            // Declare the queue.
            $queueChannel = $this->amqpQueue->channel();
            $queueChannel->queue_declare($this->queue->getName(), false, true, false, false);
            $queueChannel->basic_qos(null, 1, null);

            // Register the current script as a worker.
            $queueChannel->basic_consume($this->queue->getName(), '', false, false, false, false, [$this, 'processMessage']);
            while (count($queueChannel->callbacks)) {
                $queueChannel->wait();
            }
        }

        $this->logger->info(sprintf('Shutting down worker, because its TTL of %d seconds has been reached or there are no available queues.', $this->ttl));
    }

    /**
     * Processes a queue message.
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
            $message->delivery_info['channel']->getConnection()->close();
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
            $message->delivery_info['channel']->getConnection()->close();
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

        // Process the test outcome.
        $this->acknowledgeMessage($message);
        $message->delivery_info['channel']->getConnection()->close();
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
