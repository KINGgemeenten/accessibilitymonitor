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
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Triquanta\AccessibilityMonitor\Testing\TesterInterface;

/**
 * Provides a test run worker that tests URLs.
 */
class Worker implements WorkerInterface {

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The AMQP queue connection.
     *
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    protected $queueConnection;

    /**
     * The current test run to process.
     *
     * @var \Triquanta\AccessibilityMonitor\TestRun
     */
    protected $testRun;

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
        $this->queueConnection = $queue;
        $this->resultStorage = $resultStorage;
        $this->tester = $tester;
        $this->workerTtl = $workerTtl;
    }

    public function run()
    {
        $this->logger->debug(sprintf('Starting worker. It will be shut down in %d seconds.', $this->workerTtl));
        $queueChannel = $this->queueConnection->channel();
        $workerStart = time();
        $failureWait = 3;

        while ($workerStart + $this->workerTtl > time()) {
            // Try to find a test run to process.
            $this->testRun = $this->resultStorage->getTestRunToProcess();
            if (!$this->testRun) {
                sleep($failureWait);
                continue;
            }

            // Try to retrieve a message from the queue.
            $queueName = AmqpQueueHelper::createQueueName($this->testRun->getId());
            AmqpQueueHelper::declareQueue($queueChannel, $queueName);
            $message = $queueChannel->basic_get($queueName);
            if (!($message instanceof AMQPMessage)) {
                sleep($failureWait);
                continue;
            }

            $this->processMessage($queueChannel, $message);
        }

        $this->logger->debug(sprintf('Shutting down worker, because its TTL of %d seconds was reached.', $this->workerTtl));
        $queueChannel->close();
    }

    /**
     * Processes a queue message.
     *
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel
     * @param \PhpAmqpLib\Message\AMQPMessage $message
     */
    public function processMessage(AMQPChannel $channel, AMQPMessage $message) {
        try {
            // Check message integrity.
            if (!$this->validateMessage($message)) {
                $this->logger->emergency(sprintf('"%s" is not a valid message.', $message->body));
                $channel->basic_ack($message->delivery_info['delivery_tag']);
                return;
            }

            // For $messageData's structure, see queue_message_schema.json.
            $messageData = json_decode($message->body);
            $urlId = $messageData->urlId;
            $url = $this->resultStorage->getUrlById($urlId);

            // Check if the message referenced an existing URL.
            if (!$url) {
                $this->logger->emergency(sprintf('URL %s does not exist.', $urlId));
                $channel->basic_ack($message->delivery_info['delivery_tag']);
                return;
            }

            // Run the actual tests.
            $this->logger->info(sprintf('Testing %s.', $url->getUrl()));
            $start = microtime(true);
            $this->tester->run($url);
            $end = microtime(true);
            $duration = $end - $start;
            $this->logger->info(sprintf('Done testing %s (%s seconds)', $url->getUrl(), $duration));
        }
        catch (StorageException $e) {
            // If saving the URL or test run failed, the metadata that was set
            // on it during the test run may have been lost as well. Because the
            // re-tester relies on this metadata, publishing the URL to the
            // queue again is the only way to be certain it will be re-tested
            // again in the future.
            $queueName = AmqpQueueHelper::createQueueName($this->testRun->getId());
            $channel->basic_publish($message, '', $queueName);
        }
        catch (\Exception $e) {
            $this->logger->emergency(sprintf('%s on line %d in %s.', $e->getMessage(), $e->getLine(), $e->getFile()));
        }

        $channel->basic_ack($message->delivery_info['delivery_tag']);
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
