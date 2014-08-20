<?php

// Composer autoloader.
require('vendor/autoload.php');
require('settings.php');
include_once('lib/pid.php');
include_once('lib/PhantomQuailWorker.php');
include_once('lib/QuailTester.php');
include_once('lib/actions.php');

define('STATUS_SCHEDULED', 0);
define('STATUS_TESTING', 1);
define('STATUS_TESTED', 2);
define('STATUS_ERROR', 3);
define('STATUS_EXCLUDED', 4);

define('TEST_TYPE_WAPPALYZER', 'cms');
define('TEST_TYPE_GOOGLE_PAGESPEED', 'google_pagespeed');

// Execute main with arguments.
$argument1 = isset($argv[1]) ? $argv[1] : NULL;
$argument2 = isset($argv[2]) ? $argv[2] : NULL;
$argument3 = isset($argv[3]) ? $argv[3] : NULL;

main($argument1, $argument2, $argument3);



function main($operation = NULL, $workerCount = 2, $arg3) {
  if (get_setting('is_master', FALSE)) {
    // First update the status.
    updateStatus();
    // Then perform all actions.
    performActions();
  }

  // Then kill all stalled phantomjs processes.
  killStalledProcesses();

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
        echo "Already running. Checking last update.\n";
        $lastUpdateTimeAgo = getTimeAgoLastAnalysis();
        // If the last update was more than 40 seconds ago. The process might be stalled.
        // In that case, kill the process.
        if ($lastUpdateTimeAgo > 40) {
          shell_exec('kill -KILL ' . $pid->pid);
          echo "Process killed because last action was " . $lastUpdateTimeAgo . " seconds ago.\n";
        }
        else {
          echo "Process is still running\n";
          exit;
        }
      }
      else {
        echo "Running...\n";
      }
      print "Performing tests\n";
      $pdo = getDatabaseConnection();

      $max_execution_time = get_setting('max_execution_time');

      $tester = new QuailTester($max_execution_time, $workerCount, $pdo);
      $tester->test();
      break;

    // Update site may always run.
    case 'update-sitelist':
      // Update the url's which need to be tested.
      updateUrlFromNutch();
      break;

    case 'detect-cms':
      // Detect the cms.
      detectCms();
      break;

    // Perform Google pagespeed mobile tests.
    case 'google_pagespeed':
      determine_page_speed();
      break;

    // Delete solr phantomcore.
    case 'purge-solr':
      purgeSolr();
      break;

    case 'solr-commit':
      commitSolr();
      break;

    case 'test-actions':
      testActions($workerCount, $arg3);
      break;
  }

  // Explicitly exit when at the end.
  exit;

}

/**
 * Purge the phantom solr core.
 */
function purgeSolr() {
  $config = get_setting('solr_phantom');

  $client = new Solarium\Client($config);

  // Get a delete query.
  $update = $client->createUpdate();

  $solrQuery = '*:*';

  $update->addDeleteQuery($solrQuery);
  $update->addCommit();

  // this executes the query and returns the result
  $result = $client->update($update);
}

/**
 * Commit to the phantom solr core.
 */
function commitSolr() {
  $config = get_setting('solr_phantom');

  $client = new Solarium\Client($config);

  // Get a delete query.
  $update = $client->createUpdate();

  $update->addCommit();

  // this executes the query and returns the result
  $result = $client->update($update);
}

/**
 * Kill all stalled phantomjs processes.
 */
function killStalledProcesses() {
  shell_exec('killall --older-than 2m phantomjs');
}

/**
 * Get amount of seconds after last update.
 *
 * @return int
 */
function getTimeAgoLastAnalysis() {
  // Get the database connection.
  $pdo = getDatabaseConnection();

  // Get the total amount url's so we can define a start for Solr.
  $query = $pdo->prepare("SELECT last_analysis FROM website ORDER BY last_analysis DESC LIMIT 1");
  $query->execute();
  $lastAnalysis = $query->fetchColumn();
  $now = time();
  return $now - $lastAnalysis;
}

/**
 * Detect the cms of the first url which is not detected.
 */
function detectCms() {
  // Get the database connection.
  $pdo = getDatabaseConnection();

  $query = $pdo->prepare("SELECT * FROM urls WHERE cms IS NULL AND status!=:status");
  $query->execute(array('status' => STATUS_EXCLUDED));
  if ($row = $query->fetch()) {
    // Phantomjs path.
    $phantomjsExecutable = get_setting('phantomjs_executable');
    $command = $phantomjsExecutable . ' --ignore-ssl-errors=yes node_modules/phantalyzer/phantalyzer.js ' . $row['full_url'] . ' | grep detectedApps';
    $output = shell_exec($command);
    $detectedApps = str_replace('detectedApps: ', '', $output);
    $update = $pdo->prepare("UPDATE urls SET cms=:cms WHERE url_id=:url_id");
    $update->execute(array(
        'cms' => $detectedApps,
        'url_id' => $row['url_id'],
      ));
  }
}

