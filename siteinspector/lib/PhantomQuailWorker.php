<?php

class PhantomQuailWorker extends Thread {

  protected $urlObject;
  protected $websiteObject;
  protected $phantomcore_name;
  protected $result;

  // Store the website cms.
  protected $websiteCms;
  // Store the website id, if the website analysis has been done.
  protected $wid;

  protected $status = STATUS_TESTING;

  // Array to hold all the raw results per test.
  protected $rawQuailResults = array();
  // Array to hold all the results per test.
  protected $quailResults = array();
  // Array to hold all the cases of all tests together with the result.
  protected $quailCases = array();
  // Array to hold the final results per wcag thingy.
  protected $quailFinalResult = array();

  // Store the queueid of the object.
  protected $queueId;

  public function __construct($urlObject, $websiteObject, $queueId) {
   $this->urlObject = $urlObject;
    $this->websiteObject = $websiteObject;
    // Fill the websiteCms if present.
    if (isset($websiteObject->cms) && $websiteObject->cms != '') {
      $this->websiteCms = $websiteObject->cms;
    }
    $this->queueId = $queueId;
    $this->phantomcore_name = get_setting('solr_phantom_corename');
  }

  /**
   * Run function. This function is executed when the method start is executed.
   */
  public function run() {
    // We need to include the autoloaders here.
    // This is because this is a thread, and there
    // are different rules.
    // For this perticular case see:
    // https://github.com/krakjoe/pthreads/issues/68
    // Composer autoloader.
    require( __DIR__ . '/../vendor/autoload.php');
    require( __DIR__ . '/../settings.php');
    // If the website has not yet a cms detected, perform the detection here.
    $this->detectCms();
    $this->analyzeQuail();
  }

  /**
   * Detect the cms of the main website.
   */
  protected function detectCms() {
    $phantomjsExecutable = get_setting('phantomjs_executable');
    $testUrl = $this->websiteObject->url;
    // Add http, if not present.
    if (! preg_match('/^http/', $testUrl)) {
      $testUrl = 'http://' . $testUrl;
    }
    $phantomDir = __DIR__ . '/../';
    $command = $phantomjsExecutable . ' --ignore-ssl-errors=yes ' . $phantomDir . 'node_modules/phantalyzer/phantalyzer.js ' . $testUrl;
    $output = shell_exec($command);
    $preg_split = preg_split("/((\r?\n)|(\r\n?))/", $output);
    $detectedAppsArray = array();
    foreach ($preg_split as $line) {
      // Check the line detectedApps.
      if ($line != '' && preg_match("/^detectedApps/", $line)) {
        $detectedApps = str_replace('detectedApps: ', '', $line);
        $detectedAppsArray = explode('|', $detectedApps);
      }
      // Also check the generator.
      if ($line != '' && preg_match('/<meta name="generator" content="([A-Za-z\s]*)"/', $line, $matches)) {
        $generator = $matches[1];
      }
    }
    // If a generator is found, add it to the detected apps.
    if (isset($generator)) {
      // Add the generator to the detected apps.
      $detectedAppsArray[] = $generator;
    }
    $this->websiteCms = implode('|', $detectedAppsArray);
    // Fill the wid property in order to trigger the save in the quailTester.
    $this->wid = $this->websiteObject->wid;
  }

  /**
   * Get the website id.
   *
   * This property only returns result, if a succesful cms detection has been performed.
   *
   * @return mixed
   */
  public function getWid() {
    return $this->wid;
  }

  /**
   * Get the website cms.
   *
   * @return mixed
   */
  public function getWebsiteCms() {
    return $this->websiteCms;
  }

