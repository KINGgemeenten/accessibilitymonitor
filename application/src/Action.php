<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Action.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Represents a scheduled action.
 */
class Action {

  const ACTION_ADD_URL = 'addUrl';

  /**
   * The URL ID.
   *
   * @var int
   */
  protected $id;

  /**
   * The action to take.
   *
   * @var string
   *   One of the self::ACTION_* constants.
   */
  protected $action;

  /**
   * The URL to take the action for.
   *
   * @var string
   */
  protected $url;

  /**
   * The timestamp.
   *
   * @todo What does this do?
   *
   * @var int
   *   A Unix timestamp.
   */
  protected $timestamp;

  /**
   * The website test results ID.
   *
   * @var int
   */
  protected $websiteTestResultsId;

  /**
   * Returns the URL ID.
   *
   * @return int
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Sets the URL ID.
   *
   * @param int $id
   *
   * @return $this
   */
  public function setId($id) {
    if ($this->id) {
      throw new \BadMethodCallException('This action already has an ID.');
    }
    else {
      $this->id = $id;
    }

    return $this;
  }

  /**
   * Returns the website test results ID.
   *
   * @return int
   */
  public function getWebsiteTestResultsId() {
    return $this->websiteTestResultsId;
  }

  /**
   * Sets the website test results ID.
   *
   * @param int $website_test_results_id
   *
   * @return $this
   */
  public function setWebsiteTestResultsId($website_test_results_id) {
    $this->websiteTestResultsId = $website_test_results_id;

    return $this;
  }

  /**
   * Returns the action to take.
   *
   * @return string
   *   One of the self::ACTION_* constants.
   */
  public function getAction() {
    return $this->action;
  }

  /**
   * Sets the action to take.
   *
   * @param string $action
   *   One of the self::ACTION_* constants.
   *
   * @return $this
   */
  public function setAction($action) {
    $this->action = $action;

    return $this;
  }

  /**
   * Returns the URL.
   *
   * @return string
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Sets the URL.
   *
   * @param string $url
   *
   * @return $this
   */
  public function setUrl($url) {
    $url = Validator::validateUrl($url);
    if ($url === FALSE) {
      throw new \InvalidArgumentException(sprintf('%s is not a valid URL.', $url));
    }
    else {
      $this->url = $url;
    }

    return $this;
  }

  /**
   * Returns the timestamp.
   *
   * @return int
   *   A Unix timestamp.
   */
  public function getTimestamp() {
    return $this->timestamp;
  }

  /**
   * Sets the timestmap.
   *
   * @param int $timestamp
   *   A Unix timestamp.
   *
   * @return $this
   */
  public function setTimestamp($timestamp) {
    $this->timestamp = $timestamp;

    return $this;
  }

}
