<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|GroupedTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a grouped tester.
 */
class GroupedTester implements GroupedTesterInterface {

    /**
     * The testers.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface[]
     */
    protected $testers = [];

    public function addTester(TesterInterface $tester) {
        $this->testers[] = $tester;
    }

    public function run(Url $url) {
        foreach ($this->testers as $tester) {
            $tester->run($url);
        }
    }

}
