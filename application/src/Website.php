<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Website.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Represents a website.
 */
class Website implements TestingStatusInterface {

  /**
   * The URL ID.
   *
   * @var int
   */
  protected $id;

  /**
   * The URL itself.
   *
   * @var string
   *   The website's root URL. This does not have to be a domain root.
   */
  protected $url;

  /**
   * The current testing status.
   *
   * @var int
   *   One of the self::STATUS_* constants.
   */
  protected $testingStatus;

  /**
   * The time of the last analysis.
   *
   * @var int
   *   A Unix timestamp.
   */
  protected $lastAnalysis;

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
      throw new \BadMethodCallException('This website already has an ID.');
    }
    else {
      $this->id = $id;
    }

    return $this;
  }

  /**
   * Returns the website's URL.
   *
   * @return string
   *   The website's root URL. This does not have to be a domain root.
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Sets the website's URL.
   *
   * @param string $url
   *   The website's root URL. This does not have to be a domain root.
   *
   * @return $this
   */
  public function setUrl($url) {
    if ($this->url) {
      throw new \BadMethodCallException('This website already has a URL.');
    }
    elseif (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new \InvalidArgumentException(sprintf('%s is not a valid URL.', $url));
    }
    else {
      $this->url = $url;
    }

    return $this;
  }

  /**
   * Returns the current testing status.
   *
   * @return int
   *   One of the self::STATUS_* constants.
   */
  public function getTestingStatus() {
    return $this->testingStatus;
  }

  /**
   * Sets the current testing status.
   *
   * @param int $testing_status
   *   One of the self::STATUS_* constants.
   *
   * @return $this
   */
  public function setTestingStatus($testing_status) {
    $this->testingStatus = $testing_status;

    return $this;
  }

  /**
   * Returns the time of the last analysis.
   *
   * @return int
   *   A Unix timestamp.
   */
  public function getLastAnalysis() {
    return $this->lastAnalysis;
  }

  /**
   * Sets the time of the last analysis.
   *
   * @param int $last_analysis
   *   A Unix timestamp.
   *
   * @return $this
   */
  public function setLastAnalysis($last_analysis) {
    $this->lastAnalysis = $last_analysis;

    return $this;
  }

}
