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
     * @return bool
     *   Whether saving the data was successful or not.
     */
    public function saveUrl(Url $url);

    /**
     * Counts the number of URLs that was tested in a specific period.
     *
     * @param int $websiteTestResultsId
     * @param $start
     *   A UNIX timestamp.
     * @param $end
     *   A UNIX timestamp.
     *
     * @return int
     */
    public function countUrlsByWebsiteTestResultsIdAndAnalysisDateTimePeriod($websiteTestResultsId, $start, $end);

}