function determine_page_speed() {
  // Geting settings from settings.php.
  $google_pagespeed_api_url = get_setting('google_pagespeed_api_url');
  $google_pagespeed_api_key = get_setting('google_pagespeed_api_key');
  $google_pagespeed_api_strategy = get_setting('google_pagespeed_api_strategy');
  $google_pagespeed_api_fetch_limit = get_setting('google_pagespeed_api_fetch_limit');

  // Get a database connection.
  $pdo = getDatabaseConnection();

  if ($google_pagespeed_api_fetch_limit) {
    $query = $pdo->prepare("SELECT * FROM urls WHERE mobile_score IS NULL limit " . $google_pagespeed_api_fetch_limit);
  } else {
    $query = $pdo->prepare("SELECT * FROM urls WHERE mobile_score IS NULL");
  }

  $query->execute();
  if ($inspector_urls = $query->fetchAll()) {
    foreach($inspector_urls as $inspector_url) {
      // Get cURL resource
      $curl = curl_init();
      // Set some options - we are passing in a user agent too here
      curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $google_pagespeed_api_url . '?' .
          'key=' . $google_pagespeed_api_key .
          '&url=' . $inspector_url['full_url'] .
          '&strategy=' . $google_pagespeed_api_strategy,
        CURLOPT_USERAGENT => 'GT inspector script',
      ));

      // Send the request and get the response.
      $result_string = curl_exec($curl);

      // Close request to clear up some resources
      curl_close($curl);

      // Decode the JSON result string to a PHP object to obtain values.
      $result = json_decode($result_string);

      // Save score if we have one.
      if(isset($result->responseCode) && $result->responseCode == 200) {
        $update = $pdo->prepare("UPDATE urls SET mobile_score=:mobile_score WHERE url_id=:url_id");
        $update->execute(array(
          'mobile_score' => $result->score,
          'url_id' => $inspector_url['url_id'],
        ));
      }
    }
  }
}

/**
 * @param $url
 *
 * @return bool|mixed
 */
