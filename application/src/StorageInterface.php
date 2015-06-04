<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\StorageInterface.
 */

namespace Triquanta\AccessibilityMonitor;

use Triquanta\AccessibilityMonitor\Testing\TestingStatusInterface;

/**
 * Defines a storage manager.
 */
interface StorageInterface extends TestingStatusInterface
{

    /**
     * Gets a URL by URL ID.
     *
     * @param int $id
     *
     * @return \Triquanta\AccessibilityMonitor\Url|null
     */
    public function getUrlById($id);

    /**
     * Saves a URL.
     *
     * @param \Triquanta\AccessibilityMonitor\Url $url
     *
     * @throws \Triquanta\AccessibilityMonitor\StorageException
     *   Thrown when storage fails.
     */
    public function saveUrl(Url $url);

    /**
     * Counts the number of URLs that was last tested in a specific period.
     *
     * @param int $websiteTestResultsId
     * @param $start
     *   A UNIX timestamp.
     * @param $end
     *   A UNIX timestamp.
     *
     * @return int
     */
    public function countUrlsByWebsiteTestResultsIdAndLastProcessedDateTimePeriod($websiteTestResultsId, $start, $end);

    /**
     * Gets a test run by ID.
     *
     * @param string $id
     *
     * @return \Triquanta\AccessibilityMonitor\TestRun|null
     */
    public function getTestRunById($id);

    /**
     * Saves a test run.
     *
     * @param \Triquanta\AccessibilityMonitor\TestRun $testRun
     *
     * @throws \Triquanta\AccessibilityMonitor\StorageException
     *   Thrown when storage fails.
     */
    public function saveTestRun(TestRun $testRun);

    /**
     * Gets the test run a worker must process.
     *
     * This method is not idempotent. This means that subsequent calls may
     * return different test runs.
     *
     * @return \Triquanta\AccessibilityMonitor\TestRun|null
     *   The test run or NULL in case no test run is available.
     */
    public function getTestRunToProcess();

    /**
     * Gets URLs based on their status and last processed date/time.
     *
     * @param $status
     *   One of the
     *   \Triquanta\AccessibilityMonitor\Testing\TestingStatusInterface::STATUS_*
     *   constants.
     * @param $start
     *   A Unix timestamp.
     * @param $end
     *   A Unix timestamp.
     *
     * @return \Triquanta\AccessibilityMonitor\Url[]
     */
    public function getUrlsByStatusAndLastProcessedDateTime($status, $start, $end);

}
