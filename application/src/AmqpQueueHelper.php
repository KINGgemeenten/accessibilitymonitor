<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\AmqpQueueHelper.
 */

namespace Triquanta\AccessibilityMonitor;

use PhpAmqpLib\Channel\AMQPChannel;

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
    }

}