function performGooglePagespeedRequest($url) {
  // Geting settings from settings.php.
  $google_pagespeed_api_url = get_setting('google_pagespeed_api_url');
  $google_pagespeed_api_key = get_setting('google_pagespeed_api_key');
  $google_pagespeed_api_strategy = get_setting('google_pagespeed_api_strategy');

  // Get cURL resource
  $curl = curl_init();
  // Set some options - we are passing in a user agent too here
  curl_setopt_array(
    $curl,
    array(
      CURLOPT_RETURNTRANSFER => 1,
      CURLOPT_URL            => $google_pagespeed_api_url . '?' .
        'key=' . $google_pagespeed_api_key .
        '&url=' . $url .
        '&strategy=' . $google_pagespeed_api_strategy,
      CURLOPT_USERAGENT      => 'GT inspector script',
    )
  );

  // Send the request and get the response.
  $result_string = curl_exec($curl);

  // Close request to clear up some resources
  curl_close($curl);

  // Decode the JSON result string to a PHP object to obtain values.
  $result = json_decode($result_string);

  // Save score if we have one.
  if (isset($result->responseCode) && $result->responseCode == 200) {
    return $result;
  }
  return FALSE;
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
      $row = loadWebsiteRow($pdo, $url);
      if ($row) {
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
  $query = $pdo->prepare("SELECT * FROM website WHERE status IN (:status1, :status2)");
  $query->execute(array(
      ':status1' => STATUS_SCHEDULED,
      ':status2' => STATUS_TESTED,
    ));
  $toBeTested = $query->fetchAll(PDO::FETCH_OBJ);

  if (count($toBeTested)) {
    foreach ($toBeTested as $entry) {
      // Now check if there are still url's to be tested for this website.
      // If not, try to get more from solr..
      $query = $pdo->prepare("SELECT count(*) FROM urls WHERE wid=:wid AND status=:status");
      $query->execute(array(
          'wid' => $entry->wid,
          'status' => STATUS_SCHEDULED
        ));
      $results = $query->fetchColumn();
      if ($results == 0) {
        // Get the total amount url's so we can define a start for Solr.
        $query = $pdo->prepare("SELECT count(*) FROM urls WHERE wid=:wid");
        $query->execute(array(
            'wid' => $entry->wid,
          ));
        $start = $query->fetchColumn();

        // Create a Solarium instance.
        $nutch_config = get_setting('solr_nutch');
        $client = new Solarium\Client($nutch_config);

        // Create a query.
        $query = $client->createQuery($client::QUERY_SELECT);

        // Set some query parameters.
        $query->addParam('defType', 'edismax');
        $query->addParam('qf', 'host^0.001 url^2');
        $query->addParam('df', 'host');

        // Add the filter.
//        $baseUrl = str_replace('www.', '', $entry->url);
        // Get the host of the url.
        $parts = parse_url($entry->url);
        if (isset($parts['host'])) {
          $host = $parts['host'];
//        $query->setQuery('host:' . $host);
          $query->setQuery($host);

          // Add a filter application type, so we only have html and no pdf's!
          $type_query = 'type:application/xhtml+xml OR type:text/html';
          $query->createFilterQuery('type')->setQuery($type_query);

          // Now also add a filter query for host.
          $host_query = 'host:"' . $host . '"';
          $query->createFilterQuery('host')->setQuery($host_query);

          // Set the fields.
          $query->setFields(array('url', 'score'));

          // First check how many rows we should ask from nutch.
          $urls_per_sample = get_setting('urls_per_sample');

          // Set the rows.
          $query->setRows($urls_per_sample);
          // Set the start
          $query->setStart($start);

          // Get the results.
          $solrResults = $client->select($query);

          // Set the priority to 1, for the first document and increase.
          $priority = 1;
          foreach ($solrResults as $doc) {
            // Check if entry already exists.
            $query = $pdo->prepare("SELECT count(*) FROM urls WHERE wid=:wid AND full_url=:full_url");
            $query->execute(
              array(
                'wid'      => $entry->wid,
                'full_url' => $doc->url,
              )
            );
            $present = $query->fetchColumn();

            if (!$present) {
              // Insert a new entry.
              $sql = "INSERT INTO urls (wid,full_url,status,priority) VALUES (:wid,:full_url,:status,:priority)";
              $insert = $pdo->prepare($sql);
              $result = $insert->execute(
                array(
                  'wid'      => $entry->wid,
                  'full_url' => $doc->url,
                  'status'   => STATUS_SCHEDULED,
                  'priority' => $priority,
                )
              );
              // Increase the priority.
              $priority++;
            }
          }
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
    $urlCountResult = $urlCountQuery->fetch(PDO::FETCH_NUM);
    $urlCount = array_shift($urlCountResult);
    // Tested urls.
    $testedCountQuery = $pdo->prepare("SELECT COUNT(*) FROM urls WHERE wid=:wid AND status=:status");
    $testedCountQuery->execute(array(
        'wid' => $website->wid,
        'status' => STATUS_TESTED
      ));
    // The result is an array. Because it is only 1 result
    // we can use array_shift to get the first array element.
    // This is the number we want.
    $testedCountResult = $testedCountQuery->fetch(PDO::FETCH_NUM);
    $testedCount = array_shift($testedCountResult);

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
 * Perform scheduled actions.
 */
function performActions() {
  // Get all actions for which no timestamp has been set.
  $pdo = getDatabaseConnection();
  $actionQuery = $pdo->prepare("SELECT * FROM actions WHERE timestamp=0");
  $actionQuery->execute(array());
  $actions = $actionQuery->fetchAll(PDO::FETCH_OBJ);
  // Perform the actions.
  foreach ($actions as $action) {
    // The action is a function. We perform the function with argument item_id.
    // The item_id contains the full url, or the domain,
    // depending on the action.
    $function = $action->action;
    if (function_exists($function)) {
      $result = $function($action->item_uid);
      // If the result is true, set the timestamp to the current time.
      // Else we set it to -1, to indicate an error.
      $timestamp = -1;
      if ($result) {
        $timestamp = time();
      }
      // Perform the update action.
      $updateQuery = $pdo->prepare("UPDATE actions SET timestamp=:timestamp WHERE aid=:aid");
      $updateQuery->execute(array(
          'timestamp' => $timestamp,
          'aid' => $action->aid,
        ));
    }
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
    $host = get_setting('mysql_host');
    $dsn = 'mysql:host=' . $host . ';dbname=' . $database;
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
function get_setting($setting, $default = NULL) {
  global $global_vars;
  if (isset($global_vars[$setting])) {
    return $global_vars[$setting];
  }
  return $default;
}

/********************************************************************
 * Helper settings.
 ********************************************************************/
/**
 * Load the row for the website.
 *
 * @param $url
 *   The domain url
 *
 * @return mixed
 */
function loadWebsiteRow($url) {
  // Try to match the website on hostname only.
  $url_parts = parse_url($url);
  if (isset($url_parts['host'])) {
    // Get the database connection.
    $pdo = getDatabaseConnection();
    $query = $pdo->prepare("SELECT * FROM website WHERE url LIKE :url");
    $query->execute(array('url' => '%' . $url_parts['host']));
    $row = $query->fetch();
    return $row;
  }
  return FALSE;

}

/**
 * Load the row for the url.
 *
 * @param $url
 *   The full url
 *
 * @return mixed
 */
function loadUrlRow($url) {
  // Get the database connection.
  $pdo = getDatabaseConnection();
  $query = $pdo->prepare("SELECT * FROM urls WHERE full_url=:url");
  $query->execute(array('url' => $url));
  $row = $query->fetch();
  return $row;
}


/**
 * Validate a domain.
 *
 * @param $domain
 *
 * @return bool|string
 */
function validateDomain($domain) {
  $domain_parts = parse_url($domain);
  // If the scheme and host is set, we have a valid domain name.
  if (isset($domain_parts['scheme']) && isset($domain_parts['host']) && (! isset($domain_parts['path']) || $domain_parts['path'] == '/') ) {
    return $domain_parts['scheme'] . '://' . $domain_parts['host'];
  }
  // In all other cases fail.
  return FALSE;
}
