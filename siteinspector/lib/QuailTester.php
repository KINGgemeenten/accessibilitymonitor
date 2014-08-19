<?php
/**
 * User: jur
 * Date: 26-05-14
 * Time: 16:43
 */

class QuailTester {

  protected $pdo;
  protected $maxTime;
  protected $workerCount;

  protected $startTime;
  protected $elapsedTime;

  protected $workers = array();
  protected $finishedWorkers = array();


  /**
   * Construct a quail tester.
   *
   * @param float $maxTime
   *   The maximum time a the tester may run.
   * @param int $workerCount
   *   Amount of concurrent processes of phantomjs.
   * @param PDO $pdo
   *   PDO object for doing database queries.
   */
  public function __construct($maxTime, $workerCount, PDO $pdo) {
    $this->maxTime = $maxTime;
    $this->pdo = $pdo;
    $this->workerCount = $workerCount;
    $this->startTime = microtime(TRUE);
    $this->elapsedTime = 0;
  }

  /**
   * Test url's.
   */
  public function test() {
    while ($this->elapsedTime < $this->maxTime) {
      // Get the url's to test.
      $urls = $this->getTestingUrls();

      // Create the workers.
      $this->workers = array();
      foreach ($urls as $url) {
        // Get the website record from the database.
        $website = $this->getWebsite($url->wid);
        // Determine which tests should be done.
        $testGooglePagespeed = $this->determinePerformTest(TEST_TYPE_GOOGLE_PAGESPEED, $url->wid);
        $testCms = $this->determinePerformTest(TEST_TYPE_WAPPALYZER, $url->wid);

        // Create a PhantomQuailWorker for each url.
        $worker = new PhantomQuailWorker($url, $website, $url->url_id, $testCms, $testGooglePagespeed);
        // First delete all documents from solr.
        $worker->deleteCasesFromSolr();
        // Now start the thread.
        $worker->start();
//        $worker->run();
        $this->workers[] = $worker;
      }
      // Add some debugging.
      $this->debugMessage('Prepare to send commit to solr.');

      $this->sendCommitToPhantomcoreSolr();


      $this->debugMessage('Commit to solr done.');

      // Process the finished workers.
      $this->processFinishedWorkers();

      // Break if there are no more targets.
      if (count($urls) === 0) {
        break;
      }

      $this->debugMessage('Workers activated, waiting.');
      // Join workers and put them in the finishedWorkers array.
      foreach ($this->workers as $worker) {
        $worker->join();
        $this->finishedWorkers[] = $worker;
      }
      $this->debugMessage('All workers joined, continuing');

      // Update the elapsed time.
      $oldElapsedTime = $this->elapsedTime;
      $this->elapsedTime = microtime(TRUE) - $this->startTime;

      // ProcessTime.
      $processTime = $this->elapsedTime - $oldElapsedTime;
      // Log to the console.
      $message = 'Analysis used ' . $processTime . ' seconds for ' . $this->workerCount . 'workers';
      $this->log($message);
    }
    $this->log('Elapsed time exceeded. Finishing.');
    // Process the finished workers.
    $this->processFinishedWorkers();
    $message = 'Total execution time: ' . $this->elapsedTime . ' seconds';
    $this->log($message);
  }

  /**
   * Get the testing targets.
   *
   * @return mixed
   */
  protected function getTestingUrls() {
    $query = $this->pdo->prepare("SELECT * FROM urls WHERE status=:status ORDER BY priority ASC LIMIT " . $this->workerCount);
    $query->execute(array(
        'status' => STATUS_SCHEDULED,
      ));
    $results = $query->fetchAll(PDO::FETCH_OBJ);

    // Now loop the results, and set the urls to be processing.
    foreach ($results as $result) {
      $query = $this->pdo->prepare("UPDATE urls SET status=:status WHERE url_id=:url_id");
      $query->execute(
        array(
          'status' => STATUS_TESTING,
          'url_id' => $result->url_id,
        )
      );
    }
    return $results;
  }

