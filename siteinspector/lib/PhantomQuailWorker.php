<?php

class PhantomQuailWorker extends Thread {

  protected $urlObject;
  protected $phantomcore_name;
  protected $result;

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
    // Create an array for all documents.
    $documents = array();
    foreach(preg_split("/((\r?\n)|(\r\n?))/", $output) as $line){
      if ($line != '' && preg_match("/^{/", $line)) {
        // do stuff with $line
        $quailResult = json_decode($line);
        // Process the quail result to a json object which can be send to solr.
        $document = $this->preprocessQuailResult($quailResult, $count);
        if ($document) {
          // Add the documents to the document list in solr.
          $documents[] = $document;
          $count++;
        }
      }
    }
    // Now sent the result to Solr.
    postToSolr($documents, $this->phantomcore_name);

    // Update the result.
    $this->result = $this->urlObject->url_id;
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
    if (isset($quailResult->url) && $quailResult->url != '') {
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
      if (isset($quailResult->wcag) && ($quailResult->wcag != "")) {
        $wcag = json_decode($quailResult->wcag);
        $quailResult->applicationframework = "";
        $quailResult->techniques = "";
        while (list($applicationNr, $techniques) = each($wcag)) {
          $quailResult->applicationframework[] = $applicationNr;
          if (count($techniques) > 0) {
            foreach ($techniques as $technique) {
              foreach ($technique as $techniqueStr) {
                $thistechniques[] = $techniqueStr;
              }
            }
          }
        }
        $quailResult->techniques = array_unique($thistechniques);
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