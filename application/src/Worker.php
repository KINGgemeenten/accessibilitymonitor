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
use PhpAmqpLib\Wire\AMQPTable;
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
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
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
     * @param string $queueName
     * @param int $ttl
     * @param int $maxFailedTestRunCount
     * @param int $maxFailedTestRunPeriod
     */
    public function __construct(
      LoggerInterface $logger,
      TesterInterface $tester,
      StorageInterface $resultStorage,
      AMQPStreamConnection $queue,
      $queueName,
      $ttl,
      $maxFailedTestRunCount,
      $maxFailedTestRunPeriod
    ) {
        $this->logger = $logger;
        $this->maxFailedTestRunCount = $maxFailedTestRunCount;
        $this->maxFailedTestRunPeriod = $maxFailedTestRunPeriod;
        $this->queue = $queue;
        $this->queueName = $queueName;
        $this->resultStorage = $resultStorage;
        $this->tester = $tester;
        $this->ttl = $ttl;
    }

    public function registerWorker()
    {
        $queueChannel = $this->queue->channel();
        $properties = new AMQPTable();
        $properties->set('x-max-priority', 9);
        $queueChannel->queue_declare($this->queueName, false, true, false, false, false, $properties);
        $queueChannel->basic_qos(null, 1, null);
        $queueChannel->basic_consume($this->queueName, '', false, false, false, false, [$this, 'processMessage']);
        $start = time();
        $this->logger->info(sprintf('Starting worker. It will be shut down in %d seconds.', $this->ttl));
        while(count($queueChannel->callbacks) && $start + $this->ttl > time()) {
            $queueChannel->wait();
        }
        $this->logger->info(sprintf('Shutting down worker, because its TTL of %d seconds has been reached.', $this->ttl));
    }

    /**
     * Processes a queue message.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function processMessage(AMQPMessage $message) {
        if (!$this->validateMessage($message)) {
            $this->logger->emergency(sprintf('"%s" is not a valid message.', $message->body));
            $this->acknowledgeMessage($message);
            return;
        }

        // For $messageData's structure, see queue_message_schema.json.
        $messageData = json_decode($message->body);
        $urlId = $messageData->urlId;
        $url = $this->resultStorage->getUrlById($urlId);

        if (!$url) {
            $this->logger->emergency(sprintf('URL %s does not exist.', $urlId));
            $this->acknowledgeMessage($message);
            return;
        }

        $this->logger->info(sprintf('Testing %s.',
          $url->getUrl()));

        $start = microtime(true);
        try {
            $outcome = $this->tester->run($url);
        }
        catch (\Exception $e) {
            $this->logger->emergency(sprintf('%s on line %d in %s when testing %s.', $e->getMessage(), $e->getLine(), $e->getFile(), $url->getUrl()));
            $outcome = false;
        }
        $end = microtime(true);
        $duration = $end - $start;
        $this->logger->info(sprintf('Done testing (%s seconds)',
          $duration));

        // Process the test outcome.
        if (!$outcome) {
            $messageData->failedTestRuns[] = time();
            // The URL was tested often enough. Dismiss it.
            if (count($messageData->failedTestRuns) >= $this->maxFailedTestRunCount
              && min($messageData->failedTestRuns) + $this->maxFailedTestRunPeriod <= time()) {
                $url->setTestingStatus(TestingStatusInterface::STATUS_ERROR);
                $url->setAnalysis(time());
                $this->resultStorage->saveUrl($url);
                $this->logger->info(sprintf('Dismissed testing %s, because it has been tested at least %d times in the past %d seconds.', $url->getUrl(), $this->maxFailedTestRunCount, $this->maxFailedTestRunPeriod));
                $this->acknowledgeMessage($message);
            }
            // Reschedule the URL for testing at a later time.
            else {
                $message->body = json_encode($messageData);
                $this->publishMessage($message);
                $this->acknowledgeMessage($message);
                $this->logger->info(sprintf('Rescheduled %s for testing, because the current test failed.', $url->getUrl()));
            }
        }
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
     * Publishes a queue message.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    protected function publishMessage(AMQPMessage $message) {
        $message->delivery_info['channel']->basic_publish($message, '', $this->queueName);
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
