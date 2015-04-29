<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Url.
 */

namespace Triquanta\AccessibilityMonitor;

use Triquanta\AccessibilityMonitor\Testing\TestingStatusInterface;

/**
 * Represents a URL for a website.
 */
class Url implements TestingStatusInterface
{

    /**
     * The URL ID.
     *
     * @var int
     */
    protected $id;

    /**
     * The time of the analysis.
     *
     * @var int
     *   A Unix timestamp.
     */
    protected $analysis;

    /**
     * The ID of the website test results the URL is for.
     *
     * @var int
     */
    protected $websiteTestResultsId;

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
     * The Quail test results.
     *
     * @var string
     *   Quail's JSON output, minus the cases.
     */
    protected $quailResult;

    /**
     * The Quail test result cases.
     *
     * @var array[]
     */
    protected $quailResultCases = [];
    /**
     * The queue name.
     *
     * @var string
     */
    protected $queueName;

    /**
     * The Google PageSpeed test results.
     *
     * @var string
     */
    protected $googlePageSpeedResult;

    /**
     * The CMS that powers this URL.
     *
     * @var string
     */
    protected $cms;

    /**
     * Whether this is a website's root URL.
     *
     * @var bool
     */
    protected $isRoot = false;

    /**
     * Returns the URL ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the URL ID.
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        if ($this->id) {
            throw new \BadMethodCallException('This URL already has an ID.');
        } else {
            $this->id = $id;
        }

        return $this;
    }

    /**
     * Returns the ID of the website test results this URL is for.
     *
     * @return int
     */
    public function getWebsiteTestResultsId()
    {
        return $this->websiteTestResultsId;
    }

    /**
     * Sets the URL's website test results ID.
     *
     * @param int $website_test_results_id
     *
     * @return $this
     */
    public function setWebsiteTestResultsId($website_test_results_id)
    {
        if ($this->websiteTestResultsId) {
            throw new \BadMethodCallException('This URL already has a website test results ID.');
        } else {
            $this->websiteTestResultsId = $website_test_results_id;
        }

        return $this;
    }

    /**
     * Returns the URL.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Sets the URL itself.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $url = Validator::validateUrl($url);
        if ($this->url) {
            throw new \BadMethodCallException('This URL already has a URL.');
        } elseif ($url === false) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid URL.',
              $url));
        } else {
            $this->url = $url;
        }

        return $this;
    }

    /**
     * Get the main domain for this url.
     *
     * @return string
     */
    public function getMainDomain()
    {
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
    public function getHostName()
    {
        $urlarr = parse_url($this->url);

        return $urlarr['host'];
    }

    /**
     * Returns the current testing status.
     *
     * @return int
     *   One of the self::STATUS_* constants.
     */
    public function getTestingStatus()
    {
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
    public function setTestingStatus($testing_status)
    {
        $this->testingStatus = $testing_status;

        return $this;
    }

    /**
     * Returns the CMS that powers the URL.
     *
     * @return string
     */
    public function getCms()
    {
        return $this->cms;
    }

    /**
     * Sets the CMS that powers the URL.
     *
     * @param string $cms
     *
     * @return $this
     */
    public function setCms($cms)
    {
        $this->cms = $cms;

        return $this;
    }

    /**
     * Returns the Quail test results.
     *
     * @return string
     *   Quail's JSON output.
     */
    public function getQuailResult()
    {
        return $this->quailResult;
    }

    /**
     * Sets the Quail test results.
     *
     * @param string $result
     *
     * @return $this
     */
    public function setQuailResult($result)
    {
        $this->quailResult = $result;

        return $this;
    }

    /**
     * Returns the Quail test result cases.
     *
     * @return array[]
     */
    public function getQuailResultCases()
    {
        return $this->quailResultCases;
    }

    /**
     * Sets the Quail test result cases.
     *
     * @param array[] $cases
     *
     * @return $this
     */
    public function setQuailResultCases(array $cases)
    {
        $this->quailResultCases = $cases;

        return $this;
    }

    /**
     * Returns the Google PageSpeed test results.
     *
     * @return string
     */
    public function getGooglePageSpeedResult()
    {
        return $this->googlePageSpeedResult;
    }

    /**
     * Sets the Google PageSpeed test results.
     *
     * @param string $result
     *
     * @return $this
     */
    public function setGooglePageSpeedResult($result)
    {
        $this->googlePageSpeedResult = $result;

        return $this;
    }

    /**
     * Returns the time of the analysis.
     *
     * @return int
     *   A Unix timestamp.
     */
    public function getAnalysis()
    {
        return $this->analysis;
    }

    /**
     * Sets the time of the analysis.
     *
     * @param int $analysis
     *   A Unix timestamp.
     *
     * @return $this
     */
    public function setAnalysis($analysis)
    {
        $this->analysis = $analysis;

        return $this;
    }

    /**
     * Sets whether this URL is a root URL.
     *
     * @param bool $is_root
     *
     * @return $this
     */
    public function setRoot($is_root = true)
    {
        $this->isRoot = $is_root;

        return $this;
    }

    /**
     * Returns whether this URL is a root URL.
     *
     * @return int
     *   A Unix timestamp.
     */
    public function isRoot()
    {
        return $this->isRoot;
    }

    /**
     * Sets the queue name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setQueueName($name)
    {
        $this->queueName = $name;

        return $this;
    }

    /**
     * Returns the queue name.
     *
     * @return string
     */
    public function getQueueName()
    {
        return $this->queueName;
    }

}
