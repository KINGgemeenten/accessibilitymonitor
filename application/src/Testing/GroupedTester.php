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
        $results = [];
        foreach ($this->testers as $tester) {
            $start = microtime(true);
            $results[] = $tester->run($url);
            $end = microtime(true);
            $duration = $end - $start;
            $this->logger->debug(sprintf('Done running tests for %s (%s seconds using %s)', $url->getUrl(), $duration, get_class($tester)));
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
