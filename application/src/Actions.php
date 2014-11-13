<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Actions.
 */

namespace Triquanta\AccessibilityMonitor;

use Psr\Log\LoggerInterface;
use Solarium\Client;

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
   * The Solr client.
   *
   * @var \Solarium\Client
   */
  protected $solrClient;

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
   * @param \Solarium\Client $solr_client
   *   The Phantom core.
   * @param \Psr\Log\LoggerInterface $logger
   */
  public function __construct(StorageInterface $storage, Client $solr_client, LoggerInterface $logger) {
    $this->logger = $logger;
    $this->solrClient = $solr_client;
    $this->storage = $storage;
  }

  /**
   * {@inheritdoc}
   */
  public function addWebsite(Website $website) {
    if (!$website->getId() && !$this->storage->getWebsiteByUrl($website->getUrl())) {
      $website->setTestingStatus($website::STATUS_SCHEDULED);
      $this->storage->saveWebsite($website);
      $this->logger->notice(sprintf('Added a new website for URL %s with ID %d.', $website->getUrl(), $website->getId()));
    }
    else {
      $this->logger->warning(sprintf('Did not add a new website for %s, because it already exists.', $website->getUrl()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function rescanWebsite(Website $website) {
    foreach ($this->storage->getUrlsByWebsiteId($website->getId()) as $url) {
      $url->setTestingStatus($url::STATUS_SCHEDULED);
      $this->storage->saveUrl($url);
    }
    $website->setTestingStatus($website::STATUS_SCHEDULED);
    $this->storage->saveWebsite($website);
  }

  /**
   * {@inheritdoc}
   */
  public function excludeWebsite(Website $website) {
    $website->setTestingStatus($website::STATUS_EXCLUDED);
    $this->storage->saveWebsite($website);
    if ($website->getId()) {
      $this->deleteWebsiteResults($website);
      foreach ($this->storage->getUrlsByWebsiteId($website->getId()) as $url) {
        $this->excludeUrl($url);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function addUrl(Url $url) {
    if (!$url->getWebsiteId()) {
      $this->logger->warning(sprintf('Did not add a new URL for %s, because it has no website ID.', $url->getUrl()));
      return;
    }
    $website = $this->storage->getWebsiteById($url->getWebsiteId());
    if (is_null($website)) {
      $this->logger->warning(sprintf('Did not add a new URL for %s, because no website with ID %d exists.', $url->getUrl(), $url->getWebsiteId()));
      return;
    }
    if (strpos($url->getUrl(), $website->getUrl()) !== 0) {
      $this->logger->warning(sprintf('Did not add a new URL for %s, because it does not match that of the website it is associated with (%s).', $url->getUrl(), $website->getUrl()));
      return;
    }
    if (!$url->getId() && !$this->storage->getUrlByUrl($url->getUrl())) {
      $url->setTestingStatus($url::STATUS_SCHEDULED);
      $this->storage->saveUrl($url);
      $this->logger->notice(sprintf('Added a new URL for %s with ID %d.', $url->getUrl(), $url->getId()));
    }
    else {
      $this->logger->warning(sprintf('Did not add a new URL for %s, because it already exists.', $url->getUrl()));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function excludeUrl(Url $url) {
    $actual_url = $this->storage->getUrlByUrl($url->getUrl());
    if (!is_null($actual_url)) {
      $url = $actual_url;
    }
    $url->setTestingStatus($url::STATUS_EXCLUDED);
    $this->storage->saveUrl($url);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteWebsiteResults(Website $website) {
    // Get a delete query.
    $update = $this->solrClient->createUpdate();

    // add the delete query and a commit command to the update query
    $solrQuery = 'url_sub:' . $website->getUrl();

    $update->addDeleteQuery($solrQuery);

    $this->solrClient->update($update);

    // Now send a commit to solr.
    $update = $this->solrClient->createUpdate();
    $update->addCommit();
    $this->solrClient->update($update);
  }

}
