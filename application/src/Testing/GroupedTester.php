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
class GroupedTester implements GroupedTesterInterface
{

    /**
     * The testers.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface[]
     */
    protected $testers = [];

    public function addTester(TesterInterface $tester)
    {
        $this->testers[] = $tester;
    }

    public function run(Url $url)
    {
        $results = [];
        foreach ($this->testers as $tester) {
            $results[] = $tester->run($url);
        }

        if (in_array(false, $results, true)) {
            return false;
        }
        else {
            $url->setTestingStatus(Url::STATUS_TESTED);
            $url->setAnalysis(time());
            return true;
        }
    }

}
