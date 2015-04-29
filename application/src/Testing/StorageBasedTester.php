<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Testing|StorageBasedTester.
 */

namespace Triquanta\AccessibilityMonitor\Testing;

use Psr\Log\LoggerInterface;
use Triquanta\AccessibilityMonitor\StorageInterface;
use Triquanta\AccessibilityMonitor\Url;

/**
 * Provides a storage-based tester.
 *
 * Decorates another tester in order to save the results.
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
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

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
      LoggerInterface $logger,
      TesterInterface $tester,
      StorageInterface $resultStorage
    ) {
        $this->logger = $logger;
        $this->resultStorage = $resultStorage;
        $this->tester = $tester;
    }

    public function run(Url $url)
    {
        try {
            $outcome = $this->tester->run($url);
            if (!$outcome) {
                $this->logger->debug(sprintf('The results for %s were not saved, because testing failed or was not completed.', $url->getUrl()));
                return false;
            }

            $storageResult = $this->resultStorage->saveUrl($url);
            if ($storageResult) {
                $this->logger->debug(sprintf('The results for %s were saved.', $url->getUrl()));
            }
            else {
                $this->logger->emergency(sprintf('The results for %s were not properly saved, because of a storage error.', $url->getUrl()));
            }
            return $storageResult;
        }
        catch (\Exception $e) {
            $this->logger->emergency(sprintf('%s on line %d in %s when testing %s.', $e->getMessage(), $e->getLine(), $e->getFile(), $url->getUrl()));
            return false;
        }
    }

}
