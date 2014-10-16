<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Quail.
 */

namespace Triquanta\AccessibilityMonitor;

use Psr\Log\LoggerInterface;
use Solarium\Core\Client\Client;

/**
 * Provides a Quail test manager.
 */
class Quail implements QuailInterface {

  /**
   * The number of available CPU cores.
   *
   * @var int
   */
  protected $cpuCount;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The maximum test execution time.
   *
   * @var int|float
   *   The time limit in seconds.
   */
  protected $maxExecutionTime;

  /**
   * The Solr client.
   *
   * @var \Solarium\Core\Client\Client
   */
  protected $solrClient;

  /**
   * The storage manager.
   *
   * @var \Triquanta\AccessibilityMonitor\StorageInterface
   */
  protected $storage;

  /**
   * The number of concurrent Quail workers to use.
   *
   * @var int
   */
  protected $workerCount;

  // @todo
  protected $startTime;
  // @todo
  protected $elapsedTime;

  /**
   * The concurrent Quail workers.
   *
   * @var \Triquanta\AccessibilityMonitor\PhantomQuailWorker[]
   */
  protected $workers = array();

  /**
   * The Quail workers that have finished.
   *
   * @var \Triquanta\AccessibilityMonitor\PhantomQuailWorker[]
   */
  protected $finishedWorkers = array();

  /**
   * The Quail worker factory.
   *
   * @var \Triquanta\AccessibilityMonitor\QuailWorkerFactoryInterface
   */
  protected $workerFactory;


  /**
   * Constructs a new instance.
   *
   * @param \Triquanta\AccessibilityMonitor\StorageInterface $storage
   * @param \Solarium\Core\Client\Client $solr_client
   * @param \Psr\Log\LoggerInterface $logger
   * @param $quail_worker_factory \Triquanta\AccessibilityMonitor\QuailWorkerFactoryInterface
   * @param int|float $max_execution_time
   *   The time limit in seconds.
   * @param int $worker_count
   *   The amount of concurrent PhantomJS processes.
   * @param int $cpu_count
   *   The amount of available CPU cores.
   */
  public function __construct(StorageInterface $storage, Client $solr_client, LoggerInterface $logger, QuailWorkerFactoryInterface $quail_worker_factory, $max_execution_time, $worker_count, $cpu_count) {
    $this->cpuCount = $cpu_count;
    $this->logger = $logger;
    $this->maxExecutionTime = $max_execution_time;
    $this->solrClient = $solr_client;
    $this->storage = $storage;
    $this->workerCount = $worker_count;
    $this->workerFactory = $quail_worker_factory;

    $this->startTime = microtime(TRUE);
    $this->elapsedTime = 0;
  }

  /**
   * {@inheritdoc}
   */
  public function test() {
    while ($this->elapsedTime < $this->maxExecutionTime) {
      // Determine the new amount of workers based on the load.
      $this->updateWorkerCount();

      // Get the URLs to test.
      $urls = $this->getTestingUrls();

      // Create the workers.
      $this->workers = array();

      // Keys are website IDs, values are booleans that indicate whether there
      // are results for the websites.
      $cms_results = array();
      // Keys are website IDs, values are booleans that indicate whether there
      // are results for the websites.
      $google_pagespeed_results = array();

      foreach ($urls as $url) {
        // Get the website record from the database.
        $website = $this->storage->getWebsiteById($url->getWebsiteId());
        // Determine which tests should be done.
        if (!array_key_exists($url->getWebsiteId(), $cms_results)) {
          $cms_results[$url->getWebsiteId()] = $this->storage->countCmsTestResultsByWebsiteId($url->getWebsiteId());
        }
        if (!array_key_exists($url->getWebsiteId(), $google_pagespeed_results)) {
          $google_pagespeed_results[$url->getWebsiteId()] = $this->storage->countGooglePagespeedResultsByWebsiteId($url->getWebsiteId());
        }

        // Create a PhantomQuailWorker for each url.
        $worker = $this->workerFactory->createWorker($url, $website, $url->getId(), !$cms_results[$url->getWebsiteId()], !$google_pagespeed_results[$url->getWebsiteId()]);
        // First delete all documents from solr.
        $worker->deleteCasesFromSolr();
        // Now start the thread.
//        $worker->start();
        $worker->run();
        $this->workers[] = $worker;
      }
      // Add some debugging.
      $this->logger->debug('Prepare to send commit to solr.');

      $this->sendCommitToPhantomcoreSolr();


      $this->logger->debug('Commit to solr done.');

      // Process the finished workers.
      $this->processFinishedWorkers();

      // Break if there are no more targets.
      if (count($urls) === 0) {
        break;
      }

      $this->logger->debug('Workers activated, waiting.');
      // Join workers and put them in the finishedWorkers array.
      foreach ($this->workers as $worker) {
        $worker->join();
        $this->finishedWorkers[] = $worker;
      }
      $this->logger->debug('All workers joined, continuing');

      // Update the elapsed time.
      $oldElapsedTime = $this->elapsedTime;
      $this->elapsedTime = microtime(TRUE) - $this->startTime;

      // ProcessTime.
      $processTime = $this->elapsedTime - $oldElapsedTime;
      // Log to the console.
      $message = 'Analysis used ' . $processTime . ' seconds for ' . $this->workerCount . 'workers';
      $this->logger->info($message);
    }
    $this->logger->info('Elapsed time exceeded. Finishing.');
    // Process the finished workers.
    $this->processFinishedWorkers();
    $message = 'Total execution time: ' . $this->elapsedTime . ' seconds';
    $this->logger->info($message);
  }

