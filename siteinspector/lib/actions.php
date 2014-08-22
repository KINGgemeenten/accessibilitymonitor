<?php
/**
 * Actions related functions.
 */

/**
 * Exclude a domain from testing.
 *
 * This is an action.
 *
 * @param $domain
 */
function excludeWebsite($domain) {
  // First validate domain.
  $domain = validateDomain($domain);
  if ($domain) {
    // First try to find the website record.
    $record = loadWebsiteRow($domain);
    // If there is no record, insert it.
    $pdo = getDatabaseConnection();
    if (!$record) {
      $sql = "INSERT INTO website (url,status) VALUES (:url,:status)";
      $insert = $pdo->prepare($sql);
      $insert->execute(
        array(
          'url'    => $domain,
          'status' => STATUS_EXCLUDED,
        ));
    }
    else {
      // Set the belonging website to status excluded.
      $update = $pdo->prepare("UPDATE website SET status=:status WHERE wid=:wid");
      $update->execute(
        array(
          'status' => STATUS_EXCLUDED,
          'wid'    => $record['wid']
        )
      );
      // Delete all results for the website domain
      deleteResults('website', $record);
      // Update all url's to status excluded.
      $update = $pdo->prepare("UPDATE urls SET status=:status WHERE wid=:wid");
      $update->execute(array(
          'status' => STATUS_EXCLUDED,
          'wid' => $record['wid'],
        ));
    }
    return TRUE;
  }
  return FALSE;
}

/**
 * Add a website so it can be tested.
 *
 * This is an action.
 *
 * @param $domain
 */
function addWebsite($domain) {
  // Add the website to the website table if not already present.
  // First validate domain.
  $domain = validateDomain($domain);
  if ($domain) {
    // First try to find the website record.
    $record = loadWebsiteRow($domain);
    // If there is no record, insert it.
    $pdo = getDatabaseConnection();
    if (!$record) {
      $sql = "INSERT INTO website (url,status) VALUES (:url,:status)";
      $insert = $pdo->prepare($sql);
      $insert->execute(
        array(
          'url'    => $domain,
          'status' => STATUS_SCHEDULED,
        ));
    }
    else {
      // Set status to scheduled.
      $sql = "UPDATE website SET status=:status WHERE url=:url";
      $update = $pdo->prepare($sql);
      $update->execute(
        array(
          'status' => STATUS_SCHEDULED,
          'url'    => $domain,
        ));
    }
    return TRUE;
  }
  return FALSE;
}

/**
 * Mark a domain for rescanning and remove results.
 *
 * This is an action.
 *
 * @param $domain
 */
function rescanWebsite($domain) {
  // First validate domain.
  $domain = validateDomain($domain);
  if ($domain) {
    // First try to find the website record.
    $record = loadWebsiteRow($domain);
    // If there is no record, exit.
    if (!$record) {
      return FALSE;
    }
    $pdo = getDatabaseConnection();
    // Delete all results for the website domain.
    deleteResults('website', $record);
    // Update all urls to status 0.
    $update = $pdo->prepare("UPDATE urls SET status=:status WHERE wid=:wid");
    $update->execute(array(
        'status' => STATUS_SCHEDULED,
        'wid' => $record['wid'],
      ));
    // Update the website to scheduled.
    $update = $pdo->prepare("UPDATE website SET status=:status WHERE wid=:wid");
    $update->execute(array(
        'status' => STATUS_SCHEDULED,
        'wid' => $record['wid'],
      ));
    return TRUE;
  }
  return FALSE;
}

/**
 * Add an extra url for testing.
 *
 * This is an action.
 *
 * @param $url
 *
 * @return bool $result
 */
function addUrl($url) {
  // Add the url to the url's table when the website belonging
  // to the url is found. Set status to 0 and prio to 1.
  // First try to load the website record.
  $websiteRecord = loadWebsiteRow($url);
  $pdo = getDatabaseConnection();
  if ($websiteRecord) {
    // Try to load the url.
    $urlRecord = loadUrlRow($url);
    if (!$urlRecord) {
      $sql = "INSERT INTO urls (wid,full_url,status,priority) VALUES (:wid,:full_url,:status,:priority)";
      $insert = $pdo->prepare($sql);
      $result = $insert->execute(
        array(
          'wid'      => $websiteRecord['wid'],
          'full_url' => $url,
          'status'   => STATUS_SCHEDULED,
          'priority' => 1,
        )
      );
    }
    else {
      $sql = "UPDATE urls SET status=:status WHERE url_id=:url_id";
      $update = $pdo->prepare($sql);
      $result = $update->execute(array(
          'status' => STATUS_SCHEDULED,
          'url_id' => $urlRecord['url_id'],
        ));
    }
    return $result;
  }
  return FALSE;
}

/**
 * Exclude an url from testing.
 *
 * This is an action.
 *
 * @param $url
 *
 * @return bool $result
 */
function excludeUrl($url) {
  // Check if the url is already in the url's table, and if so
  // - remove results
  // - set status to excluded.
  // else
  // Add url to urls table with status excluded.
  $websiteRecord = loadWebsiteRow($url);
  $pdo = getDatabaseConnection();
  if ($websiteRecord) {
    // Try to load the url.
    $urlRecord = loadUrlRow($url);
    if ($urlRecord) {
      $update = $pdo->prepare("UPDATE urls SET status=:status WHERE url_id=:url_id");
      $update->execute(array(
          'status' => STATUS_EXCLUDED,
          'url_id' => $urlRecord['url_id'],
        ));
    }
  }
  return FALSE;
}

/**
 * Delete the results for a website or url.
 *
 * @param $type
 *   website or url
 * @param $record
 *   the loaded database record for the website or url
 */
function deleteResults($type, $record) {
  //delete results from solr or mongo.
  switch ($type) {
    case 'website':
      // First delete from solr.

      // Create the client to solr.
      $phantomcore_config = get_setting('solr_phantom');
      $client = new Solarium\Client($phantomcore_config);

      // Get a delete query.
      $update = $client->createUpdate();

      // add the delete query and a commit command to the update query
      $parts = parse_url($record['url']);
      $solrQuery = 'url_sub:' . $parts['host'];

      $update->addDeleteQuery($solrQuery);

      // this executes the query and returns the result
      $result = $client->update($update);

      // Now send a commit to solr.
      commitSolr();
      break;

    default:
      break;
  }
}

/**
 * Track an url using a status.
 *
 * If the url already exists, only the status is changed.
 * Otherwise the url is inserted, if the corresponding website
 * Could be found.
 *
 * @param $url
 * @param $status
 *
 * @return bool $result
 *   False if the corresponding website could not be found.
 */
function trackUrl($url, $status) {
//  check if the url is already present
//        if present {
//          update status
//        }
//        else {
//          insert
//        }
  return FALSE;
}

/**
 * Track a website using a status.
 *
 * If the website already exists, only the status is changed.
 * Otherwise the website is inserted.
 *
 * @param $website
 * @param $status
 */
function trackWebsite($website, $status) {
//  check if the website is already present
//        if present {
//          update status
//        }
//        else {
//          insert
//        }
}

/**
 * Test the actions.
 *
 * @param $action
 * @param $object
 */
function testActions($action, $object) {
  echo 'Action: ' . $action . ', object: ' . $object . "\n";
  performAction($action, $object);
}

/**
 * Perform an action
 *
 * @param $action
 * @param $object
 *
 * @return bool
 */
function performAction($action, $object) {
  if (function_exists($action)) {
    $action($object);
    return TRUE;
  }
  // If function is not executed, return FALSE.
  return FALSE;
}