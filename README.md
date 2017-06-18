# Import library

This library provide a efficient way to import CSV/Excel files in a database (MySQL and PostgreSQL)

## Installation

    composer require lephare/import "dev-master"

## Usage

Exemple in a Symfony command.

```
<?php

namespace SiteBundle\Command;

use LePhare\Import\Import;
use LePhare\Import\ImportConfiguration;
use LePhare\Import\Strategy\InsertOrUpdateStrategy;
use LePhare\Import\Strategy\StrategyRepository;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this->setName('app:import');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $conn = $this->getContainer()->get('database_connection');
        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $logger = $this->getContainer()->get('logger');

        $strategyRepository = new StrategyRepository;
        $strategyRepository->addStrategy(new InsertOrUpdateStrategy($conn));

        $configuration = new ImportConfiguration();

        $import = new Import($conn, $eventDispatcher, $strategyRepository, $configuration, $logger);
        $import->init($this->getContainer()->getParameter('kernel.root_dir').'/config/import.yml');
        $success = $import->execute();

        return $success ? 0 : 1;
    }
}
```

## Configuration

An import is described by a YAML file.

    identifier: import
    name: Import
    source_dir: "%kernel.root_dir%/../var/import"
    resources:
        ...

 * `identifier` (required) The import identifier
 * `source_dir` (required) Directory where to find import data files
 * `name` (optional) A human friendly name for the import
 * `log_dir` (optional) Log files directory (default: %kernel.root_dir%/logs/import)
 * `resources` (required) One or more import resources

### Resources

A resource can load and/or copy data.

    user:
        tablename: __tmp_user
        load:
            ...
        copy:
            ...

 * `tablename` (required) resource import tablename
 * `load` Describe how to load data in the import tablename
 * `copy` Describe how to copy data from the import tablename into business tablename

#### Load data

The loading process allow to load one or more data file in import tables. These tables are called temporary as the are created at the beginning of the import process.

    load:
        pattern: ".*activites.csv?$"
        strategy: first_by_name
        loop: false
        format: csv
        format: text
        format_options:
            validate_headers: true
            sheet_index: 0
            field_delimiter: ";"
            line_delimiter: "\n"
            quote_character: "\""
        fields:
            created_at:
                type: datetime
                options:
                    default: now()
        extra_fields:
            ...

* `pattern` A regex that match filenames to load for this resource
* `strategy` [first_by_name|last_by_name] (optional) Sort matching filenames by their name (default: `first_by_name`)
* `loop [true|false]` (optional) Set to true to load all matching files, false to load only the first (default: `false`)
* `format [csv|xls|text]` File format.
   `text` is supported only by PostgreSQL. XLS support is provided only if the package phpoffice/phpexcel is installed (default: `csv`)

* `format_options` (optional)
    * `validate_headers (csv only)` Enable the header validation. See "Headers validations" chapter. (default: `true`)
    * `with_header (csv only)` Enable the header bypass. Make the load process ignore the first line. (default: `true`)
    * `sheet_index (xls only)`  The spreadsheet index to load (default: `0`)
    * `field_delimiter (csv only)` The CSV field delimiter (default: `;`)
    * `line_delimiter (csv only)` The CSV line delimiter (default: `\n`)
    * `quote_character (csv only)` The CSV quote character (default: `"`)
* `fields` Array of fields in the CSV. The keys should match the file columns name.
* `extra_fields` (optional) Array of additionals fields to create in the import table
* `indexes` (optional) Array of indexes to create in the import table.

`fields` and `extra_fields` elements accept these options:
* `type` (optional) A valid DBAL type (default: `string`)
* `options` (optional) An array of DBAL options (default: `{notnull: false}`)

You can pass a string instead of an array for a shorter syntax.

    fields:
        Code client: ~

Is equivalent to :

    fields:
        Code client:
            type: string
            options:
                notnull: false

#### Copy data

