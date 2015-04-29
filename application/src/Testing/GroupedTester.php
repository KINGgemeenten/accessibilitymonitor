<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|GroupedTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Psr\Log\LoggerInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a grouped tester.
 *
 * Decorates multiple other testers and aggregates test outcomes.
 */
class GroupedTester implements GroupedTesterInterface
{

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The testers.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface[]
     */
    protected $testers = [];

    /**
     * Constructs a new instance.
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(
      LoggerInterface $logger
    ) {
        $this->logger = $logger;
    }

    public function addTester(TesterInterface $tester)
    {
        $this->testers[] = $tester;
    }

    public function run(Url $url)
    {
        $outcomes = [];
        foreach ($this->testers as $tester) {
            $start = microtime(true);
            $outcomes[] = $tester->run($url);
            $end = microtime(true);
            $duration = $end - $start;
            $this->logger->debug(sprintf('Done running tests for %s (%s seconds using %s)', $url->getUrl(), $duration, get_class($tester)));
        }

        // The aggregated outcome is negative if at least one tester returns a
        // negative outcome.
        if (in_array(false, $outcomes, true)) {
            return false;
        }
        else {
            $url->setTestingStatus(Url::STATUS_TESTED);
            $url->setAnalysis(time());
            return true;
        }
    }

}
