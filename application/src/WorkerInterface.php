<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\WorkerInterface.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines a queue worker.
 *
 * For the structure of messages that can be processed, see
 * queue_message_schema.json.
 */
interface WorkerInterface {

    /**
     * Runs the worker.
     */
    public function run();

}
