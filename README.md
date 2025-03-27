# Import

## Resources

-   [Report issues](https://github.com/le-phare/import/issues)

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
