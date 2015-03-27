<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\Database.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines a database manager.
 */
class Database implements DatabaseInterface
{

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
     * @param string $hostName
     * @param string $databaseName
     * @param string $userName
     * @param string $password
     */
    public function __construct($hostName, $databaseName, $userName, $password)
    {
        $this->hostName = $hostName;
        $this->databaseName = $databaseName;
        $this->userName = $userName;
        $this->password = $password;
    }

    public function getConnection()
    {
        if (!$this->connection) {
            $data_source_name = 'mysql:host=' . $this->hostName . ';dbname=' . $this->databaseName;
            $pdo = new \PDO($data_source_name, $this->userName,
              $this->password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->connection = $pdo;
        }

        return $this->connection;
    }

}
