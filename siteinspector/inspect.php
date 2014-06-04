<?php

// Composer autoloader.
require('vendor/autoload.php');
require('settings.php');
include_once('lib/pid.php');
include_once('lib/PhantomQuailWorker.php');
include_once('lib/QuailTester.php');

define('STATUS_SCHEDULED', 0);
define('STATUS_TESTING', 1);
define('STATUS_TESTED', 2);
define('STATUS_ERROR', 3);

// Execute main with arguments.
$argument1 = isset($argv[1]) ? $argv[1] : NULL;
$argument2 = isset($argv[2]) ? $argv[2] : NULL;

main($argument1, $argument2);



function main($operation = NULL, $workerCount = 2) {
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

    // Delete solr phantomcore.
    case 'purge-solr':
      purgeSolr();
      break;
  }

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
        // Create a Solarium instance.
        $nutch_config = get_setting('solr_nutch');
        $client = new Solarium\Client($nutch_config);

        // Create a query.
        $query = $client->createQuery($client::QUERY_SELECT);

        // Add the filter.
        $baseUrl = str_replace('www.', '', $entry->url);
        $query->setQuery('domain:' . $baseUrl);

        // Set the fields.
        $query->setFields(array('url', 'score'));

        // First check how many rows we should ask from nutch.
        $urls_per_sample = get_setting('urls_per_sample');

        // Set the rows.
        $query->setRows($urls_per_sample);

        // Get the results.
        $solrResults = $client->select($query);

        // Set the priority to 1, for the first document and increase.
        $priority = 1;
        foreach ($solrResults as $doc) {
          // Insert a new entry.
          $sql = "INSERT INTO urls (wid,full_url,status,priority) VALUES (:wid,:full_url,:status,:priority)";
          $insert = $pdo->prepare($sql);
          $result = $insert->execute(array(
              'wid' => $entry->wid,
              'full_url' => $doc->url,
              'status' => STATUS_SCHEDULED,
              'priority' => $priority,
            ));
          // Increase the priority.
          $priority++;
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
function get_setting($setting, $default = NULL) {
  global $global_vars;
  if (isset($global_vars[$setting])) {
    return $global_vars[$setting];
  }
  return $default;
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
function exec_timeout($cmd, $timeout) {
  $timedOut = true;
  // File descriptors passed to the process.
  $descriptors = array(
    0 => array('pipe', 'r'),  // stdin
    1 => array('pipe', 'w'),  // stdout
    2 => array('pipe', 'w')   // stderr
  );

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
    stream_select($read, $other, $other, 0, $timeout);

    // Get the status of the process.
    // Do this before we read from the stream,
    // this way we can't lose the last bit of output if the process dies between these functions.
    $status = proc_get_status($process);

    // Read the contents from the buffer.
    // This function will always return immediately as the stream is none-blocking.
    $buffer .= stream_get_contents($pipes[1]);

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
  proc_terminate($process, 9);

  // Close all streams.
  fclose($pipes[0]);
  fclose($pipes[1]);
  fclose($pipes[2]);

  proc_close($process);

  return $buffer;
}