<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|ReTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Triquanta\AccessibilityMonitor\AmqpQueueHelper;
use Triquanta\AccessibilityMonitor\StorageInterface;

/**
 * Re-tests URLs.
 */
class ReTester {

    /**
     * The AMQP queue.
     *
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    protected $amqpConnection;

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The result sstorage.
     *
     * @var \Triquanta\AccessibilityMonitor\StorageInterface
     */
    protected $storage;

    /**
     * The re-test threshold.
     *
     * @var int
     *   The number of seconds after which a URL can be tested again.
     */
    protected $threshold;

    /**
     * Constructs a new instance.
     *
     * @param \Psr\Log\LoggerInterface $logger
     *   The logger.
     * @param \Triquanta\AccessibilityMonitor\StorageInterface $storage
     *   The results storage.
     * @param \PhpAmqpLib\Connection\AMQPStreamConnection $amqpConnection
     *   The AMQP queue connection.
     * @param int $threshold
     *   The number of seconds after which a URL can be tested again.
     */
    public function __construct(LoggerInterface $logger, StorageInterface $storage, AMQPStreamConnection $amqpConnection, $threshold) {
        $this->amqpConnection = $amqpConnection;
        $this->logger = $logger;
        $this->storage = $storage;
        $this->threshold = $threshold;
    }

    /**
     * Re-tests URLs.
     */
    public function retest() {
        $urls = $this->storage->getUrlsByStatusAndAnalysisDateTime(TestingStatusInterface::STATUS_SCHEDULED_FOR_RETEST, 0, time() - $this->threshold);
        foreach ($urls as $url) {
            // Update the URL in storage.
            $url->setTestingStatus(TestingStatusInterface::STATUS_SCHEDULED);
            $this->storage->saveUrl($url);

            // Add the URL to the AMQP queue.
            $queueChannel = $this->amqpConnection->channel();
            AmqpQueueHelper::declareQueue($queueChannel, $url->getQueueName());
            $properties = array(
              'delivery_mode' => 2,
            );
            $messageData = new \stdClass();
            $messageData->urlId = (int) $url->getId();
            $msg = new AMQPMessage(json_encode($messageData), $properties);
            $queueChannel->basic_publish($msg, '', $url->getQueueName());
        }
        $this->logger->info(sprintf('Scheduled %d URL(s) for re-testing.', count($urls)));
    }

}
