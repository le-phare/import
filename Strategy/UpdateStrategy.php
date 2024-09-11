<?php

namespace LePhare\Import\Strategy;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
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
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof MySQLPlatform) {
            $rowCount = $this->mysqlCopy($resource);
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $rowCount = $this->postgresqlCopy($resource);
        } else {
            throw new ImportException('update strategy is not implemented for '.get_class($platform));
        }

        return $rowCount;
    }

    public function mysqlCopy(ImportResource $resource): int
    {
        $tablename = $this->connection->quoteIdentifier($resource->getTargetTablename());
        $tempTablename = $this->connection->quoteIdentifier($resource->getTablename());

        $tempIdentifier = $resource->getLoadIdentifier();
        $destinationIdentifier = $resource->getCopyIdentifier();

        if (null === $tempIdentifier || null === $destinationIdentifier) {
            throw new ImportException(sprintf('Options update_load_identifier and update_copy_identifier are mandatory for %s strategy', $this->getName()));
        }

        $setters = [];
        foreach ($resource->getMapping() as $name => $properties) {
            foreach ($properties['property'] as $property) {
                $column = $this->connection->quoteIdentifier($property);
                $tempColumn = $properties['sql'] ?: $this->connection->quoteIdentifier($name);

                if ($column && $tempColumn) {
                    if ('NULL' !== strtoupper($tempColumn)) {
                        $setters[] = "
                            temp.$column = (SELECT $tempColumn FROM $tempTablename
                            WHERE $tempTablename.$tempIdentifier = temp.$destinationIdentifier)";
                    } else {
                        $setters[] = "
                            temp.$column = NULL";
                    }
                }
            }
        }

        $setters = implode(',', $setters);

        $joins = $resource->getJoins();
        $whereClause = 'WHERE temp.'.$destinationIdentifier.' IN (SELECT '.$tempIdentifier.' FROM '.$tempTablename.')';
        if ($resource->getCopyCondition()) {
            $whereClause .= ' AND '.$resource->getCopyCondition();
        }

        $sql = "UPDATE $tablename temp
                $joins
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

    public function postgresqlCopy(ImportResource $resource): int
    {
        $tablename = $this->connection->quoteIdentifier($resource->getTargetTablename());
        $tempTablename = $this->connection->quoteIdentifier($resource->getTablename());

        $tempIdentifier = $resource->getLoadIdentifier();
        $destinationIdentifier = $resource->getCopyIdentifier();

        if (null === $tempIdentifier || null === $destinationIdentifier) {
            throw new ImportException(sprintf('Options update_load_identifier and update_copy_identifier are mandatory for %s strategy', $this->getName()));
        }

        $setters = [];
        foreach ($resource->getMapping() as $name => $properties) {
            foreach ($properties['property'] as $property) {
                $column = $this->connection->quoteIdentifier($property);
                $tempColumn = $properties['sql'] ?: $this->connection->quoteIdentifier($name);

                if ($column && $tempColumn) {
                    if ('NULL' !== strtoupper($tempColumn)) {
                        $setters[] = "
                            $column = (SELECT $tempColumn FROM $tempTablename
                            WHERE $tempTablename.$tempIdentifier = $tablename.$destinationIdentifier)";
                    } else {
                        $setters[] = "
                            $column = NULL";
                    }
                }
            }
        }

        $setters = implode(',', $setters);

        $joins = $resource->getJoins();
        $whereClause = 'WHERE '.$tablename.'.'.$destinationIdentifier.' IN (SELECT '.$tempIdentifier.' FROM '.$tempTablename.')';
        if ($resource->getCopyCondition()) {
            $whereClause .= ' AND '.$resource->getCopyCondition();
        }

        $sql = "UPDATE $tablename
                SET $setters
                FROM $tempTablename temp $joins
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
