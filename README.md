# DBVC: Database Version Control #

DBVC was inspired by http://dbv.vizuina.com/, it uses some of the core classes used by DBV. However, unlike DBV, DBVC 

- was designed to work on CLI 
- was designed for revisions to be applied in a forward mode only, but can also work out-of-order.
- was designed to work similar to Flyway DB migration (http://flywaydb.org)

DBMigration can be used in 3 modes, all defined in DBMigrationMode class

1. Dry-run, which specifies the script should only display the list of queries that will be executed.
1. Interactive mode, which will list all changes to be applied and ask you to confirm before applying changes. This is the mode used in migration.php
1. Non-interactive mode, which runs the DB migration without any human intervention.

# Using DBMigration #
The migration script can be run using the following command: 

```php
#!php

require_once /path/to/dbvc/dbvc.php;

use intelworx\dbvc\DBMigration;
use intelworx\dbvc\DBMigrationMode;

$dbConfig = array(
	//this is the only adapter implemented for now
    'adapter' => 'MySQL', 
	//the database host
    'host' => 'localhost',
	//database port
    'port' => 3306,
	//database username
    'username' => 'root',
	//database password
    'password' => '',
	//database on which to perform migration
    'database' => 'test',
);

$migration = new DBMigration('/path/to/revisions_dir', $dbConfig);

//mode to run migration
$mode = DBMigrationMode::INTERACTIVE;

//version to start from, useful, when you wish to jump versions
$startVersion = 0;

$outOfOrder = true;

//run migration, throws DBMigrationException on error.
$migration->runMigration($mode, $startVersion, $outOfOrder);

```

# Definition of Terms #

**Revision File:** An SQL file which contains changes to make to a DB, it can contain as many queries as possible. Please see next section on how to name revision files.

**Revisions Directory:** This is a folder where all revisions are stored.

**Migration Table:** This is a database table which is used as a store for DBVC, it's name is usually ```dbvc__schema_version```, it is used to track revision files that have been executed on the schema.

**Schema:** Another name for DB

**Bad Revisions File:** This is a file containing version numbers of revisions you wish to skip in the database. This file should have only one version number per line and should be saved in ```/path/to/revisions_dir/bad_revisions.txt```

# Guidelines to Specifying Schema Versions #
This section specifies guidelines for schema revisions, please read carefully.

## Naming Revision Files ##
Revision files should be named in the format {version_number}_version_description.sql and should be saved in the revisions folder. The version_number should be serial and unique.

**Example:** if you change the users in database table to add a new column, say email2 and the last file was say 10_a_chnge.sql, the migration file should be saved as ```/path/to/revisions_dir/11_added_email2_to_users.sql.```

## Applying Changes to DB ##

To prevent errors, it is advisable that you don't use any MySQL client to apply the changes to the DB, only this script should be used to alter structure of DB objects. You should use the client to generate the change query, put the query in the revision file, then run migration script, that way, you are sure that the migration file for tracking applied migrations has been successfully updated.

## Version Conflicts ##
Before creating version files, it is advisable to first pull from the upstream so as to have the latest DB changes, this will save you the stress of having to deal with duplicate version ID. In the event of duplicate version ID, the version that already exists in the repository wins, the puller should revert changes made by his revision file, and also delete the changes from the migration store table. 

So if in the previous example, you pull and discover there's already another file called  /dbvc/revisions/default/11_another_change.sql, then you should do the following:
If you've not run the migration script, rename your migration file from 12_added_email2_to_users.sql, that is also assuming there was no version 11 when you pulled. Then run the migration script. 

It is advisable that you push your migration scripts immediately you've applied them. You can push only the revision files to the upstream if you're sure that the changes made will not break existing code. If your revisions will break existing code, then you should push your entire repo when ready, note that, the longer you wait, the more likely you are to experience version conflicts.

## Out-of-Order Migration##
When this option is set to true, all revisions that have not yet been applied, will be applied, this changes the way DBVC works normally and has the advantage that teams which use feature branches can easily merge their work together and apply all changes, using the out-of-order mechanism. In such cases, it is advisable that each branch has its own revision range, e.g. 0 - 1000 for master branch, 1001 - 1100 for feature x, and so on.

## Rolling Back Changes ##
MySQL and some other DBMS do not support roll-back of DDL queries, hence changes made to DB structure cannot be reverted automatically. If you made a change in a revision and you discover that this is not a change you want, then you should create another revision file to revert that change.

# License #

*Free for all*