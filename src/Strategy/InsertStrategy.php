<?php

namespace LePhare\Import\Strategy;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use LePhare\Import\Exception\ImportException;

class InsertStrategy implements StrategyInterface
{
    protected $connection;

    public function __construct($connection)
    {
        $this->connection = $connection;
    }

    public function getName()
    {
        return 'insert';
    }

    public function copy($resource)
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

        $sql = "INSERT INTO $tablename ($columns)
                SELECT $tempColumns FROM $tempTablename temp $joins $whereClause";

        $this->connection->beginTransaction();

        try {
            $stmt = $this->connection->executeQuery($sql);
            $lines = $stmt->rowCount();
            $this->connection->commit();
        } catch (UniqueConstraintViolationException $exception) {
            $this->connection->rollback();
            $lines = 0;
        } catch (NotNullConstraintViolationException $exception) {
            $this->connection->rollback();
            $lines = 0;

            if (preg_match('/SQLSTATE[^"]*"([^"]*)"/xms', $exception->getMessage(), $matches)) {
                throw new ImportException("Insert failed: not-null values found in \"${matches[1]}\" column");
            }
        } catch (DBALException $exception) {
            $this->connection->rollback();

            throw $exception;
        }

        return $lines;
    }
}
