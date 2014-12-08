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
   * @param bool $determine_cms
   * @param bool $execute_google_pagespeed
   *
   * @return \Triquanta\AccessibilityMonitor\PhantomQuailWorker
   */
  public function createWorker(Url $url, $queue_id, $determine_cms, $execute_google_pagespeed);

} 
