<?php

namespace intelworx\dbvc;

if (!defined('DS')) {
    define('DS', DIRECTORY_SEPARATOR);
}

use Exception;
use intelworx\dbvc\adapters\DBV_Adapter_Interface;
use PDO;
use PDOStatement;

/**
 * Description of DBMigration
 *
 * @author JosephT
 */
class DBMigration {

    /**
     *
     * @var DBV_Adapter_Interface
     */
    protected $_adapter;
    protected $revisionsDir;
    protected $schemaVersionTable = 'dvbc__schema_version';

    /**
     *
     * @var DBMigrationRevision[]
     */
    protected $newRevisions = [];

    /**
     *
     * @var DBMigrationRevision[]
     */
    protected $appliedRevisions = [];
    protected $maxRevision = 0;
    protected $latestRevision = 0;
    protected $dbConfig = array();
    protected $badRevisions = array();

    /**
     * 
     * @param string $revisionsDir path to directory storing your database revisions
     * @param array $dbConfig a configuration for you database, must have the following defined:
     *     adapter : Database adapter to use, must exist in adapters/{adapater}.sql
     *     host : The host name
     *     port : Database port number.
     *     username : Database username
     *     password : Database user's password
     *     database : The name of the database/schema to be used.
     * 
     * @throws DBMigrationException
     */
    public function __construct($revisionsDir, array $dbConfig = array()) {
        $this->revisionsDir = realpath($revisionsDir);
        if (!$this->revisionsDir || !is_dir($this->revisionsDir) || !is_readable($this->revisionsDir)) {
            //init revisions directory 
            throw new DBMigrationException("Either the specified revisions directory ({$revisionsDir}) does not exist or is not a readable directory");
        }

        $this->dbConfig = $dbConfig;
        $badRevFile = $this->revisionsDir . '/bad_revisions' . '.txt';

        if (file_exists($badRevFile)) {
            $this->badRevisions = file($badRevFile);
            array_walk($this->badRevisions, function(&$rev) {
                $rev = intval(trim($rev));
            });
            $this->badRevisions = array_filter($this->badRevisions);
        }
    }

    public function getDbConfig() {
        return $this->dbConfig;
    }

    public function setDbConfig($dbConfig) {
        $this->dbConfig = $dbConfig;
        return $this;
    }

    public function runMigration($mode = DBMigrationMode::NON_INTERACTIVE, $baseVersion = 0) {
        //load revisions from directory...
        self::el('Loading applied revisions...');
        $this->loadAppliedRevisions();
        self::el('Total revisions loaded: ', count($this->appliedRevisions));
        self::el('DB currently at version : ', $this->maxRevision, PHP_EOL);
        self::el('Loading new revisions from filesystem');
        if ($this->maxRevision < $baseVersion) {
            $this->maxRevision = $baseVersion;
            self::el('Migration will be started at version : ', $this->maxRevision, PHP_EOL);
        }

        $this->loadNewRevisions();

        self::el('Loaded', count($this->newRevisions), 'revision(s)');
        self::el('Target Schema Version : ', $this->latestRevision);

        switch ($mode) {
            case DBMigrationMode::INTERACTIVE:
                $this->_runInteractive();
                break;

            case DBMigrationMode::NON_INTERACTIVE:
                $this->_runNonInteractive();
                break;

            case DBMigrationMode::DRY_RUN:
            default :
                $this->_runDryRun();
                break;
        }
    }

    private function _runInteractive() {
        self::el("Running in interactive mode.");
        if (count($this->newRevisions)) {
            echo 'The following revisions will be applied: ', PHP_EOL;
            foreach ($this->newRevisions as $revision) {
                echo $revision->getVersionId(), ': ', $revision->getTitle(), PHP_EOL;
            }

            $resp = self::readInput('Do you want to apply these revisions (no)? [yes/no]', 'n');
            if (strtolower(trim($resp)) === 'yes') {
                $this->_runNonInteractive();
            } else {
                self::el('OK, Bye!');
            }
        } else {
            self::el('There are no revisions to apply.');
        }
    }

