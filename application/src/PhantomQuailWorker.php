<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\PhantomQuailWorker.
 */

namespace Triquanta\AccessibilityMonitor;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Log\LogLevel;
use Solarium\Core\Client\Client;
use Solarium\QueryType\Update\Query\Query;

/**
 * Provides a Phantom Quail worker.
 */
class PhantomQuailWorker extends \Thread {

  /**
   * Log messages.
   *
   * All messages are to be sent to the application's logger after self::join()
   * has been called.
   *
   * @var array[]
   *   Keys are \Psr\Log\LogLevel::* constants. Values are arrays with strings
   *   (the messages).
   */
  protected $logMessages = array(
    LogLevel::EMERGENCY => array(),
    LogLevel::ALERT => array(),
    LogLevel::CRITICAL => array(),
    LogLevel::ERROR => array(),
    LogLevel::WARNING => array(),
    LogLevel::NOTICE => array(),
    LogLevel::INFO => array(),
    LogLevel::DEBUG => array(),
  );

  /**
   * The Google PageSpeed API key.
   *
   * @var string
   */
  protected $googlePagespeedApiKey;

  /**
   * The Google PageSpeed API strategy.
   *
   * @var string
   */
  protected $googlePagespeedApiStrategy;

  /**
   * The Google PageSpeed API URL.
   *
   * @var string
   */
  protected $googlePagespeedApiUrl;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The Phantom JS manager.
   *
   * @var \Triquanta\AccessibilityMonitor\PhantomJsInterface
   */
  protected $phantomJs;

  /**
   * The Solr client.
   *
   * @var \Solarium\Core\Client\Client
   */
  protected $solrClient;

  /**
   * The URL that is being tested.
   *
   * @var \Triquanta\AccessibilityMonitor\Url
   */
  protected $url;

  /**
   * @todo
   */
  protected $result;

  /**
   * @todo
   */
  protected $cmsResult;

  /**
   * @todo
   */
  protected $pageSpeedResult = FALSE;

  // Store the website cms.
  protected $websiteCms;
  // Store the website id, if the website analysis has been done.
  protected $wid;

  /**
   * @todo
   */
  protected $status = Url::STATUS_TESTING;

  // Variable to hold the absolute raw result.
  protected $rawResult;
  // Array to hold all the raw technique results per test.
  protected $rawQuailTechniqueResults = array();
  // Array to hold all the results per test.
  protected $quailResults = array();
  // Array to hold all the cases of all tests together with the result.
  protected $quailCases = array();
  // Array to hold examples of failed cases.
  protected $failedCaseExamples = array();
  // Array to hold the final aggregated results stemming from raw cases per wcag thingy.
  protected $quailAggregatedCaseResults = array();

  // The final results per wcag item stemming from quail internal analyses.
  protected $quailFinalResults = array();

  // Store the queueid of the object.
  protected $queueId;

  /**
   * Constructs a new instance.
   *
   * @param \GuzzleHttp\ClientInterface
   * @param \Solarium\Core\Client\Client $solr_client
   * @param \Triquanta\AccessibilityMonitor\PhantomJsInterface $phantom_js
   * @param \Triquanta\AccessibilityMonitor\Url $url
   * @param $queueId
   * @param string $google_pagespeed_api_url
   * @param string $google_pagespeed_api_key
   * @param string $google_pagespeed_api_strategy
   */
  public function __construct(ClientInterface $http_client, Client $solr_client, PhantomJsInterface $phantom_js, Url $url, $queueId, $google_pagespeed_api_url, $google_pagespeed_api_key, $google_pagespeed_api_strategy) {
    $this->googlePagespeedApiKey = $google_pagespeed_api_key;
    $this->googlePagespeedApiStrategy = $google_pagespeed_api_strategy;
    $this->googlePagespeedApiUrl = $google_pagespeed_api_url;
    $this->httpClient = $http_client;
    $this->phantomJs = $phantom_js;
    $this->solrClient = $solr_client;

    $this->url = $url;
    // Fill the websiteCms if present.
    if ($url->getCms() != '') {
      $this->websiteCms = $url->getCms();
    }
    $this->queueId = $queueId;
  }

