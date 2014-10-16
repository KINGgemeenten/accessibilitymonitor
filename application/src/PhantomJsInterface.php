<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\PhantomJsInterface.
 */

namespace Triquanta\AccessibilityMonitor;

use Psr\Log\LoggerInterface;

/**
 * Defines a Phantom JS manager.
 */
interface PhantomJsInterface {

  /**
   * Detects the apps powering a URL.
   *
   * @param string $url
   *   The URL to scan.
   *
   * @return string[]
   *   The names of the detected apps.
   */
  public function getDetectedApps($url);

  /**
   * Gets the Quail analysis results for a URL.
   *
   * @param string $url
   *   The URL to scan.
   *
   * @return string
   *   The JSON results.
   */
  public function getQuailResults($url);

  /**
   * Set the logger. Only needed in a thread situation.
   *
   * @param LoggerInterface $logger
   *
   * @return mixed
   */
  public function setLogger(LoggerInterface $logger);

  /**
   * Kill all stalled phantomjs processes.
   */
  public static function killStalledProcesses();

}
