<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Storage.
 */

namespace Triquanta\AccessibilityMonitor;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use Psr\Log\LoggerInterface;
use Solarium\Core\Client\Client;
use Solarium\QueryType\Update\Query\Query;
use Triquanta\AccessibilityMonitor\Testing\TestingStatusInterface;

/**
 * Provides a database- and Solr-based storage manager.
 */
class Storage implements StorageInterface
{

    /**
     * The AMQP queue connection.
     *
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    protected $queueConnection;

    /**
     * The database manager.
     *
     * @var \Triquanta\AccessibilityMonitor\DatabaseInterface
     */
    protected $database;

    /**
     * The test group flooding threshold.
     *
     * @var int
     *   How long to wait between processing two URLs from the same test run
     *   group.
     */
    protected $testRunGroupFloodingThreshold;

    /**
     * The logger.
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * The Solr client.
     *
     * @var \Solarium\Core\Client\Client
     */
    protected $solrClient;

    /**
     * Constructs a new instance.
     *
     * @param \Triquanta\AccessibilityMonitor\DatabaseInterface $database
     * @param \Solarium\Core\Client\Client $solrClient
     * @param \PhpAmqpLib\Connection\AMQPStreamConnection $amqpQueue
     * @param \Psr\Log\LoggerInterface $logger
     * @param int $testRunGroupFloodingThreshold
     *   How long to wait between processing two URLs from the same test run
     *   group.
     */
    public function __construct(
      DatabaseInterface $database,
      Client $solrClient,
      AMQPStreamConnection $amqpQueue,
      LoggerInterface $logger,
      $testRunGroupFloodingThreshold
    ) {
        $this->queueConnection = $amqpQueue;
        $this->database = $database;
        $this->testRunGroupFloodingThreshold = $testRunGroupFloodingThreshold;
        $this->logger = $logger;
        $this->solrClient = $solrClient;
    }

    /**
     * Creates a URL from a storage record.
     *
     * @param \stdClass $record
     *   A record from the url table.
     *
     * @return \Triquanta\AccessibilityMonitor\Url
     */
    protected function createUrlFromStorageRecord(\stdClass $record)
    {
        $url = new Url();
        $url->setId($record->url_id)
          ->setUrl($record->url)
          ->setTestingStatus($record->status)
          ->setCms($record->cms)
          ->setQuailResult(json_decode($record->quail_result))
          ->setGooglePageSpeedResult($record->pagespeed_result)
          ->setLastProcessedTime($record->last_processed)
          ->setRoot((bool) $record->is_root)
          ->setTestRunId($record->test_run_id)
          ->setFailedTestCount($record->failed_test_count);

        return $url;
    }

    public function getUrlById($id)
    {
        $query = $this->database->getConnection()
          ->prepare("SELECT u.*, ur.cms, ur.quail_result, ur.pagespeed_result FROM url u INNER JOIN url_result ur ON u.url_id = ur.url_id WHERE u.url_id = :url_id");
        $query->execute(array(
          'url_id' => $id,
        ));

        $record = $query->fetch(\PDO::FETCH_OBJ);

        return $record ? $this->createUrlFromStorageRecord($record) : NULL;
    }

    public function getUrlsByStatusAndLastProcessedDateTime($status, $start, $end)
    {
        $query = $this->database->getConnection()
          ->prepare("SELECT u.*, ur.cms, ur.quail_result, ur.pagespeed_result FROM url u INNER JOIN url_result ur ON u.url_id = ur.url_id WHERE status = :status AND last_processed >= :last_processed_start AND last_processed <= :last_processed_end");
        $query->execute(array(
          'status' => $status,
          'last_processed_start' => $start,
          'last_processed_end' => $end,
        ));

        $urls = [];
        while ($record = $query->fetch(\PDO::FETCH_OBJ)) {
            $urls[] = $this->createUrlFromStorageRecord($record);
        }

        return $urls;
    }