  /**
   * Gets the log messages.
   *
   * @return array[]
   *   Keys are \Psr\Log\LogLevel::* constants. Values are arrays with strings
   *   (the messages).
   */
  public function getLogMessages() {
    return $this->logMessages;
  }

  /**
   * Logs a message.
   *
   * @param string $level
   *   One of the \Psr\Log\LogLevel::* constants.
   * @param string $message
   */
  public function log($level, $message) {
    $log_messages = $this->logMessages;
    $log_messages[$level][] = $message;
    $this->logMessages = $log_messages;
  }

  /**
   * Run function. This function is executed when the method start is executed.
   */
  public function run() {
    try {
      // We need to include the autoloaders here.
      // This is because this is a thread, and there
      // are different rules.
      // For this perticular case see:
      // https://github.com/krakjoe/pthreads/issues/68
      // Composer autoloader.
      require(__DIR__ . '/../vendor/autoload.php');
      Application::bootstrap();

      if ($this->url->isRoot()) {
        $this->detectCms();
        $this->executeGooglePagespeed();
      }
      $this->analyzeQuail();
    }
    catch (\Exception $e) {
      $this->log(LogLevel::EMERGENCY, sprintf('Uncaught exception: %s.', $e->getMessage()));
    }
  }

  /**
   * Detect the cms of the main website.
   */
  protected function detectCms() {
    $testUrl = $this->url->getUrl();
    $this->websiteCms = implode('|', $this->phantomJs->getDetectedApps($testUrl));
  }

  /**
   * Perform a google Pagespeed test.
   */
  protected function executeGooglePagespeed() {
    $url = $this->url->getUrl();
    try {
      $request = $this->httpClient->createRequest('GET', $this->googlePagespeedApiUrl);
      $request->getQuery()->set('key', $this->googlePagespeedApiKey);
      $request->getQuery()->set('locale', 'nl');
      $request->getQuery()->set('url', $url);
      $request->getQuery()->set('strategy', $this->googlePagespeedApiStrategy);
      $request->setHeader('User-Agent', 'GT inspector script');
      $response = $this->httpClient->send($request);

      $this->pageSpeedResult = (string) $response->getBody();
    }
    catch (ClientException $e) {
      $this->log(LogLevel::EMERGENCY, $e->getMessage());
      $this->pageSpeedResult = json_encode(array());
    }
  }

  /**
   * Get the website cms.
   *
   * @return mixed
   */
  public function getWebsiteCms() {
    return $this->websiteCms;
  }

  /**
   * Perform the quail analysis.
   */
  protected function analyzeQuail() {
    // First delete all solr records for this url.
    $this->deleteCasesFromSolr();

    $url = $this->url->getUrl();
    try {
      $output = $this->phantomJs->getQuailResults($url);
      // @TODO: check if we need the count for solr.
      // Now process the results from quail.
      // We have to generate a unique id later.
      // In order to do this, we count the results, so it can be
      // included in the unique id.
      $count = 0;

      // Create an array for all quail results.
      // We need to use this 'in between array' because in threads object variable
      // arrays don't allow array_push or [].
      $rawQuailResults = array();
      // Create an array for
      foreach (preg_split("/((\r?\n)|(\r\n?))/", $output) as $line) {
        if ($line != '' && preg_match("/^{/", $line)) {
          // do stuff with $line
          $rawResults = (array) json_decode($line);
          // Since there is only one result json, this is also the exact raw result.
          $this->rawResult = $rawResults;
          // Now process the quail result.
          $this->processQuailResult();
        }
      }

      // Now send the case results to solr.
      $this->log(LogLevel::DEBUG, 'Sending results to Solr.');
      $this->sendCaseResultsToSolr();
      $this->log(LogLevel::DEBUG, 'Results sent to Solr.');

      // Update the result.
      $this->result = $this->url->getId();
      // Set the status to tested.
      $this->status = $this->getQuailFinalResults() ? Url::STATUS_TESTED : Url::STATUS_ERROR;
    } catch (\Exception $e) {
      // If there is an exception, probably phantomjs timed out.
      $this->status = Url::STATUS_ERROR;
      $this->log(LogLevel::DEBUG, $e->getMessage());
    }
  }

