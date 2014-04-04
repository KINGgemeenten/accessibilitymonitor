<?php
$count = 0;
require_once('nutch2quaillib.php');

// TODO: make collections configurable
$collection["nutch"] = "nutch";
$collection["quail"] = "phantomcore";

$unprocessedRecords = fetch_solr_data($collection["nutch"], "tested:0");
$solrresponse = handle_solr_response($unprocessedRecords);
while (list ($index, $data) = each($solrresponse)) {
  $count++;
  if (isset($data["tested"])) {
    if ($count < 100) {
      $url = $data["url"];
      print $url . "\n";
    }
  }
}
 

  
   

