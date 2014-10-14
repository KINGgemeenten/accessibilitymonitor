<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\GooglePagespeedInterface.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines a Google Pagespeed tester.
 */
interface GooglePagespeedInterface {

  /**
   * Tests a URL using Google Pagespeed.
   *
   * @param string $url
   *   The URL to test.
   */
  public function test($url);

}
