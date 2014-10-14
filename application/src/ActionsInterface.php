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
   * Queues a website for testing.
   *
   * @param \Triquanta\AccessibilityMonitor\Website $website
   */
  public function addWebsite(Website $website);

  /**
   * Queues a website for a re-scan.
   *
   * @param \Triquanta\AccessibilityMonitor\Website $website
   */
  public function rescanWebsite(Website $website);

  /**
   * Excludes a whole website from testing.
   *
   * @param \Triquanta\AccessibilityMonitor\Website $website
   */
  public function excludeWebsite(Website $website);

  /**
   * Queues a specific URL for testing.
   *
   * @param \Triquanta\AccessibilityMonitor\Url $url
   */
  public function addUrl(Url $url);

  /**
   * Excludes a specific URL from testing.
   *
   * @param \Triquanta\AccessibilityMonitor\Url $url
   */
  public function excludeUrl(Url $url);

  /**
   * Deleted all results for a website.
   *
   * @param \Triquanta\AccessibilityMonitor\Website $website
   */
  public function deleteWebsiteResults(Website $website);

}
