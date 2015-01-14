<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\DatabaseStorage.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Provides a database-based storage manager.
 */
class DatabaseStorage implements StorageInterface {

  /**
   * The database connection.
   *
   * @var \PDO
   */
  protected $connection;

  /**
   * The database name.
   *
   * @var string
   */
  protected $databaseName;

  /**
   * The database host name.
   *
   * @var string
   */
  protected $hostName;

  /**
   * The database password.
   *
   * @var string
   */
  protected $password;

  /**
   * The database username.
   *
   * @var string
   */
  protected $userName;

  /**
   * Constructs a new instance.
   *
   * @param string $host_name
   * @param string $database_name
   * @param string $user_name
   * @param string $password
   */
  public function __construct($host_name, $database_name, $user_name, $password) {
    $this->hostName = $host_name;
    $this->databaseName = $database_name;
    $this->userName = $user_name;
    $this->password = $password;
  }

  /**
   * Gets a connection to the database.
   *
   * @return \PDO
   */
  protected function getConnection() {
    if (!$this->connection) {
      $data_source_name = 'mysql:host=' . $this->hostName . ';dbname=' . $this->databaseName;
      $pdo = new \PDO($data_source_name, $this->userName, $this->password);
      $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      $this->connection = $pdo;
    }

    return $this->connection;
  }

