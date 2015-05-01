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
    protected $name;

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
     * Returns the queue's name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets the queue's name.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setName($name)
    {
        if ($this->name) {
            throw new \BadMethodCallException('This queue already has a name.');
        } else {
            $this->name = $name;
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
