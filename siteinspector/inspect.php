<?php

include_once('lib/pid.php');

define('STATUS_SCHEDULED', 0);
define('STATUS_TESTING', 1);
define('STATUS_TESTED', 2);

// Execute main with the first argument.

main($argv[1]);



function main($operation = NULL) {
  // Check if the script is already running.
  // If so, exit.
  $pid = new pid('/tmp');
  if($pid->already_running) {
    echo "Already running.\n";
    exit;
  }
  else {
    echo "Running...\n";
  }

  // Main controller for the script.
  if (!isset($operation)) {
    print "inspect.php excepts 1 argument: check or update-sitelist\n";
  }

  switch ($operation) {
    case 'check':
      print "Performing tests\n";
      performTests();
      break;

    case 'update-sitelist':
      // Update the entries which should be tested.
      updateWebsiteEntries();
      // Update the url's which need to be tested.
      updateUrlFromNutch();
      break;
  }

}


/**
 * Update the website entries in the database;
 */
function updateWebsiteEntries() {
  $newWebsites = readWebsitesFile();


  // Get the database connection.
  $pdo = getDatabaseConnection();

  if (count($newWebsites)) {
    // Loop through the websites and check if there are new items.
    foreach ($newWebsites as $url) {
      // First try to load the website.
      $query = $pdo->prepare("SELECT * FROM website WHERE url=:url");
      $query->execute(array('url' => $url));
      if ($row = $query->fetch()) {
        // If the website is already present, update it.
        $update = $pdo->prepare("UPDATE website SET status=:status WHERE wid=:wid");
        $update->execute(
          array(
            'status' => STATUS_SCHEDULED,
            'wid'    => $row['wid']
          )
        );
      }
      else {
        // Insert a new entry.
        $sql = "INSERT INTO website (url,status) VALUES (:url,:status)";
        $insert = $pdo->prepare($sql);
        $insert->execute(
          array(
            'url'    => $url,
            'status' => STATUS_SCHEDULED
          ));
      }
    }
  }
}

/**
 * Update the list of url's to be tested from nutch.
 */
function updateUrlFromNutch() {
  // First get all websites which need to be retested.

  // Get the database connection.
  $pdo = getDatabaseConnection();
  $query = $pdo->prepare("SELECT * FROM website WHERE status=:status");
  $query->execute(array('status' => STATUS_SCHEDULED));
  $toBeTested = $query->fetchAll(PDO::FETCH_OBJ);

  if (count($toBeTested)) {
    foreach ($toBeTested as $entry) {
      // Now check if there are already url's available for this website.
      // If so, set the status to scheduled, otherwise get a bunch of url's from nutch solr.
      $query = $pdo->prepare("SELECT * FROM urls WHERE wid=:wid");
      $query->execute(array('wid' => $entry->wid));
      $results = $query->fetchAll(PDO::FETCH_OBJ);
      if (count($results)) {
        // Update the records.
        $update = $pdo->prepare("UPDATE urls SET status=:status WHERE wid=:wid");
        $update->execute(array(
            'status' => STATUS_SCHEDULED,
            'wid' => $entry->wid
          ));
      }
      else {
        // Get records from nutch.
        $nutchCore = get_setting('solr_nutch_corename');
        // Create the solr q query.
        // TODO: better baseurl detection.
        $baseUrl = str_replace('www.', '', $entry->url);
        $solrQuery = 'domain:' . $baseUrl;
        // Create the custom query string.
        $customParams = 'fl=url+score';
        $solrResponse = fetchSolrData($nutchCore, $solrQuery, $customParams);
        $parsedResponse = json_decode($solrResponse);
        $docs = $parsedResponse->response->docs;
        foreach ($docs as $doc) {
          // TODO: optimize the insert statement.
          // Insert a new entry.
          $sql = "INSERT INTO urls (wid,full_url,status) VALUES (:wid,:full_url,:status)";
          $insert = $pdo->prepare($sql);
          $insert->execute(array(
              'wid' => $entry->wid,
              'full_url' => $doc->url,
              'status' => STATUS_SCHEDULED
            ));
        }
      }
    }
  }
}

/**
 * Perform tests on urls.
 */