  /**
   * Get the testing targets.
   *
   * @return \Triquanta\AccessibilityMonitor\Url[]
   */
  protected function getTestingUrls() {
    $urls = $this->storage->getUrlsByStatus(Url::STATUS_SCHEDULED, $this->workerCount);

    // Now loop the results, and set the urls to be processing.
    foreach ($urls as $url) {
      $url->setTestingStatus($url::STATUS_TESTING);
      $this->storage->saveUrl($url);
    }

    return $urls;
  }

  /**
   * Update the amount of workers based on the system load.
   */
  protected function updateWorkerCount() {
    // Update the worker count, so the server is optimally used.
    // If the load is below amount of cpu's - 1: increase the worker count
    // If the load is higher than the amount of cpu's + 1: decrease it.
    // We take the 1 minute average load.
    $load = sys_getloadavg();
    if ($load[0] < $this->cpuCount - 1 && $this->workerCount < $this->cpuCount) {
      $this->workerCount++;
      // @todo Why "< 2"?
      $this->logger->info(sprintf('Increasing the amount of PhantomJS workers to %s due to low load (< 2)', $this->workerCount));
    }
    else if ($load[0] > $this->cpuCount + 1 && $this->workerCount > 1) {
      $this->workerCount--;
      // @todo Why "> 5"?
      $this->logger->info(sprintf('Decreasing the amount of PhantomJS workers to %s due to high load (> 5)', $this->workerCount));
    }
  }

  /**
   * Process the finished workers.
   */
  protected function processFinishedWorkers() {
    // If there are finished workers, process the results and die.
    if (count($this->finishedWorkers)) {
      foreach ($this->finishedWorkers as $key => $finishedWorker) {
        $this->processQuailResult($finishedWorker);
        $this->processWappalyzerResults($finishedWorker);
        $this->processGooglePagespeed($finishedWorker);

        // Now unset the finished worker in the array.
        unset($this->finishedWorkers[$key]);
      }
    }
  }

  /**
   * Process the quail results.
   *
   * @param \Triquanta\AccessibilityMonitor\PhantomQuailWorker $finishedWorker
   */
  protected function processQuailResult(PhantomQuailWorker $finishedWorker) {
    $this->storage->saveUrl($finishedWorker->getQueueId(), $finishedWorker->getStatus(), $finishedWorker->getWebsiteCms(), $finishedWorker->getQuailFinalResults());
    // Set the last_analysis date.
    $time = time();
    $this->logger->debug('time: ' . $time);
    $finishedWorker->getWebsite()->setLastAnalysis($time);
    $this->storage->saveWebsite($finishedWorker->getWebsite());
  }

  /**
   * Store the cms in the website table.
   *
   * @param \Triquanta\AccessibilityMonitor\PhantomQuailWorker $finishedWorker
   */
  protected function processWappalyzerResults(PhantomQuailWorker $finishedWorker) {
    $websiteCms = $finishedWorker->getWebsiteCms();
    if ($websiteCms) {
      $finishedWorker->getUrl()->setCms($websiteCms);
      $this->storage->saveUrl($finishedWorker->getUrl());
    }
  }

  /**
   * Store the google pagespeed results.
   *
   * @param \Triquanta\AccessibilityMonitor\PhantomQuailWorker $finishedWorker
   */
  protected function processGooglePagespeed(PhantomQuailWorker $finishedWorker) {
    $wid = $finishedWorker->getWid();
    // If there is a result, insert it.
    $pagespeedResult = $finishedWorker->getPageSpeedResult();
    if ($pagespeedResult) {
      $finishedWorker->getUrl()->setGooglePagespeedResult($pagespeedResult);
      $this->storage->saveUrl($finishedWorker->getUrl());
    }
  }

  /**
   * Send a commit to phantomcore solr.
   */
  protected function sendCommitToPhantomcoreSolr() {
    // Get an update query instance.
    $update = $this->solrClient->createUpdate();

    $update->addCommit();

    // This executes the query and returns the result.
    $this->solrClient->update($update);
  }

} 
