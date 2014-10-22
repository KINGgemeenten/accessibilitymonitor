<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Url.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Represents a URL for a website.
 */
class Url implements TestingStatusInterface {

  /**
   * The URL ID.
   *
   * @var int
   */
  protected $id;

  /**
   * The ID of the website the URL is for.
   *
   * @var int
   */
  protected $websiteId;

  /**
   * The URL itself.
   *
   * @var string
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
   * The current testing priority.
   *
   * @var int
   *   A lower value means a higher priority.
   */
  protected $priority;

  /**
   * The Quail test results.
   *
   * @var string
   *   Quail's JSON output.
   */
  protected $quailResult;

  /**
   * The Google PageSpeed test results.
   *
   * @var string
   */
  protected $googlePagespeedResult;

  /**
   * The CMS that powers this URL.
   *
   * @var string
   */
  protected $cms;

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
      throw new \BadMethodCallException('This URL already has an ID.');
    }
    else {
      $this->id = $id;
    }

    return $this;
  }

  /**
   * Returns the ID of the website this URL is for.
   *
   * @return int
   */
  public function getWebsiteId() {
    return $this->websiteId;
  }

  /**
   * Sets the URL's website ID.
   *
   * @param int $website_id
   *
   * @return $this
   */
  public function setWebsiteId($website_id) {
    if ($this->websiteId) {
      throw new \BadMethodCallException('This URL already has a website ID.');
    }
    else {
      $this->websiteId = $website_id;
    }

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
   * Get the main domain for this url.
   *
   * @return string
   */
  public function getMainDomain() {
    $urlarr = parse_url($this->url);
    $fqdArr = explode('.', $urlarr['host']);
    if (count($fqdArr) > 2) {
      $partcount = count($fqdArr);
      return $fqdArr[$partcount - 2] . '.' . $fqdArr[$partcount - 1];
    }
    // In the other case, it's just the host.
    return $urlarr['host'];
  }

  /**
   * Get the hostname of the url.
   *
   * @return mixed
   */
  public function getHostName() {
    $urlarr = parse_url($this->url);
    return $urlarr['host'];
  }

  /**
   * Sets the URL itself.
   *
   * @param string $url
   *
   * @return $this
   */
  public function setUrl($url) {
    if ($this->url) {
      throw new \BadMethodCallException('This URL already has a URL.');
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
   * Returns the testing priority.
   *
   * @return int
   *   A lower value means a higher priority.
   */
  public function getPriority() {
    return $this->priority;
  }

  /**
   * Sets the testing priority.
   *
   * @param int $priority
   *   A lower value means a higher priority.
   *
   * @return $this
   */
  public function setPriority($priority) {
    $this->priority = $priority;

    return $this;
  }

  /**
   * Returns the CMS that powers the URL.
   *
   * @return string
   */
  public function getCms() {
    return $this->cms;
  }

  /**
   * Sets the CMS that powers the URL.
   *
   * @param string $cms
   *
   * @return $this
   */
  public function setCms($cms) {
    $this->cms = $cms;

    return $this;
  }

  /**
   * Returns the Quail test results.
   *
   * @return string
   *   Quail's JSON output.
   */
  public function getQuailResult() {
    return $this->quailResult;
  }

  /**
   * Sets the Quail test results.
   *
   * @param string $result
   *
   * @return $this
   */
  public function setQuailResult($result) {
    $this->quailResult = $result;

    return $this;
  }

  /**
   * Returns the Google PageSpeed test results.
   *
   * @return string
   */
  public function getGooglePagespeedResult() {
    return $this->googlePagespeedResult;
  }

  /**
   * Sets the Google PageSpeed test results.
   *
   * @param string $result
   *
   * @return $this
   */
  public function setGooglePagespeedResult($result) {
    $this->googlePagespeedResult = $result;

    return $this;
  }

}