    public function saveUrl(Url $url)
    {
        try {
            if ($url->getId()) {
                // Save the URL to the `url` table.
                $updateUrlValues = [
                  'url_id' => $url->getId(),
                  'status' => $url->getTestingStatus(),
                  'last_processed' => $url->getLastProcessedTime(),
                  'is_root' => (int) $url->isRoot(),
                  'failed_test_count' => $url->getFailedTestCount(),
                ];
                $updateUrlQuery = $this->database->getConnection()
                  ->prepare("UPDATE url SET status = :status, last_processed = :last_processed, is_root = :is_root, failed_test_count = :failed_test_count WHERE url_id = :url_id");
                $updateUrlQuery->execute($updateUrlValues);

                // Save the URL to the `url_result` table.
                $updateUrlResultValues = [
                  'url_id' => $url->getId(),
                  'cms' => $url->getCms(),
                  'quail_result' => json_encode($url->getQuailResult()),
                  'pagespeed_result' => $url->getGooglePageSpeedResult(),
                ];
                $updateUrlResultQuery = $this->database->getConnection()
                  ->prepare("UPDATE url_result SET cms = :cms, quail_result = :quail_result, pagespeed_result = :pagespeed_result WHERE url_id = :url_id");
                $updateUrlResultQuery->execute($updateUrlResultValues);
            } else {
                // Save the URL to the `url` table.
                $insertUrlValues = [
                  'url_id' => $url->getId(),
                  'status' => $url->getTestingStatus(),
                  'last_processed' => $url->getLastProcessedTime(),
                  'is_root' => (int) $url->isRoot(),
                  'failed_test_count' => $url->getFailedTestCount(),
                  'url' => $url->getUrl(),
                  'test_run_id' => $url->getTestRunId(),
                ];
                $insertUrlQuery = $this->database->getConnection()
                  ->prepare("INSERT INTO url (test_run_id, url, status, last_processed, is_root) VALUES (:test_run_id, :url, :status, :last_processed, :is_root)");
                $insertUrlQuery->execute($insertUrlValues);

                // Get the URL's ID.
                $url->setId($this->database->getConnection()->lastInsertId());

                // Save the URL to the `url_result` table.
                $insertUrlResultValues = [
                  'url_id' => $url->getId(),
                  'cms' => $url->getCms(),
                  'quail_result' => json_encode($url->getQuailResult()),
                  'pagespeed_result' => $url->getGooglePageSpeedResult(),
                ];
                $insertUrlResultQuery = $this->database->getConnection()
                  ->prepare("INSERT INTO url_result (cms, quail_result, pagespeed_result) VALUES (:cms, :quail_result, :pagespeed_result)");
                $insertUrlResultQuery->execute($insertUrlResultValues);
            }
            $this->sendCaseResultsToSolr($url);

            // Update the test run's last processed time. The DB column is only
            // used for querying and MUST remain in sync with the most recent
            // last processed time of the test run's URLs.
            if ($url->getLastProcessedTime()) {
                $updateUrlResultQuery = $this->database->getConnection()
                  ->prepare("UPDATE test_run SET last_processed = GREATEST(:url_last_processed, last_processed) WHERE id = :test_run_id");
                $updateUrlResultQuery->execute([
                  'test_run_id' => $url->getTestRunId(),
                  'url_last_processed' => $url->getLastProcessedTime(),
                ]);
            }
        }
        catch (\Exception $e) {
            throw new StorageException('A storage error occurred.', 0, $e);
        }
    }

    public function countUrlsByWebsiteTestResultsIdAndLastProcessedDateTimePeriod($websiteTestResultsId, $start, $end) {
        $query = $this->database->getConnection()
          ->prepare("SELECT COUNT(1) FROM url WHERE website_test_results_id = :website_test_results_id AND last_processed > :start AND last_processed < :end");
        $query->execute(array(
          'website_test_results_id' => $websiteTestResultsId,
          'start' => $start,
          'end' => $end,
        ));

        return $query->fetchColumn();
    }

