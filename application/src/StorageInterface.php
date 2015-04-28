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

    /**
     * Gets a queue by queue ID.
     *
     * @param string $id
     *
     * @return \Triquanta\AccessibilityMonitor\Queue|null
     */
    public function getQueueById($id);

    /**
     * Saves a queue.
     *
     * @param \Triquanta\AccessibilityMonitor\Queue $queue
     *
     * @return bool
     *   Whether saving the data was successful or not.
     */
    public function saveQueue(Queue $queue);

    /**
     * Gets the queue a worker must subscribe to.
     *
     * This method is not idempotent. This means that subsequent calls may
     * return different queues.
     *
     * @return \Triquanta\AccessibilityMonitor\Queue|null
     *   The queue or NULL in case no queue is available.
     */
    public function getQueueToSubscribeTo();

}
