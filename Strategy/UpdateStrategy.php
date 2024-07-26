<?php

namespace LePhare\Import\Strategy;

use Doctrine\DBAL\Connection;
use LePhare\Import\Exception\ImportException;
use LePhare\Import\ImportResource;

class UpdateStrategy implements StrategyInterface
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'update';
    }

    public function copy(ImportResource $resource): int
    {
        $tablename = $this->connection->quoteIdentifier($resource->getTargetTablename());
        $tempTablename = $this->connection->quoteIdentifier($resource->getTablename());

        $tempIdentifier = $resource->getLoadIdentifier();
        $destinationIdentifier = $resource->getCopyIdentifier();

        if (null === $tempIdentifier || null === $destinationIdentifier) {
            throw new ImportException(sprintf('Options update_load_indentifier and update_copy_indentifier are mandatory for %s strategy', $this->getName()));
        }

        $setters = [];
        foreach ($resource->getMapping() as $name => $properties) {
            foreach ($properties['property'] as $property) {
                $column = $this->connection->quoteIdentifier($property);
                $tempColumn = $properties['sql'] ?: $this->connection->quoteIdentifier($name);

                if ($column && $tempColumn) {
                    $setters[] = "
                    $column = (SELECT $tempColumn FROM $tempTablename
                    WHERE $tempTablename.$tempIdentifier = $tablename.$destinationIdentifier)";
                }
            }
        }

        $setters = implode(',', $setters);

        $whereClause = 'WHERE '.$destinationIdentifier.' IN (SELECT '.$tempIdentifier.' FROM '.$tempTablename.')';
        if ($resource->getCopyCondition()) {
            $whereClause .= ' AND '.$resource->getCopyCondition();
        }

        $sql = "UPDATE $tablename
                SET $setters
                $whereClause";

        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->executeQuery($sql);
            $lines = $stmt->rowCount();
            $this->connection->commit();
        } catch (\Exception $exception) {
            $this->connection->rollback();
            $lines = 0;
        }

        return $lines;
    }
}