  protected function processQuailResult() {
    // Create an array which will be written to the object property later.
    // We need this because of the thread nature of this class.
    $quailFinalResults = array();
    // Store the quailCases in a temporary array.
    $quailCases = array();
    $rawResult = $this->rawResult;
    $wcag20Mapping = $this->getWcag2Mapping();
    foreach ($rawResult as $criterium) {
      $criteriumNumber = $wcag20Mapping[$criterium->testRequirement];
      // Extract the most important cases.
      // First check if there is a pointer, because if there is none,
      // the cases doesn't need to be stored.
      if (property_exists($criterium, 'hasPart') && count($criterium->hasPart)) {
        foreach ($criterium->hasPart as $case) {
          // Check if there is a pointer in the outcome.
          // The pointer contains the html snippet we need.
          if (isset($case->outcome->pointer) && isset($case->outcome->pointer[0]->chars) && ($case->outcome->result == 'failed' || $case->outcome->result == 'cantTell')) {
            // Create the unique key, to prevent that we store only one case per testCase per criterium.
            $uniqueKey = str_replace('.', '_', $criteriumNumber) . '_' . $case->testCase . '_' . $case->outcome->result;
            if (! isset ($quailCases[$uniqueKey])) {
              // Add the unique key to the case.
              $case->uniqueKey = $uniqueKey;
              // Add the criteriumName to the case.
              $case->criteriumName = $criteriumNumber;
              $quailCases[$uniqueKey] = $case;
            }
          }
        }
      }
      // Now unset the hasPart, on order to save space.
      unset($criterium->hasPart);
      // Add criterium.
      $criterium->criterium = $criteriumNumber;
      $quailFinalResults[$criteriumNumber] = $criterium;
    }
    // Now fill the property.
    $this->quailFinalResults = $quailFinalResults;
    $this->quailCases = $quailCases;

  }

  /**
   * Delete all the cases from solr.
   */
  public function deleteCasesFromSolr() {
    // Get a delete query.
    $update = $this->solrClient->createUpdate();

    // add the delete query and a commit command to the update query
    $escaped_string = $this->escapeUrlForSolr($this->url->getUrl());
    $solrQuery = 'url_id:' . $escaped_string;

    $update->addDeleteQuery($solrQuery);

    // this executes the query and returns the result
    $result = $this->solrClient->update($update);

  }

  /**
   * Send all the cases to solr.
   */
  protected function sendCaseResultsToSolr() {
    // First check if there are results to send.
    if (count($this->quailCases)) {
      // Create an update query.
      $updateQuery = $this->solrClient->createUpdate();

      // Create an array of documents.
      $docs = array();
      // Now start adding the cases.
      foreach ($this->quailCases as $case) {
        $doc = $this->caseToSolrDocument($updateQuery, $case);
        if ($doc) {
          $docs[] = $doc;
        }
      }
      // add the documents and a commit command to the update query
      $updateQuery->addDocuments($docs);

      // this executes the query and returns the result.
      try {
        $result = $this->solrClient->update($updateQuery);
      } catch (\Exception $e) {
        $this->log(LogLevel::ERROR, 'Error sending cases to solr. Solr responded with an exception: ' . $e->getMessage());
      }
    }
  }

