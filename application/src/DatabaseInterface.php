<?php

/**
 * @file
 * Contains \Triquanta\AccessibilityMonitor\DatabaseInterface.
 */

namespace Triquanta\AccessibilityMonitor;

/**
 * Defines a database manager.
 */
interface DatabaseInterface
{

    /**
     * Gets a connection to the database.
     *
     * @return \PDO
     */
    public function getConnection();

}
