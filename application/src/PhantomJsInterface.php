<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\PhantomJsInterface.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines a Phantom JS manager.
 */
interface PhantomJsInterface
{

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
    public function getQuailResult($url);

    /**
     * Kill all stalled phantomjs processes.
     */
    public function killStalledProcesses();

}