  /**
   * Perform the quail analysis.
   */
  protected function analyzeQuail() {
    // First delete all solr records for this url.
    $this->deleteCasesFromSolr();

    $url = $this->urlObject->full_url;
    // Execute phantomjs.
    // First get the path to the phantomjs executable.
    $phantomjsExecutable = get_setting('phantomjs_executable');
    $phantomDir = __DIR__ . '/../';

    $command = $phantomjsExecutable . ' --ignore-ssl-errors=yes ' . $phantomDir . 'phantomquail.js ' . $url;
    try {
      $phantomjsTimout = get_setting('phantomjs_timeout', 10);
      // Print some debug info.
      $this->debugMessage('Starting phantomjs');
      $output = $this->execTimeout($command, $phantomjsTimout);
      $this->debugMessage('Phantomjs executed succesfully.');
      // Now process the results from quail.
      // We have to generate a unique id later.
      // In order to do this, we count the results, so it can be
      // included in the unique id.
      $count = 0;

      // Create an array for all quail results.
      // We need to use this 'in between array' because in threads object variable
      // arrays don't allow array_push or [].
      $quailResults = array();
      // Create an array for
      foreach (preg_split("/((\r?\n)|(\r\n?))/", $output) as $line) {
        if ($line != '' && preg_match("/^{/", $line)) {
          // do stuff with $line
          $quailResult = json_decode($line);

          // Add the url to the quailResult.
          $quailResult->url = $this->urlObject->full_url;


          // Process the quail result to a json object which can be send to solr.
          $processedResult = $this->preprocessQuailResult($quailResult, $count);
          if ($processedResult) {
            // Add the documents to the document list in solr.
            $quailResults[] = $processedResult;
            $count++;
          }
        }
      }
      $this->rawQuailResults = $quailResults;
      $this->processQuailResults();

      // Now send the case results to solr.
      $this->debugMessage('Sending results to Solr.');
      $this->sendCaseResultsToSolr();
      $this->debugMessage('Results sended to Solr.');

      // Update the result.
      $this->result = $this->urlObject->url_id;
      // Set the status to tested.
      $this->status = STATUS_TESTED;
    } catch (Exception $e) {
      // If there is an exception, probably phantomjs timed out.
      $this->status = STATUS_ERROR;
      $this->debugMessage($e->getMessage());
    }
  }

  /**
   * Delete all the cases from solr.
   */
  public function deleteCasesFromSolr() {
    // Create the client to solr.
    $phantomcore_config = get_setting('solr_phantom');
    $client = new Solarium\Client($phantomcore_config);

    // Get a delete query.
    $update = $client->createUpdate();

    // add the delete query and a commit command to the update query
    $escaped_string = escapeUrlForSolr($this->urlObject->full_url);
    $solrQuery = 'url_id:' . $escaped_string;

    $update->addDeleteQuery($solrQuery);

    // this executes the query and returns the result
    $result = $client->update($update);

  }

  /**
   * Process the quail results.
   */
  protected function processQuailResults() {
    // Loop the quail results to create the different arrays.
    $quailResults = array();
    $quailCases = array();
    $quailFinalResult = array();
    foreach ($this->rawQuailResults as $result) {
      // Add the technologies.
      if (isset($this->websiteCms)) {
        $technologies = explode('|', $this->websiteCms);
        if (count($technologies) > 0) {
          $result->technologies = $technologies;
        }
      }
      // First do the quailResults.
      $quailResults[] = $result;
      // Expand on case
      if (isset($result->cases) && count($result->cases) > 0 && is_array($result->cases)) {
        foreach ($result->cases as $case) {
          $caseItem = $result;
          // Unset the cases.
          unset($caseItem->cases);
          $caseItem->status = $case->status;
          if (isset($case->selector)) {
            $caseItem->selector = $case->selector;
          }
          if (isset($case->html)) {
            $caseItem->html = $case->html;
          }
          // Add the quailCase.
          $quailCases[] = $caseItem;

          if (isset($caseItem->applicationframework)) {
            // Add the case to the final result.
            foreach ($caseItem->applicationframework as $wcagItem) {
              // Add the case to the specific wcag item.
              if (!isset($quailFinalResult[$wcagItem]['cases'])) {
                $quailFinalResult[$wcagItem]['cases'] = array();
              }
              $quailFinalResult[$wcagItem]['cases'][] = $caseItem;
              // Increment counters on the status.
              if (!isset($quailFinalResult[$wcagItem]['statuses'][$caseItem->status])) {
                $quailFinalResult[$wcagItem]['statuses'][$caseItem->status] = 0;
              }
              $quailFinalResult[$wcagItem]['statuses'][$caseItem->status]++;
            }
          }

        }
      }
    }
    $this->quailCases = $quailCases;
    $this->quailFinalResult = $quailFinalResult;
  }