  /**
   * Creates a URL from a storage record.
   *
   * @param \stdClass $record
   *   A record from the url table.
   *
   * @return \Triquanta\AccessibilityMonitor\Url
   */
  protected function createUrlFromStorageRecord($record) {
    $url = new Url();
    $url->setId($record->url_id)
      ->setWebsiteTestResultsId($record->website_test_results_id)
      ->setUrl($record->url)
      ->setTestingStatus($record->status)
      ->setPriority($record->priority)
      ->setCms($record->cms)
      ->setQuailResult(json_decode($record->quail_result))
      ->setGooglePagespeedResult($record->pagespeed_result)
      ->setAnalysis($record->analysis)
      ->setRoot((bool) $record->is_root);

    return $url;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlsByStatus($status, $limit = NULL) {
    $query_string = "SELECT * FROM url WHERE status = :status ORDER BY priority ASC, RAND()";
    if ($limit) {
      $query_string .= " LIMIT " . $limit;
    }
    $query = $this->getConnection()->prepare($query_string);
    $query->execute(array(
      'status' => $status,
    ));
    $urls = array();
    while ($record = $query->fetch(\PDO::FETCH_OBJ)) {
      $urls[] = $this->createUrlFromStorageRecord($record);
    }

    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlById($id) {
    $query = $this->getConnection()->prepare("SELECT * FROM url WHERE url_id = :url_id");
    $query->execute(array(
      'url_id' => $id,
    ));
    $urls = array();
    while ($record = $query->fetch(\PDO::FETCH_OBJ)) {
      $urls[] = $this->createUrlFromStorageRecord($record);
    }

    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlsByWebsiteTestResultsId($website_test_results_id) {
    $query = $this->getConnection()->prepare("SELECT * FROM url WHERE website_test_results_id = :website_test_results_id");
    $query->execute(array(
      'website_test_results_id' => $website_test_results_id,
    ));
    $urls = array();
    while ($record = $query->fetch(\PDO::FETCH_OBJ)) {
      $urls[] = $this->createUrlFromStorageRecord($record);
    }

    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlsByNotStatus($status) {
    $query = $this->getConnection()->prepare("SELECT * FROM url WHERE cms IS NULL AND status != :status");
    $query->execute(array(
      'status' => $status
    ));
    $urls = array();
    while ($record = $query->fetch(\PDO::FETCH_OBJ)) {
      $urls[] = $this->createUrlFromStorageRecord($record);
    }

    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlsWithoutGooglePagespeedScore($limit = NULL) {
    $query_string = "SELECT * FROM url WHERE pagespeed_result IS NULL";
    if (is_int($limit)) {
      $query_string .= " LIMIT " . $limit;
    }
    $query = $this->getConnection()->prepare($query_string);
    $query->execute();
    $urls = array();
    while ($record = $query->fetch(\PDO::FETCH_OBJ)) {
      $urls[] = $this->createUrlFromStorageRecord($record);
    }

    return $urls;
  }

  /**
   * {@inheritdoc}
   */
  public function countUrlsByWebsiteTestResultsId($website_test_results_id) {
    $query = $this->getConnection()->prepare("SELECT count(*) FROM url WHERE website_test_results_id = :website_test_results_id");
    $query->execute(array(
      'website_test_results_id' => $website_test_results_id,
    ));

    return $query->fetchColumn();
  }

  /**
   * {@inheritdoc}
   */
  public function countUrlsByWebsiteTestResultsIdAndUrl($website_test_results_id, $url) {
    $query = $this->getConnection()->prepare("SELECT count(*) FROM url WHERE website_test_results_id = :website_test_results_id AND url = :url");
    $query->execute(array(
      'url' => $url,
      'website_test_results_id' => $website_test_results_id,
    ));

    return $query->fetchColumn();
  }

  /**
   * Counts URLs by status.
   *
   * @param int $status
   *   One of the self::STATUS_* constants.
   *
   * @return int
   */
  public function countUrlsByStatus($status) {
    $query_string = "SELECT count(*) FROM url WHERE status = :status";
    $query = $this->getConnection()->prepare($query_string);
    $query->execute(array(
      'status' => $status,
    ));

    return $query->fetchColumn();
  }

  /**
   * {@inheritdoc}
   */
  public function countUrlsByStatusAndWebsiteId($status, $website_test_results_id) {
    $query = $this->getConnection()->prepare("SELECT count(*) FROM url WHERE website_test_results_id = :website_test_results_id AND status = :status");
    $query->execute(array(
      'website_test_results_id' => $website_test_results_id,
      'status' => $status,
    ));

    return $query->fetchColumn();
  }

  /**
   * {@inheritdoc}
   */
  public function saveUrl(Url $url) {
    $values = array(
      'status' => $url->getTestingStatus(),
      'priority' => $url->getPriority(),
      'cms' => $url->getCms(),
      'quail_result' => json_encode($url->getQuailResult()),
      'pagespeed_result' => $url->getGooglePagespeedResult(),
      'analysis' => $url->getAnalysis(),
      'is_root' => (int) $url->isRoot(),
    );
    if ($url->getId()) {
      $values['url_id'] = $url->getId();
      $query = $this->getConnection()->prepare("UPDATE url SET status = :status, cms = :cms, quail_result = :quail_result, pagespeed_result = :pagespeed_result, priority = :priority, analysis = :analysis, is_root = :is_root WHERE url_id = :url_id");
      $query->execute($values);
    }
    else {
      $values['url'] = $url->getUrl();
      $values['website_test_results_id'] = $url->getWebsiteTestResultsId();
      $insert = $this->getConnection()->prepare("INSERT INTO url (website_test_results_id, url, status, priority, cms, quail_result, pagespeed_result, analysis, is_root) VALUES (:website_test_results_id, :url, :status, :priority, :cms, :quail_result, :pagespeed_result, :analysis, :is_root)");
      $insert->execute($values);
      $url->setId($this->getConnection()->lastInsertId());
    }

    return $this;
  }

  /**
   * Counts the number of CMS test results for a website.
   *
   * @param int $website_test_results_id
   *
   * @return int
   */
  public function countCmsTestResultsByWebsiteTestResultsId($website_test_results_id) {
    $query = $this->getConnection()->prepare("SELECT COUNT(*) FROM url WHERE website_test_results_id = :website_test_results_id AND cms IS NOT NULL");
    $query->execute(array(
      'website_test_results_id' => $website_test_results_id,
    ));

    return $query->fetchColumn();
  }

  /**
   * Counts the number of Google PageSpeed results for a website.
   *
   * @param int $website_test_results_id
   *
   * @return int
   */
  public function countGooglePagespeedResultsByWebsiteTestResultsId($website_test_results_id) {
    $query = $this->getConnection()->prepare("SELECT COUNT(*) FROM url WHERE website_test_results_id = :website_test_results_id AND pagespeed_result <> ''");
    $query->execute(array(
      'website_test_results_id' => $website_test_results_id,
    ));

    return $query->fetchColumn();
  }

  /**
   * Gets the datetime of any URL's last analysis.
   *
   * @return int
   *   A Unix timestamp.
   */
  public function getUrlLastAnalysisDateTime() {
    $query = $this->getConnection()->prepare("SELECT analysis FROM url WHERE ORDER BY analysis DESC LIMIT 1");
    $query->execute();

    return $query->fetchColumn();

  }

}
