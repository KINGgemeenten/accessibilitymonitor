<?php

class PhantomQuailWorker extends Thread {

  protected $urlObject;
  protected $phantomcore_name;
  protected $result;

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

  public function __construct($urlObject, $queueId) {
    $this->urlObject = $urlObject;
    $this->queueId = $queueId;
    $this->phantomcore_name = get_setting('solr_phantom_corename');
  }

  /**
   * Run function. This function is executed when the method start is executed.
   */
  public function run() {
    // First delete all solr records for this url.
    $escaped_string = escapeUrlForSolr($this->urlObject->full_url);
    $solrQuery = 'url_id:' . $escaped_string;
//    $solrQuery = '*:*';
    deleteFromSolr($solrQuery, $this->phantomcore_name);

    $url = $this->urlObject->full_url;
    // Execute phantomjs.
    // TODO: make the phantomjs path and the js file path configurable.
    $command = '/usr/bin/phantomjs --ignore-ssl-errors=yes /opt/siteinspector/phantomquail.js ' . $url;
    $output = shell_exec($command);
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
    foreach(preg_split("/((\r?\n)|(\r\n?))/", $output) as $line){
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
    // Now sent the result to Solr.
//    postToSolr($this->quailResults, $this->phantomcore_name);

    // Update the result.
    $this->result = $this->urlObject->url_id;
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
      // First do the quailResults.
      $quailResults[] = $result;
      // Expand on case
      foreach ($result->cases as $case) {
        $caseItem = $result;
        // Unset the cases.
        unset($caseItem->cases);
        $caseItem->status = $case->status;
        $caseItem->selector = $case->selector;
        // Add the quailCase.
        $quailCases[] = $caseItem;

        // Add the case to the final result.
        foreach ($case->applicationframework as $wcagItem) {
          // Add the case to the specific wcag item.
          if (! isset($quailFinalResult[$wcagItem]['cases'])) {
            $quailFinalResult[$wcagItem]['cases'];
          }
          $quailFinalResult[$wcagItem]['cases'][] = $case;
          // Increment counters on the status.
          if (!isset($quailFinalResult[$wcagItem]['statuses'][$case->status])) {
            $quailFinalResult[$wcagItem]['statuses'][$case->status] = 0;
          }
          $quailFinalResult[$wcagItem]['statuses'][$case->status]++;
        }

      }
    }
    $this->quailCases = $quailCases;
    $this->quailFinalResult = $quailFinalResult;
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
            foreach ($wcagItem->techniques as $technique) {
              $quailResult->techniques[] = $technique;
            }
          }
        }

        $quailResult->techniques = array_unique($quailResult->techniques);
      }
      if (count($quailResult->cases) > 1) {
        $oie = 'oei';
      }

      return $quailResult;
    }
    return FALSE;
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
}