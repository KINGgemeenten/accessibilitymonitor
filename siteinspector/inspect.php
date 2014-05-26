<?php

include_once('lib/pid.php');
include_once('lib/threads.php');
include_once('lib/QuailTester.php');

define('STATUS_SCHEDULED', 0);
define('STATUS_TESTING', 1);
define('STATUS_TESTED', 2);

// Execute main with the first argument.

main($argv[1]);



function main($operation = NULL) {
  // First update the status.
  updateStatus();

  // Main controller for the script.
  if (!isset($operation)) {
    print "inspect.php excepts 1 argument: check or update-sitelist\n";
  }

  switch ($operation) {
    case 'check':
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
      print "Performing tests\n";
      QuailTester::performTests();
      break;

    // Update site may always run.
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
 * Update the status of the websites.
 *
 * If at least one url of a website is set to tested,
 * the website is in testing mode
 * If all url's are tested, the website is also tested.
 */
function updateStatus() {
  // First get all website entries.
  $pdo = getDatabaseConnection();
  $query = $pdo->prepare("SELECT * FROM website;");
  $query->execute(array('status' => STATUS_SCHEDULED));
  $websites = $query->fetchAll(PDO::FETCH_OBJ);

  foreach ($websites as $website) {
    // We have to do two queries:
    // 1: how many urls are present
    // 2: how many urls are tested.
    $urlCountQuery = $pdo->prepare("SELECT COUNT(*) FROM urls WHERE wid=:wid");
    $urlCountQuery->execute(array('wid' => $website->wid));
    $urlCount = array_shift($urlCountQuery->fetch(PDO::FETCH_NUM));
    // Tested urls.
    $testedCountQuery = $pdo->prepare("SELECT COUNT(*) FROM urls WHERE wid=:wid AND status=:status");
    $testedCountQuery->execute(array(
        'wid' => $website->wid,
        'status' => STATUS_TESTED
      ));
    // The result is an array. Because it is only 1 result
    // we can use array_shift to get the first array element.
    // This is the number we want.
    $testedCount = array_shift($testedCountQuery->fetch(PDO::FETCH_NUM));

    // Now there are three possibilities:
    // The amount of tested urls is 0: set the website to scheduled.
    // The amount of tested urls is smaller than the total amount of urls: set website to testing.
    // The amount of tested urls is the same as the total amount of urls: set website to tested.
    $websiteStatus = STATUS_SCHEDULED;
    if ($testedCount > 0 && $testedCount < $urlCount) {
      $websiteStatus = STATUS_TESTING;
    }
    else if ($testedCount == $urlCount && $urlCount != 0) {
      $websiteStatus = STATUS_TESTED;
    }
    // Now write the status.
    $update = $pdo->prepare("UPDATE website SET status=:status WHERE wid=:wid");
    $update->execute(array(
        'status' => $websiteStatus,
        'wid' => $website->wid
      ));
  }

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
//    print $data;

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
//  print_r($query);
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
//    print $data;
    return TRUE;
  }
}
