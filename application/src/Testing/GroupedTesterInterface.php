<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|GroupedTesterInterface.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

/**
 * Defines a grouped tester.
 */
interface GroupedTesterInterface extends TesterInterface
{

    /**
     * Adds a tester.
     *
     * @param \Triquanta\AccessibilityMonitor\Testing\TesterInterface $tester
     */
    public function addTester(TesterInterface $tester);

}
