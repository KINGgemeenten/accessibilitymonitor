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
  // Set the belonging website to status excluded.
  // Delete all results for the website domain
  // Update all url's to status excluded
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
}

/**
 * Mark a domain for rescanning and remove results.
 *
 * This is an action.
 *
 * @param $domain
 */
function rescanWebsite($domain) {
  // Delete all results for the website domain.
  // Update all urls to status 0.
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
  return FALSE;
}

/**
 * Delete the results for a website or url.
 *
 * @param $type
 *   website or url
 * @param $id
 *   the numeric id for the website or url
 */
function deleteResults($type, $id) {
  //delete results from solr or mongo.
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