<?php
function fetch_solr_data($collection, $query) {
  $result = FALSE;
  $http_post = FALSE;
  // TODO: make solr host configurable.
  $search_url = 'http://dev-crawler.wrl.org:8080/solr/' . $collection . '/select';
  $querystring = "stylesheet=&q=" . trim(urlencode($query)) . "&fl=*+score&qt=standard&rows=9999";
  $selecturl = "/?$querystring";
  $search_url .= $selecturl;
  $header[] = "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
  $header[] = "Accept-Language: en-us,en;q=0.5";
  $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $search_url); // set url to post to
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, 10);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
  curl_setopt($ch, CURLOPT_ENCODING, "");
  //curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_DNS_USE_GLOBAL_CACHE, 0);

  $data = curl_exec($ch);
  if (curl_errno($ch)) {
    throw new Exception(curl_error($ch));
  }
  else {
    curl_close($ch);
  }

  return $data;
}


function handle_solr_response($data) {
  if ($data) {
    $xml = simplexml_load_string($data);
    $results = array();
    foreach ($xml->result->doc as $story) {
      $resarray = array();
      try {
        foreach ($story as $item) {
          $name = $item->attributes()->name;
          $value = $item;
          $resarray["$name"] = "$value";
        } // end foreach
      } catch (Exception $e) {
        throw new Exception('Problem handling XML array.');
      }
      $results[] = $resarray;
    } // end foreach
  }
  else {
    $results = FALSE;
  }

  return $results;
}
