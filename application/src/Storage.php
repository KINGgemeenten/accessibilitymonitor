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
     * The AMQP queue.
     *
     * @var \PhpAmqpLib\Connection\AMQPStreamConnection
     */
    protected $amqpQueue;

    /**
     * The database manager.
     *
     * @var \Triquanta\AccessibilityMonitor\DatabaseInterface
     */
    protected $database;

    /**
     * The flooding threshold.
     *
     * @var int
     *   How long to wait between processing two items from the same queue.
     */
    protected $floodingThreshold;

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
     * @param int $floodingThreshold
     *   How long to wait between processing two items from the same queue.
     */
    public function __construct(
      DatabaseInterface $database,
      Client $solrClient,
      AMQPStreamConnection $amqpQueue,
      LoggerInterface $logger,
      $floodingThreshold
    ) {
        $this->amqpQueue = $amqpQueue;
        $this->database = $database;
        $this->floodingThreshold = $floodingThreshold;
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
    protected function createUrlFromStorageRecord($record)
    {
        $url = new Url();
        $url->setId($record->url_id)
          ->setWebsiteTestResultsId($record->website_test_results_id)
          ->setUrl($record->url)
          ->setTestingStatus($record->status)
          ->setCms($record->cms)
          ->setQuailResult(json_decode($record->quail_result))
          ->setGooglePageSpeedResult($record->pagespeed_result)
          ->setAnalysis($record->analysis)
          ->setRoot((bool) $record->is_root);

        return $url;
    }

    public function getUrlById($id)
    {
        $query = $this->database->getConnection()
          ->prepare("SELECT * FROM url WHERE url_id = :url_id");
        $query->execute(array(
          'url_id' => $id,
        ));

        $record = $query->fetch(\PDO::FETCH_OBJ);

        return $record ? $this->createUrlFromStorageRecord($record) : NULL;
    }

    public function saveUrl(Url $url)
    {
        $values = array(
          'status' => $url->getTestingStatus(),
          'cms' => $url->getCms(),
          'quail_result' => json_encode($url->getQuailResult()),
          'pagespeed_result' => $url->getGooglePageSpeedResult(),
          'analysis' => $url->getAnalysis(),
          'is_root' => (int) $url->isRoot(),
        );
        if ($url->getId()) {
            $values['url_id'] = $url->getId();
            $query = $this->database->getConnection()
              ->prepare("UPDATE url SET status = :status, cms = :cms, quail_result = :quail_result, pagespeed_result = :pagespeed_result, analysis = :analysis, is_root = :is_root WHERE url_id = :url_id");
            $dbSaveResult = $query->execute($values);
        } else {
            $values['url'] = $url->getUrl();
            $values['website_test_results_id'] = $url->getWebsiteTestResultsId();
            $insert = $this->database->getConnection()
              ->prepare("INSERT INTO url (website_test_results_id, url, status, cms, quail_result, pagespeed_result, analysis, is_root) VALUES (:website_test_results_id, :url, :status, :cms, :quail_result, :pagespeed_result, :analysis, :is_root)");
            $dbSaveResult = $insert->execute($values);
            $url->setId($this->database->getConnection()->lastInsertId());
        }
        $solrSaveResult = $this->sendCaseResultsToSolr($url);

        return $dbSaveResult && $solrSaveResult;
    }

    public function countUrlsByWebsiteTestResultsIdAndAnalysisDateTimePeriod($websiteTestResultsId, $start, $end) {
        $query = $this->database->getConnection()
          ->prepare("SELECT COUNT(1) FROM url WHERE website_test_results_id = :website_test_results_id AND analysis > :start AND analysis < :end");
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
     * @return bool
     *   Whether saving the data was successful or not.
     */
    protected function sendCaseResultsToSolr(Url $url)
    {
        $this->logger->debug(sprintf('Sending results for %s to Solr.', $url->getUrl()));
        // First check if there are results to send.
        if ($url->getQuailResultCases()) {
            // Create an update query.
            $updateQuery = $this->solrClient->createUpdate();

            // Create an array of documents.
            $docs = array();
            // Now start adding the cases.
            foreach ($url->getQuailResultCases() as $case) {
                $doc = $this->caseToSolrDocument($url, $updateQuery, $case);
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
                return false;
            }
        }
        $this->logger->debug(sprintf('Results for %s sent to Solr.', $url->getUrl()));
        return true;
    }

    /**
     * Create a solr document from a case.
     *
     * @param \Triquanta\AccessibilityMonitor\Url $url
     * @param \Solarium\QueryType\Update\Query\Query $updateQuery
     * @param \stdClass $case
     *
     * @return \Solarium\QueryType\Update\Query\Document\DocumentInterface $doc
     *   Solr document
     */
    protected function caseToSolrDocument(
      Url $url,
      Query $updateQuery,
      \stdClass $case
    ) {
        // Create a Solr document.
        if (property_exists($case, 'uniqueKey')) {
            /** @var \Solarium\QueryType\Update\Query\Document\Document $doc */
            $doc = $updateQuery->createDocument();
            $doc->setField('id', $this->createCaseSolrId($url, $case));
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
              $url->getWebsiteTestResultsId());

            return $doc;
        }

        return false;
    }

    /**
     * Create a solr id for a case.
     *
     * @param \Triquanta\AccessibilityMonitor\Url $url
     * @param \stdClass $case
     *
     * @return string
     *   Case id.
     */
    protected function createCaseSolrId(Url $url, \stdClass $case)
    {
        // Create a hash based om the url and uniqueKey of the case.
        $hash = md5($case->uniqueKey . '_' . $url->getWebsiteTestResultsId() . '_' . $url->getUrl());

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
     * Creates a queue from a storage record.
     *
     * @param \stdClass $record
     *   A record from the queue table.
     *
     * @return \Triquanta\AccessibilityMonitor\Queue
     */
    protected function createQueueFromStorageRecord($record)
    {
        $queue = new Queue();
        $queue->setId($record->id)
          ->setWebsiteTestResultsId($record->website_test_results_id)
          ->setPriority($record->priority)
          ->setCreated($record->created)
          ->setLastRequest($record->last_request);

        return $queue;
    }

    public function getQueueById($id) {
        $query = $this->database->getConnection()
          ->prepare("SELECT * FROM queue WHERE id = :id");
        $query->execute(array(
          'id' => $id,
        ));

        $record = $query->fetch(\PDO::FETCH_OBJ);

        return $record ? $this->createQueueFromStorageRecord($record) : NULL;
    }

    public function saveQueue(Queue $queue) {
        $values = array(
          'id' => $queue->getId(),
          'priority' => $queue->getPriority(),
          'created' => $queue->getCreated(),
          'last_request' => $queue->getLastRequest(),
          'website_test_results_id' => $queue->getWebsiteTestResultsId(),
        );
        if ($queue->getId()) {
            $query = $this->database->getConnection()
              ->prepare("UPDATE queue SET id = :id, priority = :priority, created = :created, last_request = :last_request, website_test_results_id = :website_test_results_id WHERE id = :id");
            $dbSaveResult = $query->execute($values);
        } else {
            $insert = $this->database->getConnection()
              ->prepare("INSERT INTO queue (id, priority, created, last_request, website_test_results_id) VALUES (:d, :priority, :created, :last_request, :website_test_results_id)");
            $dbSaveResult = $insert->execute($values);
        }

        return $dbSaveResult;
    }

    public function getQueueToSubscribeTo() {
        $this->deleteEmptyQueues();

        $query = $this->database->getConnection()->prepare("SELECT * FROM queue WHERE last_request < :last_request ORDER BY priority ASC, created ASC LIMIT 1");
        $query->execute([
          'last_request' => time() - $this->floodingThreshold,
        ]);
        $record = $query->fetch(\PDO::FETCH_OBJ);

        return $record ? $this->createQueueFromStorageRecord($record) : NULL;
    }

    /**
     * Deletes empty queues.
     *
     * @return $this
     */
    protected function deleteEmptyQueues() {
        $selectQuery = $this->database->getConnection()->prepare("SELECT q.id FROM queue q WHERE q.id NOT IN (SELECT q.id FROM queue q INNER JOIN url u ON u.website_test_results_id = q.website_test_results_id WHERE u.status = :status GROUP BY id)");
        $selectQuery->execute([
            'status' => TestingStatusInterface::STATUS_SCHEDULED,
        ]);
        while ($queueId = $selectQuery->fetchColumn()) {
            $deleteQuery = $this->database->getConnection()->prepare("DELETE FROM queue WHERE queue.id = :queue_id");
            $deleteQuery->execute([
              'queue_id' => $queueId,
            ]);

            $this->amqpQueue->channel()->queue_delete($queueId);
        }

        return $this;
    }

}