  /**
   * Create a solr document from a case.
   *
   * @param \Solarium\QueryType\Update\Query\Query $updateQuery
   * @param $case
   *
   * @return \Solarium\QueryType\Update\Query\Document\DocumentInterface $doc
   *   Solr document
   */
  protected function caseToSolrDocument(Query $updateQuery, $case) {
    // Create a solr document.
    if (property_exists($case, 'uniqueKey')) {
      $doc = $updateQuery->createDocument();
      $doc->id = $this->createCaseSolrId($case);
      $doc->url = $this->url->getUrl();
      $doc->url_id = $this->escapeUrlForSolr($doc->url);
      $doc->url_main = $this->url->getMainDomain();
      $doc->host = $this->url->getHostName();
      if (property_exists($case->outcome, 'pointer')) {
        $doc->element = $case->outcome->pointer[0]->chars;
      }
      if (property_exists($case->outcome, 'info')) {
        $doc->name_en = $case->outcome->info->en;
        $doc->name_nl = $case->outcome->info->nl;
      }
      $doc->succescriterium = $case->criteriumName;
      $doc->test_result = $case->outcome->result;
      $doc->testtype = $case->testCase;
      // Add document type.
      $doc->document_type = 'case';
      $doc->website_test_results_id = $this->url->getWebsiteTestResultsId();

      return $doc;
    }
    return FALSE;
  }

  /**
   * Create a solr id for a case.
   *
   * @param $case
   *
   * @return string
   *   Case id.
   */
  protected function createCaseSolrId($case) {
    // Create a hash based om the url and uniqueKey of the case.
    $hash = md5($case->uniqueKey . '_' . $this->url->getWebsiteTestResultsId() . '_' . $this->url->getUrl());
    return $hash;
  }

  /********************************
   * GETTER AND SETTERS.
   ********************************/

  /**
   * @param mixed $queueId
   */
  public function setQueueId($queueId) {
    $this->queueId = $queueId;
  }

  /**
   * @return mixed
   */
  public function getQueueId() {
    return $this->queueId;
  }

  /**
   * Return result data, so it can be processed further.
   *
   * @return mixed
   */
  public function getResult() {
    return $this->result;
  }

  /**
   * Get the quail aggregated case results for all wcag criteria (including level AAA).
   *
   * @return array
   */
  public function getQuailAggregatedCaseResults() {
    return $this->quailAggregatedCaseResults;
  }

  /**
   * Get the quail final result.
   *
   * @return array
   */
  public function getQuailFinalResults() {
    return $this->quailFinalResults;
  }


  /**
   * Get status.
   *
   * @return int
   */
  public function getStatus() {
    return $this->status;
  }

  /**
   * Gets the URL's website test results ID.
   *
   * @return int
   */
  public function getWebsiteTestResultsId() {
    return $this->url->getWebsiteTestResultsId();
  }

  /**
   * Gets the URL this worker tests.
   *
   * @return \Triquanta\AccessibilityMonitor\Url
   */
  public function getUrl() {
    return $this->url;
  }

  /**
   * Get the cms result.
   *
   * @return mixed
   */
  public function getCmsResult() {
    return $this->cmsResult;
  }

  /**
   * Get the google pagespeed result.
   *
   * @return mixed
   */
  public function getPageSpeedResult() {
    return $this->pageSpeedResult;
  }

  /**
   * Escapes a URL for Solr.
   *
   * @param string $url
   *
   * @return string
   */
  protected function escapeUrlForSolr($url) {
    $special_characters = array("'", '+', '-', '&&', '||', '!', '(', ')', '{', '}', '[', ']', '^', '"', '~', '*', '?', ':', '\\');
    return str_replace($special_characters, '_', $url);
  }