    /**
     * Send all the cases to solr.
     *
     * @param \Triquanta\AccessibilityMonitor\Url $url
     *
     * @throws \Triquanta\AccessibilityMonitor\StorageException
     */
    protected function sendCaseResultsToSolr(Url $url)
    {
        $this->logger->debug(sprintf('Sending results for %s to Solr.', $url->getUrl()));
        // First check if there are results to send.
        if ($url->getQuailResultCases()) {
            $testRun = $this->getTestRunById($url->getTestRunId());

            // Create an update query.
            $updateQuery = $this->solrClient->createUpdate();

            // Create an array of documents.
            $docs = array();
            // Now start adding the cases.
            foreach ($url->getQuailResultCases() as $case) {
                $doc = $this->caseToSolrDocument($url, $testRun, $updateQuery, $case);
                if ($doc) {
                    $docs[] = $doc;
                }
            }
            // add the documents and a commit command to the update query
            $updateQuery->addDocuments($docs);

            // this executes the query and returns the result.
            try {
                $this->solrClient->execute($updateQuery);
            } catch (\Exception $e) {
                $this->logger->emergency(sprintf('Error sending cases for %s to Solr. Solr responded with an exception: %s', $url->getUrl(), $e->getMessage()));
                throw $e;
            }
        }
        $this->logger->debug(sprintf('Results for %s sent to Solr.', $url->getUrl()));
    }

    /**
     * Create a solr document from a case.
     *
     * @param \Triquanta\AccessibilityMonitor\Url $url
     * @param \Triquanta\AccessibilityMonitor\TestRun $testRun
     * @param \Solarium\QueryType\Update\Query\Query $updateQuery
     * @param \stdClass $case
     *
     * @return \Solarium\QueryType\Update\Query\Document\DocumentInterface $doc
     *   Solr document
     */
    protected function caseToSolrDocument(
      Url $url,
      TestRun $testRun,
      Query $updateQuery,
      \stdClass $case
    ) {
        // Create a Solr document.
        if (property_exists($case, 'uniqueKey')) {
            /** @var \Solarium\QueryType\Update\Query\Document\Document $doc */
            $doc = $updateQuery->createDocument();
            $doc->setField('id', $this->createCaseSolrId($url, $testRun, $case));
            $doc->setFIeld('url', $url->getUrl());
            $doc->setField('url_id',
              $this->escapeStringForSolr($url->getUrl()));
            $doc->setField('url_main', $url->getMainDomain());
            $doc->setFIeld('host', $url->getHostName());
            if (property_exists($case->outcome, 'pointer')) {
                $doc->setField('element', $case->outcome->pointer[0]->chars);
            }
            if (property_exists($case->outcome, 'info')) {
                $doc->setField('name_en', $case->outcome->info->en);
                $doc->setField('name_nl', $case->outcome->info->nl);
            }
            $doc->setField('succescriterium', $case->criteriumName);
            $doc->setField('test_result', $case->outcome->result);
            $doc->setField('testtype', $case->testCase);
            // Add document type.
            $doc->setField('document_type', 'case');
            $doc->setField('website_test_results_id',
              $testRun->getWebsiteTestResultsId());

            return $doc;
        }

        return false;
    }

    /**
     * Create a solr id for a case.
     *
     * @param \Triquanta\AccessibilityMonitor\Url $url
     * @param \Triquanta\AccessibilityMonitor\TestRun $testRun
     * @param \stdClass $case
     *
     * @return string
     *   Case id.
     */
    protected function createCaseSolrId(Url $url, TestRun $testRun, \stdClass $case)
    {
        // Create a hash based om the url and uniqueKey of the case.
        $hash = md5($case->uniqueKey . '_' . $testRun->getWebsiteTestResultsId() . '_' . $url->getUrl());

        return $hash;
    }

    /**
     * Escapes a string for Solr.
     *
     * @param string $string
     *
     * @return string
     */
    protected function escapeStringForSolr($string)
    {
        $special_characters = array(
          "'",
          '+',
          '-',
          '&&',
          '||',
          '!',
          '(',
          ')',
          '{',
          '}',
          '[',
          ']',
          '^',
          '"',
          '~',
          '*',
          '?',
          ':',
          '\\'
        );

        return str_replace($special_characters, '_', $string);
    }

