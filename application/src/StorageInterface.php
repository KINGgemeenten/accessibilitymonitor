<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\StorageInterface.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines a storage manager.
 */
interface StorageInterface extends TestingStatusInterface {

  /**
   * Gets a URL by URL ID.
   *
   * @param int $id
   *
   * @return \Triquanta\AccessibilityMonitor\Url
   */
  public function getUrlById($id);

  /**
   * Gets a URL by URL.
   *
   * @param string $url
   *
   * @return \Triquanta\AccessibilityMonitor\Url
   */
  public function getUrlByUrl($url);

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
  public function getUrlsByStatus($status, $limit = NULL);

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
   * @param int $website_id
   *
   * @return \Triquanta\AccessibilityMonitor\Url[]
   */
  public function getUrlsByWebsiteId($website_id);

  /**
   * Gets URLs without a Google Pagespeed score.
   *
   * @param int|null $limit
   *   The number of URLs to get or NULL to return all URLs.
   *
   * @return \Triquanta\AccessibilityMonitor\Url[]
   */
  public function getUrlsWithoutGooglePagespeedScore($limit = NULL);

  /**
   * Gets the number of URLs by website ID.
   *
   * @param int $website_id
   *   The website status.
   *
   * @return int
   */
  public function countUrlsByWebsiteId($website_id);

  /**
   * Gets the number of URLs by website ID.
   *
   * @param int $website_id
   *   The website status.
   * @param string $full_url
   *
   * @return int
   */
  public function countUrlsByWebsiteIdAndFullUrl($website_id, $full_url);

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
   * @param int $website_id
   *   The website status.
   *
   * @return int
   */
  public function countUrlsByStatusAndWebsiteId($status, $website_id);

  /**
   * Gets websites by status.
   *
   * @param int[] $statuses
   *   One of the self::STATUS_* constants.
   *
   * @return \Triquanta\AccessibilityMonitor\Website[]
   */
  public function getWebsitesByStatuses(array $statuses);

  /**
   * Gets a website by website ID.
   *
   * @param int $website_id
   *
   * @return \Triquanta\AccessibilityMonitor\Website
   */
  public function getWebsiteById($website_id);

  /**
   * Gets a website by URL.
   *
   * @param string $url
   *
   * @return \Triquanta\AccessibilityMonitor\Website
   */
  public function getWebsiteByUrl($url);

  /**
   * Saves a URL.
   *
   * @param \Triquanta\AccessibilityMonitor\Url $url
   *
   * @return $this
   */
  public function saveUrl(Url $url);

  /**
   * Gets the datetime of any website's last analysis.
   *
   * @return int
   *   A Unix timestamp.
   */
  public function getWebsiteLastAnalysisDateTime();

  /**
   * Saves a website.
   *
   * @param \Triquanta\AccessibilityMonitor\Website $website
   *
   * @return $this
   */
  public function saveWebsite(Website $website);

  /**
   * Counts the number of CMS test results for a website.
   *
   * @param int $website_id
   *
   * @return int
   */
  public function countCmsTestResultsByWebsiteId($website_id);

  /**
   * Counts the number of Google PageSpeed results for a website.
   *
   * @param int $website_id
   *
   * @return int
   */
  public function countGooglePagespeedResultsByWebsiteId($website_id);

  /**
   * Gets all pending actions.
   *
   * @return \Triquanta\AccessibilityMonitor\Action[]
   */
  public function getPendingActions();

  /**
   * Saves an action.
   *
   * @parem \Triquanta\AccessibilityMonitor\Action
   */
  public function saveAction(Action $action);

}