    private function _runNonInteractive() {
        self::el('Starting migrations...');

        $adapter = $this->_getAdapter();
        $lastVersion = $this->maxRevision;
        foreach ($this->newRevisions as $revision) {
            if (!in_array($revision->getVersionId(), $this->badRevisions)) {
                self::el('Migrating from ', $lastVersion, 'to', $revision->getVersionId(), ': ', $revision->getTitle());
                $queries = $this->getContent($revision);
                $adapter->query($queries);
                self::el('Successfully applied');
                $this->_saveAppliedRevision($revision);
            } else {
                self::el('Skipped Bad Revision, ', $revision->getVersionId());
            }

            $lastVersion = $revision->getVersionId();
        }
        self::el('Successfully migrated DB to version : ', $lastVersion);
    }

    private function _runDryRun() {
        self::el(str_repeat('+', 30));
        self::el('Running in Dry Run mode, pipe into less for better result');
        self::el(str_repeat('+', 30));
        self::el("DB will be migrated from version {$this->maxRevision} to {$this->latestRevision}");
        foreach ($this->newRevisions as $aRevision) {
            self::el('Revision Version: ', $aRevision->getVersionId());
            self::el('Description: ', $aRevision->getTitle());
            self::el('File: ', $aRevision->getFile());
            self::el(str_repeat('-', 30));
            self::el('Content', PHP_EOL, "\t", $this->getContent($aRevision));
        }
    }

    private function _saveAppliedRevision(DBMigrationRevision $revision) {
        $sql = $this->_buildInsert([$revision->toFieldArray()]);
        return $this->_getAdapter()->query($sql);
    }

    private function checkTableExists() {
        $db = $this->_getAdapter();
        if (!$db->versionTableExists($this->schemaVersionTable)) {
            self::el('Seems SCHEMA VERSION table is not existent,');
            $db->createVersionTable($this->schemaVersionTable);
        }
    }

    private function loadAppliedRevisions() {
        //check if table exists, if not create
        $this->checkTableExists();
        //load revisions applied
        $result = $this->_getAdapter()->query("SELECT * FROM `{$this->schemaVersionTable}`");
        /* @var $result PDOStatement */

        //since this uses the same column ordering as legacy
        $result->setFetchMode(PDO::FETCH_NUM);

        foreach ($result as $row) {
            $revision = DBMigrationRevision::fromLine($row);
            $path = $this->revisionsDir . '/' . $revision->getFile();

            if (!file_exists($path)) {
                throw new DBMigrationException("Applied migration at version {$revision->getVersionId()}, {$revision->getFile()} was not found");
            } else {
                $fileChecksum = DBMigrationRevision::computeChecksum($path);
                if ($fileChecksum !== $revision->getChecksum()) {
                    throw new DBMigrationException("Applied migration is no longer valid, applied migration at Version {$revision->getVersionId()} = {$revision->getChecksum()} ; Revision File = {$fileChecksum}");
                }
            }
            $this->appliedRevisions[$revision->getVersionId()] = $revision;
        }

        //close cursor
        $result->closeCursor();

        if (!empty($this->appliedRevisions)) {
            krsort($this->appliedRevisions, SORT_NUMERIC);
            $this->latestRevision = $this->maxRevision = current($this->appliedRevisions)->getVersionId();
        }

        return $this;
    }

    private function _buildInsert($rows) {
        $q = "INSERT INTO `{$this->schemaVersionTable}` (version_id, file, checksum, title, created_on) VALUES ";

        $values = array();
        foreach ($rows as $row) {
            $rowEscd = [];
            foreach ($row as $value) {
                $rowEscd[] = sprintf("'%s'", addslashes($value));
            }
            $values[] = '(' . join(', ', $rowEscd) . ')';
        }

        return $q . join(', ', $values);
    }

