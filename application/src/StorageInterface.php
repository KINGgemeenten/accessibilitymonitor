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
     * Gets URLs by status.
     *
     * @param int $status
     *   One of the self::STATUS_* constants.
     * @param int|null $limit
     *   The number of URLs to get or NULL to return all URLs.
     *
     * @return \Triquanta\AccessibilityMonitor\Url[]
     */
    public function getUrlsByStatus($status, $limit = null);

    /**
     * Gets URLs that don't have a particular status.
     *
     * @param int $status
     *   One of the self::STATUS_* constants.
     *
     * @return \Triquanta\AccessibilityMonitor\Url[]
     */
    public function getUrlsByNotStatus($status);

    /**
     * Gets URLs for a specific website.
     *
     * @param int $website_test_results_id
     *
     * @return \Triquanta\AccessibilityMonitor\Url[]
     */
    public function getUrlsByWebsiteTestResultsId($website_test_results_id);

    /**
     * Gets URLs without a Google Pagespeed score.
     *
     * @param int|null $limit
     *   The number of URLs to get or NULL to return all URLs.
     *
     * @return \Triquanta\AccessibilityMonitor\Url[]
     */
    public function getUrlsWithoutGooglePagespeedScore($limit = null);

    /**
     * Gets the number of URLs by website ID.
     *
     * @param int $website_test_results_id
     *   The website status.
     *
     * @return int
     */
    public function countUrlsByWebsiteTestResultsId($website_test_results_id);

    /**
     * Counts URLs by status.
     *
     * @param int $status
     *   One of the self::STATUS_* constants.
     *
     * @return int
     */
    public function countUrlsByStatus($status);

    /**
     * Gets the number of URLs by status and website ID.
     *
     * @param int $status
     *   One of the self::STATUS_* constants.
     * @param int $website_test_results_id
     *   The website status.
     *
     * @return int
     */
    public function countUrlsByStatusAndWebsiteId(
      $status,
      $website_test_results_id
    );

    /**
     * Saves a URL.
     *
     * @param \Triquanta\AccessibilityMonitor\Url $url
     *
     * @return $this
     */
    public function saveUrl(Url $url);

    /**
     * Gets the datetime of any URL's last analysis.
     *
     * @return int
     *   A Unix timestamp.
     */
    public function getUrlLastAnalysisDateTime();

    /**
     * Counts the number of CMS test results for a website.
     *
     * @param int $website_test_results_id
     *
     * @return int
     */
    public function countCmsTestResultsByWebsiteTestResultsId(
      $website_test_results_id
    );

    /**
     * Counts the number of Google PageSpeed results for a website.
     *
     * @param int $website_test_results_id
     *
     * @return int
     */
    public function countGooglePagespeedResultsByWebsiteTestResultsId(
      $website_test_results_id
    );

}
