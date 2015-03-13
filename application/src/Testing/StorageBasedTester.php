<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|StorageBasedTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Triquanta\AccessibilityMonitor\StorageInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a storage-based tester.
 */
class StorageBasedTester implements TesterInterface
{

    /**
     * The result storage.
     *
     * @var \Triquanta\AccessibilityMonitor\StorageInterface
     */
    protected $resultStorage;

    /**
     * The tester.
     *
     * @var \Triquanta\AccessibilityMonitor\Testing\TesterInterface
     */
    protected $tester;

    /**
     * Creates a new instance.
     *
     * @param \Triquanta\AccessibilityMonitor\Testing\TesterInterface $tester
     * @param \Triquanta\AccessibilityMonitor\StorageInterface $resultStorage
     */
    public function __construct(
      TesterInterface $tester,
      StorageInterface $resultStorage
    ) {
        $this->resultStorage = $resultStorage;
        $this->tester = $tester;
    }

    public function run(Url $url)
    {
        // @todo Add a check to prevent DOS effects taking place on the website
        //   under test. See https://support.triquanta.nl/issues/16918.
        $this->tester->run($url);
        $this->resultStorage->saveUrl($url);
    }

}
