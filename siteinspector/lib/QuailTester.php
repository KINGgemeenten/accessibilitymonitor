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
      $targets = $this->getTestingTargets();

      // Create the workers.
      $this->workers = array();
      foreach ($targets as $target) {
        // Create a PhantomQuailWorker for each url.
        $worker = new PhantomQuailWorker($target, $target->url_id);
        // First delete all documents from solr.
        $worker->deleteCasesFromSolr();
        // Now start the thread.
        $worker->start();
//        $worker->run();
        $this->workers[] = $worker;
      }
      // Add some debugging.
      $this->log('Prepare to send commit to solr.');

      $this->sendCommitToPhantomcoreSolr();


      $this->log('Commit to solr done.');

      // Process the finished workers.
      $this->processFinishedWorkers();

      // Break if there are no more targets.
      if (count($targets) === 0) {
        break;
      }

      $this->log('Workers activated, waiting.');
      // Join workers and put them in the finishedWorkers array.
      foreach ($this->workers as $worker) {
        $worker->join();
        $this->finishedWorkers[] = $worker;
      }
      $this->log('All workers joined, continuing');

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
  protected function getTestingTargets() {
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
   * Process the finished workers.
   */
  protected function processFinishedWorkers() {
    // If there are finished workers, process the results and die.
    if (count($this->finishedWorkers)) {
      foreach ($this->finishedWorkers as $finishedWorker) {
        // Update the status of the url.
        $query = $this->pdo->prepare("UPDATE urls SET status=:status WHERE url_id=:url_id");
        $query->execute(
          array(
            'status' => $finishedWorker->getStatus(),
            'url_id' => $finishedWorker->getQueueId(),
          )
        );
        // Set the last_analysis date.
        $time = time();
        $query = $this->pdo->prepare("UPDATE website SET last_analysis=:time WHERE wid=:wid");
        $query->execute(
          array(
            'time' => $time,
            'wid' => $finishedWorker->getWebsiteId(),
          )
        );
      }
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