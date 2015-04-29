<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|TesterInterface.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Triquanta\AccessibilityMonitor\Url;

/**
 * Defines a tester.
 */
interface TesterInterface
{

    /**
     * Runs the test for a URL.
     *
     * Results must be stored on the URL object itself.
     *
     * @param \Triquanta\AccessibilityMonitor\Url $url
     *
     * @return bool
     *   Whether the URL was tested or not.
     */
    public function run(Url $url);

}