  /**
   * Send all the cases to solr.
   */
  protected function sendCaseResultsToSolr() {
    // First check if there are results to send.
    if (count($this->quailCases)) {
      // Create the client to solr.
      $phantomcore_config = get_setting('solr_phantom');
      $client = new Solarium\Client($phantomcore_config);

      // Create an update query.
      $updateQuery = $client->createUpdate();

      // Create an array of documents.
      $docs = array();
      // Now start adding the cases.
      foreach ($this->quailCases as $case) {
        $doc = $this->caseToSolrDocument($updateQuery, $case);
        if ($doc) {
          $docs[] = $doc;
        }
      }
      // add the documents and a commit command to the update query
      $updateQuery->addDocuments($docs);

      // this executes the query and returns the result.
      // TODO: catch exceptions.
      $result = $client->update($updateQuery);
    }
  }

  /**
   * Create a solr document from a case.
   *
   * @param $updateQuery
   * @param $case
   *
   * @return $doc
   *   Solr document
   */
  protected function caseToSolrDocument($updateQuery, $case) {
    // Create a solr document.
    if (property_exists($case, 'status')) {
      $doc = $updateQuery->createDocument();
      $doc->id = $this->createCaseSolrId($case);
      $doc->content = json_encode($case);
      $doc->url = $case->url;
      $doc->url_id = $case->url_id;
      $doc->url_main = $case->url_main;
      $doc->url_sub = $case->url_sub;
      if (property_exists($case, 'html')) {
        $doc->element = $case->html;
      }
      if (property_exists($case, 'title')) {
        $doc->name = $case->title->en;
      }
      if (property_exists($case, 'applicationframework')) {
        $doc->applicationframework = $case->applicationframework;
      }
      if (property_exists($case, 'techniques')) {
        $doc->techniques = $case->techniques;
      }
      if (property_exists($case, 'technologies')) {
        $doc->technologies = $case->technologies;
      }
      $doc->tags = $case->tags;
      $doc->testability = $case->testability;
      $doc->test_result = $case->status;
      $doc->testtype = $case->type;
      $doc->severity = $case->testability;

      // Add document type.
      $doc->document_type = 'case';

      return $doc;
    }
    return FALSE;
  }

  /**
   * Create a solr id for a case.
   *
   * @param $case
   *
   * @return string
   *   Case id.
   */
  protected function createCaseSolrId($case) {
    return 'case_' . $case->id . '_' . $case->url_id;
  }

  /**
   * Preprocess quail result for sending to solr.
   *
   * TODO: Solr should have a class, in which we can do all these things.
   *
   * @param $quailResult
   * @param $count
   *
   * @return mixed
   */
  protected function preprocessQuailResult($quailResult, $count) {
    if (isset($quailResult->url) && $quailResult->url != '' && isset($quailResult->cases) && count($quailResult->cases) > 0) {
      $quailResult->url_main = "";
      $quailResult->url_sub = "";
      $urlarr = parse_url($quailResult->url);
      $fqdArr = explode(".", $urlarr["host"]);
      if (count($fqdArr) > 2) {
        $partcount = count($fqdArr);
        $quailResult->url_main = $fqdArr[$partcount - 2] . "." . $fqdArr[$partcount - 1];
      }
      else {
        $quailResult->url_main = $urlarr["host"];
      }
      $quailResult->url_sub = $urlarr["host"];

      // Add the escaped url in order to be able to delete.
      $escaped_url = escapeUrlForSolr($quailResult->url);
      $quailResult->url_id = $escaped_url;

      // Create a unique id.
      $quailResult->id = time() . $count;
      if (isset($quailResult->guidelines) && !empty($quailResult->guidelines)) {
        $quailResult->applicationframework = array();
        $quailResult->techniques = array();
        if (isset($quailResult->guidelines->wcag)) {
          foreach ($quailResult->guidelines->wcag as $wcagCode => $wcagItem) {
            $quailResult->applicationframework[] = $wcagCode;
            // Add the techniques.
            if (isset($wcagItem->techniques) && count($wcagItem->techniques) > 0 && is_array($wcagItem->techniques)) {
              foreach ($wcagItem->techniques as $technique) {
                $quailResult->techniques[] = $technique;
              }
            }
          }
        }

        $quailResult->techniques = array_unique($quailResult->techniques);
      }
      return $quailResult;
    }
    return FALSE;
  }

