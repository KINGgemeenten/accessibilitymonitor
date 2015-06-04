<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\TestRun.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Represents a test run.
 */
class TestRun
{

    /**
     * The test run ID.
     *
     * @var int
     */
    protected $id;

    /**
     * The time the queue was created.
     *
     * @var int
     *   A Unix timestamp.
     */
    protected $created;

    /**
     * The group the test run belongs to.
     *
     * @var string
     */
    protected $group;

    /**
     * The current testing priority.
     *
     * @var int
     *   A lower value means a higher priority.
     */
    protected $priority = 0;

    /**
     * The test run's website test results ID.
     *
     * @var int
     *
     * @deprecated This property is specific to the Drupal client, and to URL's
     *   Solr storage. The client and Solr storage should be refactored to use
     *   test run IDs only.
     */
    protected $websiteTestResultsId;

    /**
     * Returns the test run's ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the test run's ID.
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        if ($this->id) {
            throw new \BadMethodCallException('This test run already has an ID.');
        } else {
            $this->id = $id;
        }

        return $this;
    }

    /**
     * Returns the test run's website test results ID.
     *
     * @return int
     *
     * @deprecated This method is specific to the Drupal client, and to URL's
     *   Solr storage. The client and Solr storage should be refactored to use
     *   test run IDs only.
     */
    public function getWebsiteTestResultsId()
    {
        return $this->websiteTestResultsId;
    }

    /**
     * Sets the test run's website test results ID.
     *
     * @param int $id
     *
     * @return $this
     *
     * @deprecated This method is specific to the Drupal client, and to URL's
     *   Solr storage. The client and Solr storage should be refactored to use
     *   test run IDs only.
     */
    public function setWebsiteTestResultsId($id)
    {
        if ($this->websiteTestResultsId) {
            throw new \BadMethodCallException('This test run already has a website test results ID.');
        } else {
            $this->websiteTestResultsId = $id;
        }

        return $this;
    }

    /**
     * Returns the testing priority.
     *
     * @return int
     *   A lower value means a higher priority.
     */
    public function getPriority()
    {
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
    public function setPriority($priority)
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * Returns the time the queue was created.
     *
     * @return int
     *   A Unix timestamp.
     */
    public function getCreated()
    {
        return $this->created;
    }

    /**
     * Sets the time the queue was created.
     *
     * @param int $created
     *   A Unix timestamp.
     *
     * @return $this
     */
    public function setCreated($created)
    {
        $this->created = $created;

        return $this;
    }

    /**
     * Returns the test run's group.
     *
     * @return string
     */
    public function getGroup()
    {
        return $this->group;
    }

    /**
     * Sets the test run's group.
     *
     * @param string $group
     *
     * @return $this
     */
    public function setGroup($group)
    {
        $this->group = $group;

        return $this;
    }

}
