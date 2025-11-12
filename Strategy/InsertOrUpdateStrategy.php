<?php

namespace LePhare\Import\Strategy;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use LePhare\Import\Exception\ImportException;
use LePhare\Import\ImportResource;

class InsertOrUpdateStrategy implements StrategyInterface
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'insert_or_update';
    }

    public function copy(ImportResource $resource): int
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof MySQLPlatform) {
            $rowCount = $this->mysqlCopy($resource);
        } elseif ($platform instanceof PostgreSQLPlatform) {
            $rowCount = $this->postgresqlCopy($resource);
        } else {
            throw new ImportException('insert_or_update strategy is not implemented for '.get_class($platform));
        }

        return $rowCount;
    }

    private function mysqlCopy(ImportResource $resource): int
    {
        $tablename = $this->connection->quoteIdentifier($resource->getTargetTablename());
        $tempTablename = $this->connection->quoteIdentifier($resource->getTablename());

        $columns = [];
        $tempColumns = [];
        $updateClause = [];

        foreach ($resource->getMapping() as $name => $properties) {
            foreach ($properties['property'] as $property) {
                if ($resource->isUpdateableField($property)) {
                    $fieldUpdateclause = $properties['update_sql'] ?
                        $this->connection->quoteIdentifier($property).' = '.$properties['update_sql'] :
                        $this->connection->quoteIdentifier($property).' = VALUES('.$property.')';

                    $updateClause[] = $fieldUpdateclause;
                }
                $columns[] = $this->connection->quoteIdentifier($property);

                $tempColumns[] = $properties['sql'] ?: $this->connection->quoteIdentifier($name);
            }
        }

        $columns = implode(',', $columns);
        $tempColumns = implode(',', $tempColumns);
        $updateClause = implode(',', $updateClause);

        $whereClause = $resource->getCopyCondition() ? 'WHERE '.$resource->getCopyCondition() : '';
        $joins = $resource->getJoins();
        $distinct = $resource->isDistinct() ? 'DISTINCT' : '';

        $sql = "INSERT INTO {$tablename} ({$columns})
                SELECT {$distinct} {$tempColumns} FROM {$tempTablename} temp {$joins} {$whereClause}
                ON DUPLICATE KEY UPDATE {$updateClause}";

        if (!$resource->isSharedConnection()) {
            $this->connection->beginTransaction();
        }
        $this->connection->executeQuery($sql);
        if (!$resource->isSharedConnection()) {
            $this->connection->commit();
        }

        // Because of bad results of PDOStatement::rowCount() for MySQL (at least)
        // we determine rowCount from select clause that has been inserted
        $rowCountSql = "SELECT COUNT(*) FROM {$tempTablename} temp {$whereClause}";
        $rowCountStmt = $this->connection->executeQuery($rowCountSql);

        return (int) $rowCountStmt->fetchOne();
    }

    private function postgresqlCopy(ImportResource $resource): int
    {
        $tablename = $this->connection->quoteIdentifier($resource->getTargetTablename());
        $tempTablename = $this->connection->quoteIdentifier($resource->getTablename());

        $columns = [];
        $tempColumns = [];
        $updateClause = [];

        foreach ($resource->getMapping() as $name => $properties) {
            foreach ($properties['property'] as $property) {
                if ($resource->isUpdateableField($property)) {
                    $fieldUpdateclause = $properties['update_sql'] ?
                        $this->connection->quoteIdentifier($property).' = '.$properties['update_sql'] :
                        $this->connection->quoteIdentifier($property).' = excluded.'.$this->connection->quoteIdentifier($property);

                    $updateClause[] = $fieldUpdateclause;
                }
                $columns[] = $this->connection->quoteIdentifier($property);

                $tempColumns[] = $properties['sql'] ? $properties['sql'].' as '.$name : $this->connection->quoteIdentifier($name);
            }
        }

        $columns = implode(',', $columns);
        $tempColumns = implode(',', $tempColumns);
        $updateClause = implode(',', $updateClause);

        $whereClause = $resource->getCopyCondition() ? 'WHERE '.$resource->getCopyCondition() : '';
        $joins = $resource->getJoins();
        $conflictTargetClause = $resource->getConflictTarget() ?: '';
        $distinct = $resource->isDistinct() ? 'DISTINCT' : '';

        $sql = "INSERT INTO {$tablename} ({$columns})
                SELECT {$distinct} {$tempColumns} FROM {$tempTablename} temp {$joins} {$whereClause}
                ON CONFLICT {$conflictTargetClause} DO UPDATE SET {$updateClause}";

        if (!$resource->isSharedConnection()) {
            $this->connection->beginTransaction();
        }

        try {
            $rowCount = $this->connection->executeStatement($sql);
            if (!$resource->isSharedConnection()) {
                $this->connection->commit();
            }
        } catch (Exception $exception) {
            if (!$resource->isSharedConnection()) {
                $this->connection->rollback();
            }

            throw $exception;
        }

        return $rowCount;
    }
}
