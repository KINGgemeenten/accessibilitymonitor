<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Queue.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Represents a queue.
 */
class Queue
{

    /**
     * The queue ID.
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
     * The time a URL from this queue was last requested.
     *
     * @var int
     *   A Unix timestamp.
     */
    protected $lastRequest;

    /**
     * The ID of the website test results the queue is for.
     *
     * @var int
     */
    protected $websiteTestResultsId;

    /**
     * The current testing priority.
     *
     * @var int
     *   A lower value means a higher priority.
     */
    protected $priority = 0;

    /**
     * Returns the queue ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Sets the queue ID.
     *
     * @param int $id
     *
     * @return $this
     */
    public function setId($id)
    {
        if ($this->id) {
            throw new \BadMethodCallException('This queue already has an ID.');
        } else {
            $this->id = $id;
        }

        return $this;
    }

    /**
     * Returns the ID of the website test results this queue is for.
     *
     * @return int
     */
    public function getWebsiteTestResultsId()
    {
        return $this->websiteTestResultsId;
    }

    /**
     * Sets the queue's website test results ID.
     *
     * @param int $websiteTestResultsId
     *
     * @return $this
     */
    public function setWebsiteTestResultsId($websiteTestResultsId)
    {
        if ($this->websiteTestResultsId) {
            throw new \BadMethodCallException('This queue already has a website test results ID.');
        } else {
            $this->websiteTestResultsId = $websiteTestResultsId;
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
     * Returns the time a URL was last requested.
     *
     * @return int
     *   A Unix timestamp.
     */
    public function getLastRequest()
    {
        return $this->lastRequest;
    }

    /**
     * Sets the time a URL was last requested.
     *
     * @param int $lastRequest
     *   A Unix timestamp.
     *
     * @return $this
     */
    public function setLastRequest($lastRequest)
    {
        $this->lastRequest = $lastRequest;

        return $this;
    }

}
