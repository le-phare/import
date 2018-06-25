<?php

namespace LePhare\Import\Strategy;

use Doctrine\DBAL\DBALException;
use LePhare\Import\Exception\ImportException;

class InsertOrUpdateStrategy implements StrategyInterface
{
    protected $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function getName()
    {
        return 'insert_or_update';
    }

    public function copy($resource)
    {
        $platformName = $this->connection->getDatabasePlatform()->getName();

        if ('mysql' === $platformName) {
            $rowCount = $this->mysqlCopy($resource);
        } elseif ('postgresql' === $platformName) {
            $rowCount = $this->postgresqlCopy($resource);
        } else {
            throw new ImportException('insert_or_update strategy is not implemented for '.$platformName);
        }

        return $rowCount;
    }

    private function mysqlCopy($resource)
    {
        $tablename = $this->connection->quoteIdentifier($resource->getTargetTablename());
        $tempTablename = $this->connection->quoteIdentifier($resource->getTablename());

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

        $sql = "INSERT INTO $tablename ($columns)
                SELECT $distinct $tempColumns FROM $tempTablename temp $joins $whereClause
                ON DUPLICATE KEY UPDATE $updateClause"
        ;

        $this->connection->beginTransaction();
        $this->connection->executeQuery($sql);
        $this->connection->commit();

        // Because of bad results of PDOStatement::rowCount() for MySQL (at least)
        // we determine rowCount from select clause that has been inserted
        $rowCountSql = "SELECT COUNT(*) FROM $tempTablename temp $whereClause";
        $rowCountStmt = $this->connection->query($rowCountSql);
        $rowCount = (int) $rowCountStmt->fetchColumn();

        return $rowCount;
    }

    private function postgresqlCopy($resource)
    {
        $tablename = $this->connection->quoteIdentifier($resource->getTargetTablename());
        $tempTablename = $this->connection->quoteIdentifier($resource->getTablename());

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

        $sql = "INSERT INTO $tablename ($columns)
                SELECT $distinct $tempColumns FROM $tempTablename temp $joins $whereClause
                ON CONFLICT ($conflictTargetClause) DO UPDATE SET $updateClause"
        ;

        $this->connection->beginTransaction();

        try {
            $rowCount = $this->connection->executeUpdate($sql);
            $this->connection->commit();
        } catch (DBALException $exception) {
            $this->connection->rollback();

            throw $exception;
        }

        return $rowCount;
    }
}
