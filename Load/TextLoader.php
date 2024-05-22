<?php

namespace LePhare\Import\Load;

use Behat\Transliterator\Transliterator;
use Doctrine\DBAL\Connection;
use ForceUTF8\Encoding;
use LePhare\Import\Configuration\CredentialsInterface;
use LePhare\Import\Exception\ImportException;
use LePhare\Import\ImportResource;

/** @api */
class TextLoader implements LoaderInterface
{
    protected Connection $connection;

    private CredentialsInterface $credentials;

    public function __construct(Connection $connection, CredentialsInterface $credentials)
    {
        $this->connection = $connection;
        $this->credentials = $credentials;
    }

    public function supports(ImportResource $resource, array $context): bool
    {
        return 'text' === $resource->getFormat() && isset($context[LoaderInterface::FILE]);
    }

    public function load(ImportResource $resource, array $context): int
    {
        $file = $context[LoaderInterface::FILE];
        $connection = $this->connection;
        $platform = $connection->getDatabasePlatform();

        $fields = array_map(function ($v) use ($connection) {
            $v = Transliterator::urlize($v, '_');

            return $connection->quoteIdentifier($v);
        }, array_keys($resource->getCsvFields()));
        $fields = implode(',', $fields);

        $from = $connection->quote($file);
        $tablename = $platform->quoteIdentifier($resource->getTablename());
        $fieldDelimiter = $connection->quote($resource->getFieldDelimiter());
        $lineDelimiter = $connection->quote($resource->getLineDelimiter());
        $quoteCharacter = $connection->quote($resource->getQuoteCharacter());

        if ('postgresql' === $platform->getName()) {
            if (!function_exists('pg_connect')) {
                throw new ImportException('You need the pgsql extension to load CSV in PostgreSQL');
            }

            // Use low-level driver to perform data load
            $pg = pg_connect(sprintf(
                'host=%s user=%s password=%s dbname=%s port=%s',
                $this->credentials->getHost(),
                $this->credentials->getUsername(),
                $this->credentials->getPassword(),
                $connection->getDatabase(),
                $this->credentials->getPort()
            ));

            // Date format
            pg_query($pg, "SET datestyle = 'ISO, DMY'");

            $sql = "
                COPY $tablename ($fields)
                FROM STDIN
                WITH (
                    FORMAT text
                )
            ";
            pg_query($pg, $sql);

            // Iterate file to put lines
            $fp = fopen($file, 'r');

            if (false === $fp) {
                throw new ImportException(sprintf('Could not open file to load \'%s\'', $file));
            }

            $loaded = 0;
            do {
                pg_put_line($pg, Encoding::toUTF8(fgets($fp)));
                ++$loaded;
            } while (!feof($fp));
            fclose($fp);
            pg_end_copy($pg);
            pg_close($pg);
        } else {
            throw new ImportException($platform->getName().' platform is not supported');
        }

        return $loaded;
    }
}
