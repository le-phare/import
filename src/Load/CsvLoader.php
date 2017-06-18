<?php

namespace LePhare\Import\Load;

use Behat\Transliterator\Transliterator;
use Doctrine\DBAL\Connection;
use ForceUTF8\Encoding;
use LePhare\Import\Exception\ImportException;

class CsvLoader
{
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function load($resource, $file)
    {
        $connection = $this->connection;
        $platform = $connection->getDatabasePlatform();

        $fields = array_map(function ($v) use ($connection) {
            $v = Transliterator::urlize($v, '_');
            $v = $connection->quoteIdentifier($v);
            return $v;
        }, array_keys($resource->getCsvFields()));
        $fields = implode(',', $fields);

        $from = $connection->quote($file);
        $tablename = $platform->quoteIdentifier($resource->getTablename());
        $fieldDelimiter = $connection->quote($resource->getFieldDelimiter());
        $lineDelimiter = $connection->quote($resource->getLineDelimiter());
        $quoteCharacter = $connection->quote($resource->getQuoteCharacter());

        if ('mysql' === $platform->getName()) {
            if (!class_exists('\MySQLi')) {
                throw new ImportException('You need the mysqli extension to load CSV in MySQL');
            }

            // There is a bug in PHP-PDO https://bugs.php.net/bug.php?id=55737
            $conn = new \MySQLi();
            $conn->init();
            $conn->options(MYSQLI_OPT_LOCAL_INFILE, true);
            $conn->real_connect(
                $connection->getHost(),
                $connection->getUsername(),
                $connection->getPassword(),
                $connection->getDatabase()
            );

            $header = "";
            if ($resource->getConfig()['load']['format_options']['with_header']) {
                $header = "IGNORE 1 LINES";
            }

            $sql = "
                LOAD DATA LOCAL INFILE $from IGNORE INTO TABLE $tablename
                FIELDS TERMINATED BY $fieldDelimiter
                OPTIONALLY ENCLOSED BY $quoteCharacter
                LINES TERMINATED BY $lineDelimiter
                $header ($fields)
                SET file_line_no = CONCAT($from,':',@row:=@row+1)
            ";

            $conn->query('set @row = 1');
            if (!$conn->query($sql)) {
                throw new ImportException($conn->error);
            }

            $rowCountSql = "SELECT COUNT(*) FROM $tablename";
            $rowCountStmt = $conn->query($rowCountSql);
            $rowCount = (int) $rowCountStmt->fetch_array()[0];

            $conn->close();

            $loaded = $rowCount;
        } elseif ('postgresql' === $platform->getName()) {
            if (!function_exists('pg_connect')) {
                throw new ImportException('You need the pgsql extension to load CSV in PostgreSQL');
            }

            // Use low-level driver to perform data load
            $pg = pg_connect(sprintf(
                'host=%s user=%s password=%s dbname=%s',
                $connection->getHost(),
                $connection->getUsername(),
                $connection->getPassword(),
                $connection->getDatabase()
            ));

            // Date format
            pg_query($pg, "SET datestyle = 'ISO, DMY'");

            $header = $resource->getConfig()['load']['format_options']['with_header'];

            $sql = "
                COPY $tablename ($fields)
                FROM STDIN
                WITH (
                    FORMAT csv,
                    QUOTE $quoteCharacter,
                    DELIMITER $fieldDelimiter,
                    NULL '',
                    HEADER $header
                )
            ";

            pg_query($pg, $sql);

            // Iterate file to put lines
            $fp = fopen($file, 'r');
            $loaded = 0;
            do {
                pg_put_line($pg, Encoding::toUTF8(fgets($fp)));
                $loaded += 1;
            } while (!feof($fp));
            fclose($fp);
            pg_end_copy($pg);
            pg_close($pg);
        } else {
            throw new ImportException($platform->getName(). " platform is not supported");
        }

        return $loaded;
    }
}
