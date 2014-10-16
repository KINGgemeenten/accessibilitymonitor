<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Database.
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
      ->setWebsiteId($record->website_id)
      ->setUrl($record->full_url)
      ->setTestingStatus($record->status)
      ->setPriority($record->priority)
      ->setCms($record->cms)
      ->setQuailResult($record->quail_result)
      ->setGooglePagespeedResult($record->pagespeed_result);

    return $url;
  }

  /**
   * Creates a website from a storage record.
   *
   * @param \stdClass $record
   *   A record from the website table.
   *
   * @return \Triquanta\AccessibilityMonitor\Website
   */
  protected function createWebsiteFromStorageRecord($record) {
    $website = new Website();
    $website->setId($record->website_id)
      ->setUrl($record->url)
      ->setTestingStatus($record->status)
      ->setLastAnalysis($record->last_analysis);

    return $website;
  }

  /**
   * Creates an action from a storage record.
   *
   * @param \stdClass $record
   *   A record from the actions table.
   *
   * @return \Triquanta\AccessibilityMonitor\Action
   */
  protected function createActionFromStorageRecord($record) {
    $website = new Action();
    $website->setId($record->aid)
      ->setAction($record->action)
      ->setUrl($record->item_uid)
      ->setTimestamp($record->timestamp);

    return $website;
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlsByStatus($status, $limit = NULL) {
    $query = $this->getConnection()->prepare("SELECT * FROM url WHERE status = :status ORDER BY priority ASC LIMIT " . $limit);
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
  public function getUrlsByWebsiteId($website_id) {
    $query = $this->getConnection()->prepare("SELECT * FROM url WHERE website_id = :website_id");
    $query->execute(array(
      'website_id' => $website_id,
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
  public function countUrlsByWebsiteId($website_id) {
    $query = $this->getConnection()->prepare("SELECT count(*) FROM url WHERE website_id = :website_id");
    $query->execute(array(
      'website_id' => $website_id,
    ));

    return $query->fetchColumn();
  }

  /**
   * {@inheritdoc}
   */
  public function countUrlsByWebsiteIdAndFullUrl($website_id, $full_url) {
    $query = $this->getConnection()->prepare("SELECT count(*) FROM url WHERE website_id = :website_id AND full_url = :full_url");
    $query->execute(array(
      'full_url' => $full_url,
      'website_id' => $website_id,
    ));

    return $query->fetchColumn();
  }

  /**
   * {@inheritdoc}
   */
  public function countUrlsByStatusAndWebsiteId($status, $website_id) {
    $query = $this->getConnection()->prepare("SELECT count(*) FROM url WHERE website_id = :website_id AND status = :status");
    $query->execute(array(
      'website_id' => $website_id,
      'status' => $status,
    ));

    return $query->fetchColumn();
  }

  /**
   * {@inheritdoc}
   */
  public function getWebsitesByStatuses(array $statuses) {
    $fields = array();
    foreach ($statuses as $i => $status) {
      $fields[':status' . $i] = $status;
    }
    $query = $this->getConnection()->prepare(sprintf("SELECT * FROM website WHERE status IN (%s)", implode(', ', array_keys($fields))));
    $query->execute($fields);
    $websites = array();
    while ($record = $query->fetchObject()) {
      $websites[] = $this->createWebsiteFromStorageRecord($record);
    }

    return $websites;
  }

  /**
   * {@inheritdoc}
   */
  public function getWebsiteById($website_id) {
    $query = $this->getConnection()->prepare("SELECT * FROM website WHERE website_id = :website_id");
    $query->execute(array(
      'website_id' => $website_id,
    ));
    $record = $query->fetchObject();

    return $record ? $this->createWebsiteFromStorageRecord($record) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getWebsiteByUrl($url) {
    $query = $this->getConnection()->prepare("SELECT * FROM website WHERE url = :url");
    $query->execute(array(
      'url' => $url,
    ));
    $record = $query->fetchObject();

    return $record ? $this->createWebsiteFromStorageRecord($record) : NULL;
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
    );
    if ($url->getId()) {
      $values['url_id'] = $url->getId();
      $query = $this->getConnection()->prepare("UPDATE url SET status = :status, cms = :cms, quail_result = :quail_result, pagespeed_result = :pagespeed_result, priority = :priority WHERE url_id = :url_id");
      $query->execute($values);
    }
    else {
      $values['full_url'] = $url->getUrl();
      $values['website_id'] = $url->getWebsiteId();
      $insert = $this->getConnection()->prepare("INSERT INTO url (website_id, full_url, status, priority, cms, quail_result, pagespeed_result) VALUES (:website_id, :full_url, :status, :priority, :cms, :quail_result, :pagespeed_result)");
      $insert->execute($values);
      $url->setId($this->getConnection()->lastInsertId());
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function saveWebsite(Website $website) {
    $values = array(
      'status' => $website->getTestingStatus(),
      'last_analysis' => $website->getLastAnalysis(),
    );
    if ($website->getId()) {
      $values['website_id'] = $website->getId();
      $query = $this->getConnection()->prepare("UPDATE website SET last_analysis = :last_analysis, url = :url, status = :status WHERE website_id = :website_id");
      $query->execute($values);
    }
    else {
      $values['url'] = $website->getUrl();
      $sql = "INSERT INTO website (url, status, last_analysis) VALUES (:url, :status, :last_analysis)";
      $insert = $this->getConnection()->prepare($sql);
      $insert->execute($values);
      $website->setId($this->getConnection()->lastInsertId());
    }

    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getWebsiteLastAnalysisDateTime() {
    // Get the total amount url's so we can define a start for Solr.
    $query = $this->getConnection()->prepare("SELECT last_analysis FROM website ORDER BY last_analysis DESC LIMIT 1");
    $query->execute();

    return $query->fetchColumn();
  }

  /**
   * Counts the number of CMS test results for a website.
   *
   * @param int $website_id
   *
   * @return int
   */
  public function countCmsTestResultsByWebsiteId($website_id) {
    $query = $this->getConnection()->prepare("SELECT COUNT(*) FROM url WHERE website_id = :website_id AND cms IS NOT NULL");
    $query->execute(array(
      'website_id' => $website_id,
    ));

    return $query->fetchColumn();
  }

  /**
   * Counts the number of Google PageSpeed results for a website.
   *
   * @param int $website_id
   *
   * @return int
   */
  public function countGooglePagespeedResultsByWebsiteId($website_id) {
    $query = $this->getConnection()->prepare("SELECT COUNT(*) FROM url WHERE website_id = :website_id AND pagespeed_result <> ''");
    $query->execute(array(
      'website_id' => $website_id,
    ));

    return $query->fetchColumn();
  }

  /**
   * {@inheritdoc}
   */
  public function getWebsiteIdForNestedUrl($nested_url) {
    $query = $this->getConnection()->prepare("SELECT website_id FROM website WHERE :url LIKE CONCAT('%', url, '%')");
    $query->execute(array(
      'url' => $nested_url,
    ));

    return $query->fetchColumn();
  }

  /**
   * {@inheritdoc}
   */
  public function getUrlByUrl($url) {
    $query = $this->getConnection()->prepare("SELECT * FROM url WHERE full_url = :url");
    $query->execute(array(
      'url' => $url,
    ));
    $record = $query->fetchObject();

    return $record ? $this->createUrlFromStorageRecord($record) : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getPendingActions() {
    $query = $this->getConnection()->prepare("SELECT * FROM actions WHERE timestamp = 0");
    $query->execute();
    $actions = array();
    while ($record = $query->fetchObject()) {
      $actions[] = $this->createActionFromStorageRecord($record);
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function saveAction(Action $action) {
    if (!$action->getId()) {
      throw new \InvalidArgumentException('The action does not exist yet.');
    }
    $values = array(
      'aid' => $action->getId(),
      'timestamp' => $action->getTimestamp(),
    );
    $query = $this->getConnection()->prepare("UPDATE actions SET timestamp = :timestamp WHERE aid = :aid");
    $query->execute($values);

    return $this;
  }

}
