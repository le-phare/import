# Import

## Resources

-   [Report issues](https://github.com/le-phare/import/issues)

## Shared Connection Mode

When using the `Import` class with an existing database connection that has an active transaction, you can enable **shared connection mode** to prevent the `Import` class from managing transactions itself.

This is useful when:
- You want to run the import within an existing transaction
- You need to perform operations before/after the import in the same transaction
- You want full control over transaction boundaries (commit/rollback)

### Usage

Pass `true` as the last parameter when constructing the `Import` instance:

```php
use LePhare\Import\Import;

// Start your transaction
$connection->beginTransaction();

try {
    // Perform pre-import operations
    $connection->executeStatement('TRUNCATE TABLE my_table');
    
    // Create Import with shared connection mode enabled
    $import = new Import(
        $connection,
        $eventDispatcher,
        $strategyRepository,
        $loadStrategyRepository,
        $configuration,
        $logger,
        true // Enable shared connection mode
    );
    
    $import->init($config);
    $import->execute();
    
    // Commit the transaction
    $connection->commit();
} catch (\Exception $e) {
    // Rollback on error
    $connection->rollBack();
    throw $e;
}
```

### Behavior

**Normal mode (default):**
- The `Import` class manages transactions automatically
- Each copy operation is wrapped in `beginTransaction()` / `commit()` / `rollBack()`

**Shared connection mode:**
- The `Import` class **does not** call `beginTransaction()`, `commit()`, or `rollBack()`
- The caller is responsible for transaction management
- All import operations execute within the caller's transaction

### Benefits

✅ **Clear transaction boundaries**: Caller controls the transaction lifecycle  
✅ **Atomic operations**: TRUNCATE + COPY in same transaction  
✅ **Predictable rollback**: Caller can rollback all operations if needed  
✅ **Backwards compatible**: Default behavior unchanged

## Archive

Archive affects imported files/resources when a `ImportEvents::POST_COPY` event is triggered.

For archive to take effect on a imported resource, you need to explicitly define:

-   the `archive.enabled` value to `true`
-   the `resources.references.load` node

```yaml
name: stock
source_dir: "var/exchange/in"

archive:
    enabled: true
    dir: "var/exchange/in/foo/stock"
    rotation: 60

resources:
    references:
        tablename: import.stock
        load:
            pattern: "^stock.csv$"
```

The file will move to a default `archives` directory in the defined `source_dir` or in the `archive.dir` if you explicitly define its value.

The `archive.rotation` define define the number of files to keep before deletion.

## Quarantine

Quarantine affects imported files/resources when a `ImportEvents::EXCEPTION` event is triggered (before)

For quarantine to take effect on a imported resource, you need to explicitly define:

-   the `quarantine.enabled` value to `true`
-   the `resources.references.load` node

The subsequent `stock.csv` file will be quarantined if an import exception happen during the import process.

```yaml
name: stock
source_dir: "var/exchange/in"

quarantine:
    enabled: true
    dir: "var/exchange/in/bar/stock"
    rotation: 60

resources:
    references:
        tablename: import.stock
        load:
            pattern: "^stock.csv$"
```

The file will move to a default `quarantine` directory in the defined `source_dir` or in the `quarantine.dir` value if you explicitly define its value.

The `quarantine.rotation` define define the number of files to keep before deletion.

## IDE Integration

Beyond validating YAML syntax in your IDE, you can validate the definition of an import configuration using 
the JSON schema `./lephare-import.schema.json`.

This also provides contextual help for autocompletion and when hovering over a YAML key.
