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
          ->setLastProcessedTime($record->last_processed)
          ->setRoot((bool) $record->is_root)
          ->setQueueName($record->queue_name)
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
                  'website_test_results_id' => $url->getWebsiteTestResultsId(),
                ];
                $insertUrlQuery = $this->database->getConnection()
                  ->prepare("INSERT INTO url (website_test_results_id, url, status, last_processed, is_root) VALUES (:website_test_results_id, :url, :status, :last_processed, :is_root)");
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
                throw $e;
            }
        }
        $this->logger->debug(sprintf('Results for %s sent to Solr.', $url->getUrl()));
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
        $queue->setName($record->name)
          ->setPriority($record->priority)
          ->setCreated($record->created)
          ->setLastRequest($record->last_request);

        return $queue;
    }

    public function getQueueByName($name) {
        $query = $this->database->getConnection()
          ->prepare("SELECT * FROM queue WHERE name = :name");
        $query->execute(array(
          'name' => $name,
        ));

        $record = $query->fetch(\PDO::FETCH_OBJ);

        return $record ? $this->createQueueFromStorageRecord($record) : NULL;
    }

    public function saveQueue(Queue $queue) {
        try {
            $values = array(
              'name' => $queue->getName(),
              'priority' => $queue->getPriority(),
              'created' => $queue->getCreated(),
              'last_request' => $queue->getLastRequest(),
            );
            if ($queue->getName()) {
                $query = $this->database->getConnection()
                  ->prepare("UPDATE queue SET name = :name, priority = :priority, created = :created, last_request = :last_request WHERE name = :name");
                $query->execute($values);
            } else {
                $insert = $this->database->getConnection()
                  ->prepare("INSERT INTO queue (id, priority, created, last_request) VALUES (:d, :priority, :created, :last_request)");
                $insert->execute($values);
            }
        }
        catch (\Exception $e) {
            throw new StorageException('A storage error occurred.', 0, $e);
        }
    }

    public function getQueueToSubscribeTo() {
        // No events are dispatched when queues are empty, so we cannot delete
        // queues once all their items have been processed. Instead we delete
        // empty queues here, because this is the only moment where it matters
        // to have no empty queues at all.
        $this->deleteEmptyQueues();

        // Get the names of all active queues.
        $activeQueueNamesSelectQuery = $this->database->getConnection()->prepare("
SELECT DISTINCT u.queue_name
     FROM url u
     WHERE u.status = :status");
        $activeQueueNamesSelectQuery->execute([
          'status' => TestingStatusInterface::STATUS_SCHEDULED,
        ]);
        $activeQueueNames = $activeQueueNamesSelectQuery->fetchAll(\PDO::FETCH_COLUMN, 0);

        if (empty($activeQueueNames)) {
            return null;
        }

        // Of all the active queues, load one that is available.
        $parameters = [];
        foreach ($activeQueueNames as $i => $activeQueueName) {
            $parameters[':queue_' . $i] = $activeQueueName;
        }
        $placeholders = implode(', ', array_keys($parameters));
        $availableQueueSelectQuery = $this->database->getConnection()->prepare(sprintf("
SELECT q.*
     FROM queue q
     WHERE q.name IN (%s)
          AND q.last_request < :last_request
     ORDER BY last_request ASC, priority ASC, created ASC
     LIMIT 1", $placeholders));
        $parameters['last_request'] = time() - $this->floodingThreshold;
        $availableQueueSelectQuery->execute($parameters);
        $record = $availableQueueSelectQuery->fetch(\PDO::FETCH_OBJ);

        return $record ? $this->createQueueFromStorageRecord($record) : null;
    }

    /**
     * Deletes empty queues.
     *
     * @return $this
     */
    protected function deleteEmptyQueues()
    {
        $this->logger->info('Collecting empty queues.');

        // Get the names of the queues for which URLs must still be tested.
        $activeQueueSelectQuery = $this->database->getConnection()->prepare("
SELECT DISTINCT u.queue_name
     FROM url u
     WHERE u.status IN (:status_scheduled,
                        :status_scheduled_for_retest)");
        $activeQueueSelectQuery->execute([
          'status_scheduled' => TestingStatusInterface::STATUS_SCHEDULED,
          'status_scheduled_for_retest' => TestingStatusInterface::STATUS_SCHEDULED_FOR_RETEST,
        ]);
        $activeQueueNames = $activeQueueSelectQuery->fetchAll(\PDO::FETCH_COLUMN, 0);

        // Get the names of the queues for which all URLs have been tested.
        if ($activeQueueNames) {
            $parameters = [];
            foreach ($activeQueueNames as $i => $activeQueueName) {
                $parameters[':queue_' . $i] = $activeQueueName;
            }
            $placeholders = implode(', ', array_keys($parameters));
            $inactiveQueueSelectQuery = $this->database->getConnection()
              ->prepare(sprintf("
    SELECT q.name
    FROM queue q
    WHERE q.name NOT IN (%s)", $placeholders));
            $inactiveQueueSelectQuery->execute($parameters);
        } else {
            $inactiveQueueSelectQuery = $this->database->getConnection()
              ->prepare("
    SELECT q.name
    FROM queue q");
            $inactiveQueueSelectQuery->execute();
        }
        $inactiveQueueNames = $inactiveQueueSelectQuery->fetchAll(\PDO::FETCH_COLUMN,
          0);

        if ($inactiveQueueNames) {
            $this->logger->info(sprintf('Preparing to delete empty queues: %s',
              implode(', ', $inactiveQueueNames)));
            foreach ($inactiveQueueNames as $queueName) {
                $this->logger->debug(sprintf('Preparing to delete queue %s.',
                  $queueName));

                // Delete the queue from RabbitMQ.
                $this->amqpQueue->channel()->queue_delete($queueName);
                $this->logger->debug(sprintf('Successfully removed queue %s from RabbitMQ.',
                  $queueName));

                // Delete the queue from the database.
                // To prevent race conditions only delete if we got at least one message from RabbitMQ
                // and the last request was more than 5 minutes ago.
                // "last_request" should be bigger than 0 and smaller than time() - 300
                // @todo make the last_request_offset a setting, or use a better mechanism to prevent race conditions
                $deleteQuery = $this->database->getConnection()
                  ->prepare("DELETE FROM queue WHERE queue.name = :queue_name and queue.last_request > 0 and queue.last_request < :last_request_offset");
                $result = $deleteQuery->execute([
                  'queue_name' => $queueName,
                  'last_request_offset' => time() - 300,
                ]);
                if ($result) {
                    $this->logger->debug(sprintf('Successfully removed queue %s from the database.',
                      $queueName));
                } else {
                    $errorInfo = $this->database->getConnection()->errorInfo();;
                    $pdoCode = $errorInfo[0];
                    $driverCode = array_key_exists(1,
                      $errorInfo) ? $errorInfo[1] : null;
                    $driverMessage = array_key_exists(2,
                      $errorInfo) ? $errorInfo[2] : null;
                    throw new \RuntimeException(sprintf('Failed to remove queue %s from the database. The PDO error was %s: %s (%s).',
                      $queueName, $pdoCode, $driverMessage, $driverCode));
                }
            }
        } else {
            $this->logger->info('No empty queues found.');
        }

        return $this;
    }

}
