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
     * Declares a queue for a queue channel.
     *
     * @param \PhpAmqpLib\Channel\AMQPChannel $channel
     *   The channel to declare the queue for.
     * @param $queueName
     *   The name of the queue to declare.
     */
    public static function declareQueue(AMQPChannel $channel, $queueName) {
        $channel->queue_declare($queueName, false, true, false, false);
        $channel->basic_qos(null, 1, null);
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

}