  /**
   * Get the website for an url.
   *
   * @param $wid
   *
   * @return mixed
   */
  protected function getWebsite($wid) {
    $query = $this->pdo->prepare("SELECT * FROM website WHERE wid=:wid");
    $query->execute(array(
        'wid' => $wid,
      ));
    $result = $query->fetch(PDO::FETCH_OBJ);
    return $result;
  }

  /**
   * Determine if a test should be done.
   *
   * @param $testType
   * @param $wid
   */
  protected function determinePerformTest($testType, $wid) {
    $query = $this->pdo->prepare("SELECT COUNT(*) FROM test_results WHERE type=:type and wid=:wid");
    $query->execute(array(
        'wid' => $wid,
        'type' => $testType,
      ));
    $result = $query->fetch(PDO::FETCH_NUM);
    if ($result[0] > 0) {
      return FALSE;
    }
    return TRUE;

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
   * @param $finishedWorker
   */
  protected function processQuailResult($finishedWorker) {
    // Update the status of the url.
    $query = $this->pdo->prepare("UPDATE urls SET status=:status, cms=:cms, quail_result=:quail_result WHERE url_id=:url_id");
    $query->execute(
      array(
        'status' => $finishedWorker->getStatus(),
        'url_id' => $finishedWorker->getQueueId(),
        'cms' => $finishedWorker->getWebsiteCms(),
        'quail_result' => json_encode($finishedWorker->getQuailFinalResult()),
      )
    );
    // Set the last_analysis date.
    $time = time();
    $this->debugMessage('time: ' . $time);
    $query = $this->pdo->prepare("UPDATE website SET last_analysis=:time WHERE wid=:wid");
    $query->execute(
      array(
        'time' => $time,
        'wid'  => $finishedWorker->getWebsiteId(),
      )
    );
  }

  /**
   * Store the cms in the website table.
   *
   * @param $finishedWorker
   */
  protected function processWappalyzerResults($finishedWorker) {
    $wid = $finishedWorker->getWid();
    if (isset($wid)) {
      $query = $this->pdo->prepare("INSERT INTO test_results (wid,type,result) VALUES (:wid,:type,:result)");
      $query->execute(
        array(
          'wid' => $wid,
          'type' => TEST_TYPE_WAPPALYZER,
          'result' => $finishedWorker->getWebsiteCms(),
        )
      );
    }
  }

  /**
   * Store the google pagespeed results.
   *
   * @param $finishedWorker
   */
  protected function processGooglePagespeed($finishedWorker) {
    $wid = $finishedWorker->getWid();
    // If there is a result, insert it.
    $pagespeedResult = $finishedWorker->getPageSpeedResult();
    if ($pagespeedResult) {
      $query = $this->pdo->prepare("INSERT INTO test_results (wid,type,result) VALUES (:wid,:type,:result)");
      $query->execute(
        array(
          'wid' => $wid,
          'type' => TEST_TYPE_GOOGLE_PAGESPEED,
          'result' => $pagespeedResult,
        )
      );
    }
  }


  /**
   * Log messages.
   *
   * @param $message
   */
  protected function log($message) {
    print __CLASS__ . ':' .  $message . "\n";
  }

  /**
   * Print a debug message.
   *
   * @param $message
   */
  public function debugMessage($message) {
    $debug  = get_setting('debug', FALSE);
    if ($debug) {
      print __CLASS__ . ':' . $message . "\n";
    }
  }

  /**
   * Send a commit to phantomcore solr.
   */
  protected function sendCommitToPhantomcoreSolr() {
    // Send a commit to solr.
    $phantomcore_config = get_setting('solr_phantom');
    $client = new Solarium\Client($phantomcore_config);

    // Get an update query instance.
    $update = $client->createUpdate();

    $update->addCommit();

    // This executes the query and returns the result.
    $result = $client->update($update);
  }
} 