The copy process allow to copy data from import tables to application tables.

    copy:
        target: _user
        strategy: insert_or_update
        strategy_options:
            copy_condition: email_commercial IS NOT NULL
            distinct: true
            joins: INNER JOIN table ON (table.email = temp.email_commercial)
            conflict_target: email
            non_updateable_fields: ['email', 'username']
        mapping:
            email_commercial:
                property: [email, username]
            nom_commercial: lastname
            prenom_commercial: firstname
            created_at:
                property: [created_at, updated_at]
            created_by:
                property: [created_by, updated_by]
            status:
                property: status
                sql: "'enabled'"
            type:
                property: type
                sql: "'commercial'"
            salt:
                property: salt
                sql: "'salt_bidon'"
            password_token:
                property: password_token
                sql: "'token_bidon'"

* `target` The application table name
* `strategy [insert|insert_ignore|insert_or_update]` (optional) The copy strategy. See "Copy Strategies" chapter. Default to `insert_or_update`
* `strategy_options` (optional)
    * `copy_condition`  (optional) Add a clause to the INSERT INTO ... SELECT ... [copy_condition] query
    * `distinct`  (optional) Add a distinct clause in the select query
    * `joins`  (optional) Add joins to the select query
    * `conflict_target (postgresql only)` The unique index columns to use  when using the insert_or_update or insert_ignore strategy
    * `non_updateable_fields` (optional) Array of fields not to update
    * `mapping` Array used to map import columns to application columns
    * `property` (optional) An array of application columns where to copy the import column
    * `sql` (optional) The raw SQL declaraction for application columns
    * `update_sql` (optional) The raw SQL declaraction for the update part

## Execution flow

### Initialize

Called by `Import::init()`
The init process load and validation the provided configuration file.

### Execute

`Import::execute()`
The execute process first load then copy the resources.

### load

Loop on each loadable resources and load corresponding files in temporaries tables.

### Copy

Loop on each copyable resources and copy from temporaries tables to targets tables.

## Events

You can hook in the import process by listening to specific events.  All events takes an instance of `ImportEvent` that give you the import configuration and a logger (PSR-3).

### POST_INIT

Hook after the configuration file validation. You can modify the configuration in the event.

### PRE_EXECUTE

Hook before all execution. This event can be used to provide extended functionnality to the bundle. The import report system use this event (see 'Import report').

### PRE_LOAD

This event is dispatched before the loading of all resources but after the temporary table has been created.

### VALIDATE_SOURCE

Dispatched on each import files to validate them. You can add your custom file based validations rules here.

### POST_LOAD

Dispatched after all resources has been loaded. This event is often used to clean import data.

### PRE_COPY

This event is dispatched before the copy of all resources. This event is often use to validate import data. You could check for duplicates or validate business rules here.

### COPY

This event is dispatched after the copy of one resource. This event is often use to update temporary table with the id of newly created rows.

### POST_COPY

This event is dispatched after the copy of all resources. This event is often use to operate on target tables and do some extra stuff (geocoding, translations, ...). You could also use this event to provide report information.

### POST_EXECUTE

Hook after all execution.  This event can be used to provide extended functionnality to the bundle. The import report system use this event (see 'Import report').

### EXCEPTION

This event is dispatched when an exception is thrown somewhere in the import process.

## Features

### Copy Strategies

The bundle provides three copy strategies.

#### INSERT

This strategy is available for both MySQL and PostgreSQL (9.4+) . It is the most naive strategy. It execute a simple  `INSERT INTO ... SELECT ...` statment and do not care about not null  or unique constraints.

#### INSERT_IGNORE

This strategy is available for both MySQL and PostgreSQL (9.5+).  It will ignore rows that fails an unique constraint.

#### INSERT_OR_UPDATE

This strategy is available for both MySQL and PostgreSQL (9.5+).  Whenever a conflict is detected, it will try to make an UPDATE statement.  It rely on INSERT ... ON DUPLICATE KEY in MySQL and on INSERT ... ON CONLICT DO UPDATE in postgreSQL.

### Headers validation

The headers validation is only supported in CSV file format. It will validate that all fields you have in the yml exists in the same order in the CSV file no more no less.

### Archives

By default the import bundle will archive all files that has been loaded in <source_di>/archives/<date> directory and keep 30 folders.

### Quarantines

The quarantine will put not loaded files that match loadables resources pattern in a specific directory.

### Excel support

The Excel support need the phpoffice/phpexcel composer package. You just need to install it and it works.
