<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|StorageBasedTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Psr\Log\LoggerInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a storage-based tester.
 */
class StorageBasedTester implements TesterInterface
{

    /**
     * The flooding thresholds.
     *
     * @var int[]
     *   Keys are periods in seconds, values are maximum number of requests.
     *   They represent the maximum number of requests that can be made to a
     *   host in the past period.
     */
    protected $floodingThresholds = [];

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
     * The tester.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface
     */
    protected $tester;

    /**
     * Creates a new instance.
     *
     * @param \Triquanta\AccessibilityMonitor\Testing\TesterInterface $tester
     * @param \Triquanta\AccessibilityMonitor\StorageInterface $resultStorage
     * @param int[] $floodingThresholds
     *   Keys are periods in seconds, values are maximum number of requests.
     *   They represent the maximum number of requests that can be made to a
     *   host in the past period.
     */
    public function __construct(
      LoggerInterface $logger,
      TesterInterface $tester,
      StorageInterface $resultStorage,
      array $floodingThresholds
    ) {
        $this->floodingThresholds = $floodingThresholds;
        $this->logger = $logger;
        $this->resultStorage = $resultStorage;
        $this->tester = $tester;
    }

    public function run(Url $url)
    {
        if ($this->preventFlooding($url)) {
            try {
                if ($this->tester->run($url)) {
                    $storageResult = $this->resultStorage->saveUrl($url);
                    if (!$storageResult) {
                        $this->logger->emergency(sprintf('The results for %s were not saved, because of a storage error.', $url->getUrl()));
                    }
                    return $storageResult;
                }
                else {
                    $this->logger->debug(sprintf('The results for %s were not saved, because testing failed or was not completed.', $url->getUrl()));
                }
                return false;
            }
            catch (\Exception $e) {
                $this->logger->emergency(sprintf('%s on line %d in %s when testing %s.', $e->getMessage(), $e->getLine(), $e->getFile(), $url->getUrl()));
                return false;
            }
        }
        else {
            $this->logger->info(sprintf('Skipped testing of %s to prevent flooding.', $url->getUrl()));
            return false;
        }
    }

    /**
     * Prevents flooding of hosts with requests (DOS attack).
     *
     * @param \Triquanta\AccessibilityMonitor\Url $url
     *
     * @return bool
     *   Whether the URL can be tested.
     */
    protected function preventFlooding(Url $url) {
        foreach ($this->floodingThresholds as $period => $maximum) {
            if ($this->resultStorage->countUrlsByWebsiteTestResultsIdAndAnalysisDateTimePeriod($url->getWebsiteTestResultsId(), time() - $period, time()) >= $maximum) {
                return FALSE;
            }
        }
        return TRUE;
    }

}