function performTests() {
  // Get an url to test.
  $pdo = getDatabaseConnection();
  // Get the batch_size so we don't do to much.
  $batch_size = get_setting('batch_size');
  // It seems that there is something wrong here.
  // I had problems using prepared for limit.
  // Normal parameter substitution doesn't work here.
  $query = $pdo->prepare("SELECT * FROM urls WHERE status=:status LIMIT " . intval($batch_size));
  $query->execute(array(
      'status' => STATUS_SCHEDULED,
    ));
  $results = $query->fetchAll(PDO::FETCH_OBJ);
  // TODO: implement testing functionality.
  // Now set all the urls to status testing, so if another process tries to do something
  // it can read that these url's will be tested.


  // Get the phantomcore corename.
  // We need this to send results to Solr.
  $phantomcore_name = get_setting('solr_phantom_corename');
  foreach ($results as $result) {
    // First delete all solr records for this url.
    $escaped_string = escapeUrlForSolr($result->full_url);
    $solrQuery = 'url_id:' . $escaped_string;
//    $solrQuery = '*:*';
    deleteFromSolr($solrQuery, $phantomcore_name);

    $url = $result->full_url;
    // Execute phantomjs.
    // TODO: make the phantomjs path and the js file path configurable.
    $command = '/usr/local/bin/phantomjs --ignore-ssl-errors=yes /opt/siteinspector/phantomquail.js ' . $url;
    $output = shell_exec($command);
    // Now process the results from quail.
    // We have to generate a unique id later.
    // In order to do this, we count the results, so it can be
    // included in the unique id.
    $count = 0;
    // Create an array for all documents.
    $documents = array();
    foreach(preg_split("/((\r?\n)|(\r\n?))/", $output) as $line){
      if ($line != '') {
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
    postToSolr($documents, $phantomcore_name);

    // Update the url entry.
    $query = $pdo->prepare("UPDATE urls SET status=:status WHERE url_id=:url_id");
    $query->execute(array(
        'status' => STATUS_TESTED,
        'url_id' => $result->url_id,
      ));
  }
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
function preprocessQuailResult($quailResult, $count) {
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

/**
 * Escape an url for solr.
 *
 * @param $url
 *
 * @return mixed
 */
function escapeUrlForSolr($url) {
  $escaped_url = str_replace(':', '_', $url);
  $escaped_url = str_replace('/', '_', $escaped_url);
  $escaped_url = str_replace('.', '_', $escaped_url);

  return $escaped_url;
}

/**
 * Read the websites file.
 *
 * @return array
 */
function readWebsitesFile() {
// Get the new websites.
  $newWebsites = array();

  $handle = fopen("websites.txt", "r+");
  if ($handle) {
    while (($line = fgets($handle)) !== FALSE) {
      $newWebsites[] = $line;
    }
    // After reading the files, truncate it.
    ftruncate($handle, 0);
    fclose($handle);
  }
  else {
    // error opening the file.
  }

  return $newWebsites;
}

/**
 * Get a database connection.
 *
 * TODO: This is not very nice, but for now it works.
 *
 * @return PDO
 */
function getDatabaseConnection() {
  static $pdo_object;
  if (! isset($pdo_object)) {
    $database = get_setting('mysql_database');
    $username = get_setting('mysql_username');
    $password = get_setting('mysql_password');
    $dsn = 'mysql:host=localhost;dbname=' . $database;
    $pdo_object = new PDO($dsn, $username, $password);
    $pdo_object->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }
  return $pdo_object;
}


/**
 * Get settings parsed from the ini file.
 *
 * @param $setting
 *
 * @return string
 */
function get_setting($setting) {
  static $global_settings;
  if (! isset($global_settings)) {
    $global_settings = parse_ini_file('settings.ini');
  }
  if (isset($global_settings[$setting])) {
    return $global_settings[$setting];
  }
  return '';
}

/**
 * Fetch solr data.
 *
 * TODO: this is a very ugly function.
 * This should be replaced with a proper way of doing when time is available.
 *
 * @param $collection
 * @param $query
 * @param $customParams
 *
 * @return mixed
 * @throws Exception
 */
function fetchSolrData($collection, $query, $customParams) {
  $result = FALSE;
  $http_post = FALSE;
  // TODO: make solr host configurable.
  $search_url = 'http://' . get_setting('solr_host') . ': ' . get_setting('solr_port') . '/solr/' . $collection . '/select';
  $querystring = "stylesheet=&q=" . trim(urlencode($query)) . "&" . $customParams . "&qt=standard&rows=" . get_setting('batch_rows') . "&wt=json";
  $selecturl = "/?$querystring";
  $search_url .= $selecturl;
  $header[] = "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,*/*;q=0.5";
  $header[] = "Accept-Language: en-us,en;q=0.5";
  $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $search_url); // set url to post to
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_TIMEOUT, 300);
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

/**
 * Post results to Solr.
 *
 * @param $documents
 * @param $core
 *
 * @return bool
 * @throws Exception
 */
function postToSolr($documents, $core) {

  $ch = curl_init();
  // TODO: make solr host configurable
  $post_url = 'http://' . get_setting('solr_host') . ': ' . get_setting('solr_port') . '/solr/' . $core . '/update?commit=true';
  // Create an array of jason docs.
  $json_docs = array();
  foreach ($documents as $doc) {
    $json_docs[] = '"add":{"doc":' . json_encode($doc) . '}';
  }
  // Now create the total json object.
  $json_post = '{' . implode(',', $json_docs) . '}';
  $header = array("Content-type:application/json; charset=utf-8");
  curl_setopt($ch, CURLOPT_URL, $post_url);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json_post);
  curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
  curl_setopt($ch, CURLINFO_HEADER_OUT, 1);

  $data = curl_exec($ch);

  if (curl_errno($ch)) {
    throw new Exception ("curl_error:" . curl_error($ch));
    print $curl_error($ch);
  }
  else {
    curl_close($ch);
    print $data;

    return TRUE;
  }
}


/**
 * Delete documents from solr.
 *
 * @param $query
 * @param $collection
 *
 * @throws Exception
 */
function deleteFromSolr($query, $collection) {
  $ch = curl_init();

  $delete_url = 'http://' . get_setting('solr_host') . ': ' . get_setting('solr_port') . '/solr/' . $collection . '/update?commit=true';

  $json_fields = '{"delete":{"query":"' . $query . '" }}';
  print_r($query);
  $header = array("Content-type:application/json; charset=utf-8");
  curl_setopt($ch, CURLOPT_URL, $delete_url);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, 1);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $json_fields);
  curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);
  curl_setopt($ch, CURLINFO_HEADER_OUT, 1);

  $data = curl_exec($ch);

  if (curl_errno($ch)) {
    throw new Exception ("curl_error:" . curl_error($ch));
    print $curl_error($ch);
  }
  else {
    curl_close($ch);
    print $data;

    return TRUE;
  }
}