    /**
     * Creates a test run from a storage record.
     *
     * @param \stdClass $record
     *   A record from the test_run table.
     *
     * @return \Triquanta\AccessibilityMonitor\TestRun
     */
    protected function createTestRunFromStorageRecord(\stdClass $record)
    {
        $testRun = new TestRun();
        $testRun->setId($record->id)
          ->setGroup($record->group_name)
          ->setPriority($record->priority)
          ->setCreated($record->created)
          ->setWebsiteTestResultsId($record->website_test_results_id);

        return $testRun;
    }

    public function getTestRunById($id) {
        $query = $this->database->getConnection()
          ->prepare("SELECT * FROM test_run WHERE id = :id");
        $query->execute(array(
          'id' => $id,
        ));

        $record = $query->fetch(\PDO::FETCH_OBJ);

        return $record ? $this->createTestRunFromStorageRecord($record) : NULL;
    }

    public function saveTestRun(TestRun $testRun) {
        try {
            $values = array(
              'id' => $testRun->getId(),
              'priority' => $testRun->getPriority(),
              'created' => $testRun->getCreated(),
              'group' => $testRun->getGroup(),
              'queue' => $testRun->getQueue(),
            );
            if ($testRun->getId()) {
                $query = $this->database->getConnection()
                  ->prepare("UPDATE test_run SET queue = :queue, priority = :priority, created = :created, `group` = :group WHERE id = :id");
                $query->execute($values);
            } else {
                $insert = $this->database->getConnection()
                  ->prepare("INSERT INTO test_run (id, queue, priority, created, `group`) VALUES (:id, :queue, :priority, :created, :group)");
                $insert->execute($values);
            }
        }
        catch (\Exception $e) {
            throw new StorageException('A storage error occurred.', 0, $e);
        }
    }

    public function getTestRunToProcess() {
        // Get the IDs of all active test runs.
        $activeTestRunIdsSelectQuery = $this->database->getConnection()->prepare("
SELECT DISTINCT u.test_run_id
     FROM url u
     WHERE u.status = :status");
        $activeTestRunIdsSelectQuery->execute([
          'status' => TestingStatusInterface::STATUS_SCHEDULED,
        ]);
        $activeTestRunIds = $activeTestRunIdsSelectQuery->fetchAll(\PDO::FETCH_COLUMN, 0);

        if (empty($activeTestRunIds)) {
            return null;
        }

        // Get the names of groups that can be processed.
        $allowedGroupNamesSelectQuery = $this->database->getConnection()->prepare("
SELECT DISTINCT group_name FROM test_run WHERE last_processed < :last_processed");
        $allowedGroupNamesSelectQuery->execute([
        'last_processed' => time() - $this->testRunGroupFloodingThreshold,
        ]);
        $allowedGroupNames = $allowedGroupNamesSelectQuery->fetchAll(\PDO::FETCH_COLUMN, 0);

        if (empty($allowedGroupNames)) {
            return null;
        }

        // Of all the active test runs, load one that is available.
        $activeTestRunIdParameters = [];
        foreach ($activeTestRunIds as $i => $activeTestRunId) {
            $activeTestRunIdParameters[':test_run_' . $i] = $activeTestRunId;
        }
        $activeTestRunIdPlaceholders = implode(', ', array_keys($activeTestRunIdParameters));
        $allowedGroupNameParameters = [];
        foreach ($allowedGroupNames as $i => $allowedGroupName) {
            $allowedGroupNameParameters[':group_name_' . $i] = $allowedGroupName;
        }
        $allowedGroupNamePlaceholders = implode(', ', array_keys($allowedGroupNameParameters));
        $availableTestRunSelectQuery = $this->database->getConnection()->prepare(sprintf("
SELECT tr.*
     FROM test_run tr
     WHERE tr.id IN (%s)
          AND tr.group_name IN (%s)
     ORDER BY priority ASC, created ASC
     LIMIT 1", $activeTestRunIdPlaceholders, $allowedGroupNamePlaceholders));
        $parameters = array_merge($activeTestRunIdParameters, $allowedGroupNameParameters);

        $availableTestRunSelectQuery->execute($parameters);
        $record = $availableTestRunSelectQuery->fetch(\PDO::FETCH_OBJ);

        return $record ? $this->createTestRunFromStorageRecord($record) : null;
    }

}
