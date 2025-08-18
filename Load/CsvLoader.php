<?php

namespace LePhare\Import\Load;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use ForceUTF8\Encoding;
use LePhare\Import\Configuration\CredentialsInterface;
use LePhare\Import\Exception\ImportException;
use LePhare\Import\ImportResource;
use LePhare\Import\Util\Transliterator;

/** @api */
class CsvLoader implements LoaderInterface
{
    protected Connection $connection;

    private CredentialsInterface $credentials;

    /** @var \MySQLi|mixed|null */
    private $mysqli;

    public function __construct(Connection $connection, CredentialsInterface $credentials, $mysqli = null)
    {
        $this->connection = $connection;
        $this->credentials = $credentials;
        $this->mysqli = $mysqli;
    }

    public function supports(ImportResource $resource, array $context): bool
    {
        return 'csv' === $resource->getFormat() && isset($context[LoaderInterface::FILE]);
    }

    public function load(ImportResource $resource, array $context): int
    {
        $file = $context[LoaderInterface::FILE];
        $connection = $this->connection;
        $platform = $connection->getDatabasePlatform();
        $fields = $this->getFields($resource, $context, $this->connection);
        $fieldDelimiter = $resource->getFieldDelimiter();

        $from = $connection->quote($file);
        $tablename = $platform->quoteIdentifier($resource->getTablename());
        $quotedFieldDelimiter = $connection->quote($fieldDelimiter);
        $lineDelimiter = $connection->quote($resource->getLineDelimiter());
        $quoteCharacter = $connection->quote($resource->getQuoteCharacter());
        $escapeCharacter = $connection->quote($resource->getEscapeCharacter());
        $nullString = $connection->quote($resource->getNullString());
        $addLineNumber = $resource->getAddLineNumber();

        if ($platform instanceof MySQLPlatform) {
            if (!class_exists('\MySQLi')) {
                throw new ImportException('You need the mysqli extension to load CSV in MySQL');
            }

            if (null !== $this->mysqli && !$this->mysqli instanceof \mysqli) {
                throw new ImportException('mysqli property should be instance of MySQLi');
            }

            // There is a bug in PHP-PDO https://bugs.php.net/bug.php?id=55737
            $conn = $this->mysqli ?? new \mysqli();
            $conn->init();
            $conn->options(MYSQLI_OPT_LOCAL_INFILE, 1);
            $conn->real_connect(
                $this->credentials->getHost(),
                $this->credentials->getUsername(),
                $this->credentials->getPassword(),
                $connection->getDatabase(),
                $this->credentials->getPort()
            );

            $header = '';
            if ($resource->getConfig()['load']['format_options']['with_header']) {
                $header = 'IGNORE 1 LINES';
            }

            $sql = <<<SQL
            LOAD DATA LOCAL INFILE {$from} IGNORE
            INTO TABLE {$tablename}
            FIELDS
                TERMINATED BY {$quotedFieldDelimiter}
                OPTIONALLY ENCLOSED BY {$quoteCharacter}
                ESCAPED BY {$escapeCharacter}
            LINES
                TERMINATED BY {$lineDelimiter}
            {$header}
            ({$fields})
            SET file_line_no = CONCAT({$from},':',@row:=@row+1)
            SQL;

            $conn->query('set @row = 1');
            if (!$conn->query($sql)) {
                throw new ImportException($conn->error);
            }

            $rowCountSql = "SELECT COUNT(*) FROM {$tablename}";
            $rowCountStmt = $conn->query($rowCountSql);
            $rowCount = (int) $rowCountStmt->fetch_array()[0];

            $conn->close();

            $loaded = $rowCount;
        } elseif ($platform instanceof PostgreSQLPlatform) {
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

            $header = $resource->getConfig()['load']['format_options']['with_header'] ? 'HEADER,' : '';

            if ($addLineNumber) {
                $fields = $this->connection->quoteIdentifier('file_line_no').','.$fields;
            }

            $sql = <<<SQL
                COPY {$tablename} ({$fields})
                FROM STDIN
                WITH (
                    FORMAT csv,
                    QUOTE {$quoteCharacter},
                    DELIMITER {$quotedFieldDelimiter},
                    ESCAPE {$escapeCharacter},
                    {$header}
                    NULL {$nullString}
                )
            SQL;
            pg_query($pg, $sql);

            // Iterate file to put lines
            $fp = fopen($file, 'r');

            if (false === $fp) {
                throw new ImportException(sprintf('Could not open file to load \'%s\'', $file));
            }

            $fileName = (new \SplFileInfo($file))->getFilename();

            $loaded = 0;
            while (!feof($fp)) {
                $line = fgets($fp);

                if (!$line) {
                    break;
                }

                pg_put_line(
                    $pg,
                    ($addLineNumber ? ($connection->quote($fileName).':'.$loaded.$fieldDelimiter) : '').
                    Encoding::toUTF8(
                        $line
                    )
                );
                ++$loaded;
            }

            fclose($fp);
            pg_end_copy($pg);
            $error = pg_last_error($pg);

            if ('' !== $error && '0' !== $error) {
                pg_close($pg);
                throw new ImportException($error);
            }

            pg_close($pg);
        } else {
            throw new ImportException($platform::class.' platform is not supported');
        }

        return $loaded;
    }

    private function getFields(ImportResource $resource, array $context, Connection $connection): string
    {
        $config = $resource->getConfig();
        $useFirstRowAsHeader = $config['load']['format_options']['with_header'] && $config['load']['format_options']['validate_headers'];
        $headers = $useFirstRowAsHeader ? $this->getFirstRow($resource, $context) : array_keys($resource->getCsvFields());

        if ($useFirstRowAsHeader && (count($headers) !== count($resource->getCsvFields()) || [] !== array_diff($headers, array_keys($resource->getCsvFields())) || [] !== array_diff(array_keys($resource->getCsvFields()), $headers))) {
            throw new ImportException('The first row of the CSV file must contain the same fields as defined in the configuration');
        }

        $fields = array_map(function (string $v) use ($connection) {
            $v = Transliterator::urlize($v, '_');

            return $connection->quoteIdentifier($v);
        }, $headers);

        return implode(',', $fields);
    }

    private function getFirstRow(ImportResource $resource, array $context): array
    {
        $file = $context[LoaderInterface::FILE];
        $fp = fopen($file, 'r');

        $bom = fread($fp, 3);
        if ("\xEF\xBB\xBF" != $bom) {
            fseek($fp, 0);
        }

        $line = fgetcsv($fp, 0, $resource->getFieldDelimiter(), $resource->getQuoteCharacter(), $resource->getEscapeCharacter());
        fclose($fp);

        return $line;
    }
}
