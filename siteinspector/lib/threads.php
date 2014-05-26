<?php

class PhantomQuailWorker extends Thread {

  protected $urlObject;
  protected $phantomcore_name;
  protected $result;

  public function __construct($urlObject) {
    $this->urlObject = $urlObject;
    $this->phantomcore_name = get_setting('solr_phantom_corename');
  }

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
        $document = preprocessQuailResult($quailResult, $count);
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

  public function getResult() {
    return $this->result;
  }
}