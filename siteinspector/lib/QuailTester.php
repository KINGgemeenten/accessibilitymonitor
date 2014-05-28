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
//        $worker->start();
        $worker->run();
        $this->workers[] = $worker;
      }

      // Process the finished workers.
      $this->processFinishedWorkers();

      // Break if there are no more targets.
      if (count($targets) === 0) {
        break;
      }

      // Join workers and put them in the finishedWorkers array.
      foreach ($this->workers as $worker) {
        $worker->join();
        $this->finishedWorkers[] = $worker;
      }

      // Update the elapsed time.
      $this->elapsedTime = microtime(TRUE) - $this->startTime;

      // Log to the console.
      $message = 'Analysis used ' . $this->elapsedTime . ' seconds for ' . $this->workerCount . 'workers';
      $this->log($message);
    }
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
    $query = $this->pdo->prepare("SELECT * FROM urls WHERE status=:status LIMIT " . $this->workerCount);
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
        $query = $this->pdo->prepare("UPDATE urls SET status=:status WHERE url_id=:url_id");
        $query->execute(
          array(
            'status' => STATUS_TESTED,
            'url_id' => $finishedWorker->getQueueId(),
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
    print $message . "\n";
  }

} 