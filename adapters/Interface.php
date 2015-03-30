<?php

namespace intelworx\dbvc\adapters;

interface DBV_Adapter_Interface {

    /**
     * Connects to the database
     * @throws \intelworx\dbvc\DBMigrationException
     */
    public function connect(
    $host = false, $port = false, $username = false, $password = false, $database_name = false
    );

    /**
     * Runs an SQL query
     * @throws \intelworx\dbvc\DBMigrationException
     */
    public function query($sql);

    /**
     * Checks if the table for storing version information exists
     * @param string $name
     * @return boolean true if it does, false otherwise
     */
    public function versionTableExists($name);

    /**
     * 
     * Creates schema version table with the speciifed name.
     * The table must conform to the following MySQL table structure:
     * 
     * CREATE TABLE IF NOT EXISTS `{$name}`(
     *   `version_id` INT NOT NULL,
     *   `file` VARCHAR(255) NOT NULL,
     *   `checksum` VARCHAR(42) NOT NULL,
     *   `title` VARCHAR(255),
     *   `created_on` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
     *   PRIMARY KEY (`version_id`)
     * )
     *      * 
     * @param boolean $name
     */
    public function createVersionTable($name);
}
