<?php

namespace LePhare\Import\Strategy;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use LePhare\Import\Exception\ImportException;
use LePhare\Import\ImportResource;

class InsertStrategy implements StrategyInterface
{
    protected Connection $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getName(): string
    {
        return 'insert';
    }

    public function copy(ImportResource $resource): int
    {
        $tablename = $this->connection->quoteIdentifier($resource->getTargetTablename());
        $tempTablename = $this->connection->quoteIdentifier($resource->getTablename());

        $columns = [];
        $tempColumns = [];
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
            'WHERE '.$resource->getCopyCondition() : '';

        $joins = $resource->getJoins();

        $sql = "INSERT INTO {$tablename} ({$columns})
                SELECT {$tempColumns} FROM {$tempTablename} temp {$joins} {$whereClause}";

        if (!$resource->isSharedConnection()) {
            $this->connection->beginTransaction();
        }

        try {
            $stmt = $this->connection->executeQuery($sql);
            $lines = $stmt->rowCount();
            if (!$resource->isSharedConnection()) {
                $this->connection->commit();
            }
        } catch (UniqueConstraintViolationException $exception) {
            if (!$resource->isSharedConnection()) {
                $this->connection->rollback();
            }
            $lines = 0;
        } catch (NotNullConstraintViolationException $exception) {
            if (!$resource->isSharedConnection()) {
                $this->connection->rollback();
            }
            $lines = 0;

            if (preg_match('/SQLSTATE[^"]*"([^"]*)"/xms', $exception->getMessage(), $matches)) {
                throw new ImportException("Insert failed: not-null values found in \"{$matches[1]}\" column");
            }
        } catch (Exception $exception) {
            if (!$resource->isSharedConnection()) {
                $this->connection->rollback();
            }

            throw $exception;
        }

        return $lines;
    }
}
