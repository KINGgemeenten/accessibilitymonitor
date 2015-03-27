<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\WorkerInterface.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines a queue worker.
 *
 * For the structure messages that can be processed, see
 * queue_message_schema.json.
 */
interface WorkerInterface {

    /**
     * Registers the worker with the queue.
     */
    public function registerWorker();

}
