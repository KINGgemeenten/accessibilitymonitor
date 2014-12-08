<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Actions.
 */

namespace Triquanta\AccessibilityMonitor;

use Psr\Log\LoggerInterface;

/**
 * Provides an actions manager.
 */
class Actions implements ActionsInterface {

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The storage.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface
   */
  protected $storage;

  /**
   * Constructs a new instance.
   *
   * @param \Triquanta\AccessibilityMonitor\StorageInterface $storage
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(StorageInterface $storage, LoggerInterface $logger) {
    $this->logger = $logger;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function addUrl(Url $url) {
    if (!$url->getWebsiteTestResultsId()) {
      $this->logger->warning(sprintf('Did not add a new URL for %s, because it has no website test results ID.', $url->getUrl()));
      return;
    }
    if ($this->storage->getUrlByUrlAndWebsiteTestResultsId($url->getUrl(), $url->getWebsiteTestResultsId())) {
      $this->logger->warning(sprintf('Did not add a new URL for %s, because it already exists for website test results %d.', $url->getUrl(), $url->getWebsiteTestResultsId()));
      return;
    }

    $url->setTestingStatus($url::STATUS_SCHEDULED);
    $this->storage->saveUrl($url);
    $this->logger->notice(sprintf('Added a new URL for %s with ID %d.', $url->getUrl(), $url->getId()));
  }

}
