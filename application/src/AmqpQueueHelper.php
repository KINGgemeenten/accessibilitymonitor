<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\AmqpQueueHelper.
 */

namespace Triquanta\AccessibilityMonitor;

use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Message\AMQPMessage;

/**
 * Provides a helper for AMQP queue handling.
 */
class AmqpQueueHelper {

    /**
     * The queue name prefix for test runs.
     */
    const QUEUE_NAME_PREFIX = 'test-run-';

    /**
     * Declares a queue for a queue channel.
     *
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel
     *   The channel to declare the queue for.
     * @param $queueName
     *   The name of the queue to declare.
     *
     * @throws \InvalidArgumentException
     */
    public static function declareQueue(AMQPChannel $channel, $queueName) {
        if (empty($queueName)) {
            throw new \InvalidArgumentException('Invalid queue name given.');
        }

        $channel->queue_declare($queueName, false, true, false, false);
    }

    /**
     * Creates a queue message.
     *
     * @param int $urlId
     *   The ID of the URL to put into the message.
     *
     * @return \PhpAmqpLib\Message\AMQPMessage
     */
    public static function createMessage($urlId) {
        if (!is_int($urlId)) {
            throw new \InvalidArgumentException('The URL ID must be an integer.');
        }

        $properties = [
          'delivery_mode' => 2,
        ];

        $messageData = new \stdClass();
        $messageData->urlId = $urlId;

        return new AMQPMessage(json_encode($messageData), $properties);
    }

    /**
     * Creates the queue name for a test run.
     *
     * @param int $testRunId
     *   The test run's ID.
     *
     * @return string
     */
    public static function createQueueName($testRunId) {
        return static::QUEUE_NAME_PREFIX . $testRunId;
    }

}
