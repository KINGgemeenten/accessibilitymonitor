<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\QuailWorkerFactoryInterface.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines a Quail worker factory.
 */
interface QuailWorkerFactoryInterface {

  /**
   * Creates a new worker.
   *
   * @param \Triquanta\AccessibilityMonitor\Url $url
   * @param int $queue_id
   *
   * @return \Triquanta\AccessibilityMonitor\PhantomQuailWorker
   */
  public function createWorker(Url $url, $queue_id);

} 
