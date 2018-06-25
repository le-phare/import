<?php

namespace LePhare\Import\Strategy;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use LePhare\Import\Exception\ImportException;

class InsertIgnoreStrategy implements StrategyInterface
{
    protected $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function getName()
    {
        return 'insert_ignore';
    }

    public function copy($resource)
    {
        $platformName = $this->connection->getDatabasePlatform()->getName();

        if ('mysql' === $platformName) {
            $rowCount = $this->mysqlCopy($resource);
        } elseif ('postgresql' === $platformName) {
            $rowCount = $this->postgresqlCopy($resource);
        } else {
            throw new ImportException('insert_ignore strategy is not implemented for '.$platformName);
        }

        return $rowCount;
    }

    public function mysqlCopy($resource)
    {
        $tablename = $this->connection->quoteIdentifier($resource->getTargetTablename());
        $tempTablename = $this->connection->quoteIdentifier($resource->getTablename());

        $updateClause = [];

        foreach ($resource->getMapping() as $name => $properties) {
            foreach ($properties['property'] as $property) {
                if ($resource->isUpdateableField($property)) {
                    $updateClause[] = $this->connection->quoteIdentifier($property).' = temp.'.$this->connection->quoteIdentifier($name);
                }
                $columns[] = $this->connection->quoteIdentifier($property);

                $tempColumns[] = $properties['sql'] ?: $this->connection->quoteIdentifier($name);
            }
        }

        $columns = implode(',', $columns);
        $tempColumns = implode(',', $tempColumns);
        $updateClause = implode(',', $updateClause);

        $whereClause = null !== $resource->getCopyCondition() ?
            'WHERE '.$resource->getCopyCondition() : ''
        ;

        $joins = $resource->getJoins();

        $sql = "INSERT IGNORE INTO $tablename ($columns)
                SELECT $tempColumns FROM $tempTablename temp $joins $whereClause";

        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->executeQuery($sql);
            $lines = $stmt->rowCount();
            $this->connection->commit();
        } catch (NotNullConstraintViolationException $exception) {
            $this->connection->rollback();
            $lines = 0;

            if (preg_match('/SQLSTATE[^"]*"([^"]*)"/xms', $exception->getMessage(), $matches)) {
                throw new ImportException("Insert failed: not-null values found in \"${matches[1]}\" column");
            }
        }

        return $lines;
    }

    private function postgresqlCopy($resource)
    {
        $tablename = $this->connection->quoteIdentifier($resource->getTargetTablename());
        $tempTablename = $this->connection->quoteIdentifier($resource->getTablename());

        $updateClause = [];

        foreach ($resource->getMapping() as $name => $properties) {
            foreach ($properties['property'] as $property) {
                if ($resource->isUpdateableField($property)) {
                    $fieldUpdateclause = $this->connection->quoteIdentifier($property).' = excluded.'.$this->connection->quoteIdentifier($property);

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
        $conflictTargetClause = $resource->getConflictTarget() ?: '';

        $sql = "INSERT INTO $tablename ($columns)
                SELECT $tempColumns FROM $tempTablename temp $joins $whereClause
                ON CONFLICT ($conflictTargetClause) DO NOTHING"
        ;

        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->executeQuery($sql);
            $rowCount = $stmt->rowCount();
            $this->connection->commit();
        } catch (DBALException $exception) {
            $this->connection->rollback();

            throw $exception;
        }

        return $rowCount;
    }
}