  /**
   * Get the wcag2 mapping array.
   *
   * This would be better as static variable, but that doesn't work with threads.
   *
   * @return array
   */
  protected function getWcag2Mapping() {
    $wcag20Mapping = [
      "wcag20:text-equiv-all" => "1.1.1",
      "wcag20:media-equiv-av-only-alt" => "1.2.1",
      "wcag20:media-equiv-captions" => "1.2.2",
      "wcag20:media-equiv-audio-desc" => "1.2.3",
      "wcag20:media-equiv-real-time-captions" => "1.2.4",
      "wcag20:media-equiv-audio-desc-only" => "1.2.5",
      "wcag20:media-equiv-sign" => "1.2.6",
      "wcag20:media-equiv-extended-ad" => "1.2.7",
      "wcag20:media-equiv-text-doc" => "1.2.8",
      "wcag20:media-equiv-live-audio-only" => "1.2.9",
      "wcag20:content-structure-separation-programmatic" => "1.3.1",
      "wcag20:content-structure-separation-sequence" => "1.3.2",
      "wcag20:content-structure-separation-understanding" => "1.3.3",
      "wcag20:visual-audio-contrast-without-color" => "1.4.1",
      "wcag20:visual-audio-contrast-dis-audio" => "1.4.2",
      "wcag20:visual-audio-contrast-contrast" => "1.4.3",
      "wcag20:visual-audio-contrast-scale" => "1.4.4",
      "wcag20:visual-audio-contrast-text-presentation" => "1.4.5",
      "wcag20:visual-audio-contrast7" => "1.4.6",
      "wcag20:visual-audio-contrast-noaudio" => "1.4.7",
      "wcag20:visual-audio-contrast-visual-presentation" => "1.4.8",
      "wcag20:visual-audio-contrast-text-images" => "1.4.9",
      "wcag20:keyboard-operation-keyboard-operable" => "2.1.1",
      "wcag20:keyboard-operation-trapping" => "2.1.2",
      "wcag20:keyboard-operation-all-funcs" => "2.1.3",
      "wcag20:time-limits-required-behaviors" => "2.2.1",
      "wcag20:time-limits-pause" => "2.2.2",
      "wcag20:time-limits-no-exceptions" => "2.2.3",
      "wcag20:time-limits-postponed" => "2.2.4",
      "wcag20:time-limits-server-timeout" => "2.2.5",
      "wcag20:seizure-does-not-violate" => "2.3.1",
      "wcag20:seizure-three-times" => "2.3.2",
      "wcag20:navigation-mechanisms-skip" => "2.4.1",
      "wcag20:navigation-mechanisms-title" => "2.4.2",
      "wcag20:navigation-mechanisms-focus-order" => "2.4.3",
      "wcag20:navigation-mechanisms-refs" => "2.4.4",
      "wcag20:navigation-mechanisms-mult-loc" => "2.4.5",
      "wcag20:navigation-mechanisms-descriptive" => "2.4.6",
      "wcag20:navigation-mechanisms-focus-visible" => "2.4.7",
      "wcag20:navigation-mechanisms-location" => "2.4.8",
      "wcag20:navigation-mechanisms-link" => "2.4.9",
      "wcag20:navigation-mechanisms-headings" => "2.4.10",
      "wcag20:meaning-doc-lang-id" => "3.1.1",
      "wcag20:meaning-other-lang-id" => "3.1.2",
      "wcag20:meaning-idioms" => "3.1.3",
      "wcag20:meaning-located" => "3.1.4",
      "wcag20:meaning-supplements" => "3.1.5",
      "wcag20:meaning-pronunciation" => "3.1.6",
      "wcag20:consistent-behavior-receive-focus" => "3.2.1",
      "wcag20:consistent-behavior-unpredictable-change" => "3.2.2",
      "wcag20:consistent-behavior-consistent-locations" => "3.2.3",
      "wcag20:consistent-behavior-consistent-functionality" => "3.2.4",
      "wcag20:consistent-behavior-no-extreme-changes-context" => "3.2.5",
      "wcag20:minimize-error-identified" => "3.3.1",
      "wcag20:minimize-error-cues" => "3.3.2",
      "wcag20:minimize-error-suggestions" => "3.3.3",
      "wcag20:minimize-error-reversible" => "3.3.4",
      "wcag20:minimize-error-context-help" => "3.3.5",
      "wcag20:minimize-error-reversible-all" => "3.3.6",
      "wcag20:ensure-compat-parses" => "4.1.1",
      "wcag20:ensure-compat-rsv" => "4.1.2"
    ];

    return $wcag20Mapping;
  }


}