    protected function loadNewRevisions() {
        $files = glob($this->revisionsDir . DIRECTORY_SEPARATOR . '*.sql');
        foreach ($files as $aFile) {
            $aRevision = DBMigrationRevision::fromFile($aFile);
            if ($aRevision) {
                $version = $aRevision->getVersionId();
                //check if revision exists 
                if (array_key_exists($version, $this->appliedRevisions)) {
                    //checks...
                    $storedRevision = $this->appliedRevisions[$version];
                    if (!$storedRevision->equals($aRevision)) {
                        throw new DBMigrationException("Applied migration is no longer valid, applied migration at Version {$version} = {$storedRevision} ; Revision File = {$aRevision}");
                    }
                } else {
                    if ($aRevision->getVersionId() > $this->maxRevision) {
                        //check if version has been loaded, throw exception on duplicate
                        if (array_key_exists($version, $this->newRevisions)) {
                            $existing = $this->newRevisions[$version];
                            throw new DBMigrationException("Duplicate found at version: {$version}, between {$existing} and {$aRevision}");
                        }

                        $this->newRevisions[$version] = $aRevision;
                    }
                }
            }
        }

        if (!empty($this->newRevisions)) {
            ksort($this->newRevisions, SORT_NUMERIC);
            $firstNewVersion = current($this->newRevisions)->getVersionId();
            if (($firstNewVersion - $this->maxRevision) > 1) {
                self::el('WARNING!!!', 'Versions might be missing', $this->maxRevision, ' Jumps to ', $firstNewVersion);
            }
            $this->latestRevision = end($this->newRevisions)->getVersionId();
        }
    }

    /**
     * @return DBV_Adapter_Interface
     * @throws DBMigrationException
     */
    protected function _getAdapter() {
        if (!$this->_adapter) {
            $file = __DIR__ . DS . 'adapters' . DS . strtolower($this->dbConfig['adapter']) . '.php';
            if (file_exists($file)) {
                require_once $file;

                $class = __NAMESPACE__ . '\adapters\DBV_Adapter_' . $this->dbConfig['adapter'];
                if (class_exists($class)) {
                    $adapter = new $class;
                    /* @var $adapter DBV_Adapter_Interface */
                    $adapter->connect($this->dbConfig['host'], $this->dbConfig['port'], $this->dbConfig['username'], $this->dbConfig['password'], $this->dbConfig['database']);
                    $this->_adapter = $adapter;
                } else {
                    throw new DBMigrationException("Adapter class {$class} was not found");
                }
            } else {
                throw new DBMigrationException("Adapter file {$file} was not found");
            }
        }

        return $this->_adapter;
    }

    public function getContent(DBMigrationRevision $revision) {
        $file = $this->revisionsDir . DS . $revision->getFile();
        if (!file_exists($file) || !is_readable($file) || ($content = file_get_contents($file)) === FALSE) {
            throw new DBMigrationException("Revision file [{$file}] for version {$revision->getVersionId()} could not be read!");
        }

        return $content;
    }

    /**
     * Echoes to screen an outpu value.
     * 
     */
    public static function el() {
        $op = [];
        $op[] = date('[Y-m-d H:i:s]');
        $op[] = "\t";
        foreach (func_get_args() as $arg) {
            if (is_scalar($arg)) {
                $op[] = $arg;
            } else if (is_object($arg) && is_a($arg, '\Exception')) {
                /* @var $arg Exception */
                $op[] = 'Exception: ' . get_class($arg) . ":\n\t";
                $op[] = $arg->getMessage() . "\n\t";
                $op[] = $arg->getTraceAsString();
            } else {
                $op[] = print_r($arg, true);
            }
        }

        echo join(" ", $op), PHP_EOL;
    }

    public static function readInput($str, $default = "") {
        echo $str . " ";
        $fh = fopen("php://stdin", "r");
        $response = trim(fgets($fh));
        fclose($fh);
        return strlen($response) ? $response : $default;
    }

}
