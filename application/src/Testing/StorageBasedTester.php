<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|StorageBasedTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Psr\Log\LoggerInterface;
use Triquanta\AccessibilityMonitor\StatsDInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a storage-based tester.
 *
 * Decorates another tester in order to save the results.
 */
class StorageBasedTester implements TesterInterface
{

    /**
     * The maximum number of failed test runs per URL.
     *
     * @var int
     */
    protected $maxFailedTestRuns;

    /**
     * The result storage.
     *
     * @var \Triquanta\AccessibilityMonitor\StorageInterface
     */
    protected $resultStorage;

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The StatsD logger.
     *
     * @var \Triquanta\AccessibilityMonitor\StatsD
     */
    protected $statsD;

    /**
     * The tester.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface
     */
    protected $tester;

    /**
     * Creates a new instance.
     *
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Triquanta\AccessibilityMonitor\StatsDInterface $statsD
     * @param \Triquanta\AccessibilityMonitor\Testing\TesterInterface $tester
     * @param \Triquanta\AccessibilityMonitor\StorageInterface $resultStorage
     * @param int $maxFailedTestRuns
     *   The maximum number of failed test runs for any URL.
     */
    public function __construct(
      LoggerInterface $logger,
      StatsDInterface $statsD,
      TesterInterface $tester,
      StorageInterface $resultStorage,
      $maxFailedTestRuns
    ) {
        $this->logger = $logger;
        $this->statsD = $statsD;
        $this->maxFailedTestRuns = $maxFailedTestRuns;
        $this->resultStorage = $resultStorage;
        $this->tester = $tester;
    }

    public function run(Url $url)
    {
        try {
            $outcome = $this->tester->run($url);
        }
        catch (\Exception $e) {
            $this->logger->emergency(sprintf('%s on line %d in %s when testing %s.', $e->getMessage(), $e->getLine(), $e->getFile(), $url->getUrl()));
            $outcome = false;
        }

        // Process the test outcome.
        if ($outcome) {
            $url->setTestingStatus(TestingStatusInterface::STATUS_TESTED);
        }
        else {
            $url->setFailedTestCount($url->getFailedTestCount() + 1);
            // The URL was tested often enough. Dismiss it.
            if ($url->getFailedTestCount() >= $this->maxFailedTestRuns) {
                $url->setTestingStatus(TestingStatusInterface::STATUS_ERROR);
                $this->logger->info(sprintf('Dismissed testing %s, because it has been tested at least %d times and still failed.', $url->getUrl(), $this->maxFailedTestRuns));
            }
            // Reschedule the URL for testing at a later time.
            else {
                $url->setTestingStatus(TestingStatusInterface::STATUS_SCHEDULED_FOR_RETEST);
                $this->logger->info(sprintf('Rescheduled %s for testing, because the current test failed or was not completed.', $url->getUrl()));
            }
        }
        $url->setLastProcessedTime(time());

        // Save the URL.
        $this->resultStorage->saveUrl($url);

        return $outcome;
    }

}
