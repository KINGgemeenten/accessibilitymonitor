<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\ActionsInterface.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines an actions manager.
 */
interface ActionsInterface {

  /**
   * Queues a specific URL for testing.
   *
   * @param \Triquanta\AccessibilityMonitor\Url $url
   */
  public function addUrl(Url $url);

}