  /**
   * Execute a command and return it's output. Either wait until the command exits or the timeout has expired.
   *
   * This function is based on the following solution:
   * http://blog.dubbelboer.com/2012/08/24/execute-with-timeout.html
   *
   * @param string $cmd     Command to execute.
   * @param number $timeout Timeout in seconds.
   * @return string Output of the command.
   * @throws \Exception
   */
  function execTimeout($cmd, $timeout) {
    $timedOut = true;
    // File descriptors passed to the process.
    $descriptors = array(
      0 => array('pipe', 'r'),  // stdin
      1 => array('pipe', 'w'),  // stdout
      2 => array('pipe', 'w')   // stderr
    );

    $this->debugMessage('Openining processes');
    // Start the process.
    $process = proc_open('exec ' . $cmd, $descriptors, $pipes);

    if (!is_resource($process)) {
      throw new \Exception('Could not execute process');
    }

    // Set the stdout stream to none-blocking.
    stream_set_blocking($pipes[1], 0);

    // Turn the timeout into microseconds.
    $timeout = $timeout * 1000000;

    // Output buffer.
    $buffer = '';

    // While we have time to wait.
    while ($timeout > 0) {
      $start = microtime(true);

      // Wait until we have output or the timer expired.
      $read  = array($pipes[1]);
      $other = array();
      $this->debugMessage('Before stream_select');
      stream_select($read, $other, $other, 0, $timeout);

      // Get the status of the process.
      // Do this before we read from the stream,
      // this way we can't lose the last bit of output if the process dies between these functions.
      $status = proc_get_status($process);

      $this->debugMessage('Getting stream content.');
      // Read the contents from the buffer.
      // This function will always return immediately as the stream is none-blocking.
      $buffer .= stream_get_contents($pipes[1]);
      $this->debugMessage('Buffer filled');

      if (!$status['running']) {
        $timedOut = false;
        // Break from this loop if the process exited before the timeout.
        break;
      }

      // Subtract the number of microseconds that we waited.
      $timeout -= (microtime(true) - $start) * 1000000;
    }

    if ($timedOut) {
      throw new \Exception('Operation timed out');
    }

    // Check if there were any errors.
    $errors = stream_get_contents($pipes[2]);

    if (!empty($errors)) {
      throw new \Exception($errors);
    }

    // Kill the process in case the timeout expired and it's still running.
    // If the process already exited this won't do anything.
    $this->debugMessage('Terminating process');
    proc_terminate($process, 9);
    $this->debugMessage('Process terminated');

    // Close all streams.
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    $this->debugMessage('Pipes closed');
    proc_close($process);

    $this->debugMessage('Process closed');

    return $buffer;
  }

  /********************************
   * GETTER AND SETTERS.
   ********************************/

  /**
   * @param mixed $queueId
   */
  public function setQueueId($queueId) {
    $this->queueId = $queueId;
  }

  /**
   * @return mixed
   */
  public function getQueueId() {
    return $this->queueId;
  }

  /**
   * Return result data, so it can be processed further.
   *
   * @return mixed
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * Get status.
   *
   * @return int
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * Get the website id of the url.
   *
   * @return mixed
   */
  public function getWebsiteId() {
    return $this->urlObject->wid;
  }



  /**
   * Print a debug message.
   *
   * @param $message
   */
  public function debugMessage($message) {
    $debug  = get_setting('debug', FALSE);
    if ($debug) {
      print $this->urlObject->url_id . ': ' . $message . "\n";
    }
  }
}