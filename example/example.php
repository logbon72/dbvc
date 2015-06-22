<?php

//load dbvc classes
require_once __DIR__ . '/../dbvc.php';

use intelworx\dbvc\DBMigration;
use intelworx\dbvc\DBMigrationMode;

$dbConfig = array(
    'adapter' => 'MySQL', //this is the only adapter implemented for now
    'host' => 'localhost',
    'port' => 3306,
    'username' => 'root',
    'password' => '',
    'database' => 'test',
);


//for this example the first argument is the mode to run, it defaults to interactive
$mode = isset($argv[1]) ? $argv[1] : DBMigrationMode::INTERACTIVE;

//for this example the second argument is the version to start from
$startVersion = isset($argv[2]) && is_numeric($argv[2]) ? intval($argv[2]) : 0;


if (isset($_SERVER['REQUEST_URI'])) {
    //over the web
    echo '<pre>';
    if (!$mode) {
        $mode = DBMigrationMode::NON_INTERACTIVE;
    }
}

DBMigration::el('Starting migration');

try {

    //the directory with revisions.
    $revisionsDirectory = __DIR__ . '/revisions';

    //migration object must be initiated with this directory
    $migration = new DBMigration($revisionsDirectory);

    //set DB config
    $migration->setDbConfig($dbConfig);

    DBMigration::el('Start version : ', $startVersion);
    $migration->runMigration($mode, $startVersion, true);
    $status = 0;
} catch (Exception $e) {
    DBMigration::el('Cannot continue, an exception occured', $e);
    $status = -1;
}

//
if (isset($_SERVER['REQUEST_URI'])) {
    echo '</pre>';
}

//for times, you wish to return a status.
return $status;
