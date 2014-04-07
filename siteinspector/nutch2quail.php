<?php
$count = 0;
require_once('nutch2quaillib.php');

$unprocessedRecords = fetch_solr_data($collection["nutch"], "tested:0");
$solrresponse = handle_solr_response($unprocessedRecords);
while (list ($index, $data) = each($solrresponse)) {
  $count++;
  if (isset($data["tested"])) {
    $url = $data["url"];
    print $url . "\n";
  }
}
