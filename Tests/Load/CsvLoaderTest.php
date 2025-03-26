<?php

namespace LePhare\Import\Tests\Load;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use LePhare\Import\Configuration\Credentials;
use LePhare\Import\Exception\ImportException;
use LePhare\Import\ImportResource;
use LePhare\Import\Load\CsvLoader;
use phpmock\Mock;
use phpmock\prophecy\PHPProphet;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;

/**
 * @covers \LePhare\Import\Load\CsvLoader
 */
class CsvLoaderTest extends TestCase
{
    use ProphecyTrait;
    private const TEST_TABLENAME = 'test_csv_loader';
    private const TEST_FILEPATH = '/tmp/test_csv_loader.csv';

    /** @var ObjectProphecy<Connection> */
    private ObjectProphecy $connection;

    private Credentials $credentials;
    private ImportResource $resource;
    private PHPProphet $phpmockProphet;

    public function dataProvider(): iterable
    {
        yield 'id, name, age' => [
            'expectImportException' => false,
            'data' => <<<CSV
            id,name,age
            1,Alice,30
            2,Bob,40
            3,Charlie,50

            CSV,
        ];

        yield 'id, age, name' => [
            'expectImportException' => false,
            'data' => <<<CSV
            id,age,name
            1,30,Alice
            2,40,Bob
            3,50,Charlie

            CSV,
        ];

        yield 'id, name' => [
            'expectImportException' => true,
            'data' => <<<CSV
            id,name
            1,Alice
            2,Bob
            3,Charlie

            CSV,
        ];

        yield 'id, name, name' => [
            'expectImportException' => true,
            'data' => <<<CSV
            id,name,name
            1,Alice,Foo
            2,Bob,Bar
            3,Charlie,FooBar

            CSV,
        ];

        yield 'id, name, age, name' => [
            'expectImportException' => true,
            'data' => <<<CSV
            id,name,age,name
            1,Alice,30,Foo
            2,Bob,40,Bar
            3,Charlie,50,FooBar

            CSV,
        ];
    }

    /**
     * @dataProvider dataProvider
     */
    public function testPostgreSQLLoad(bool $expectImportException, string $csvContent): void
    {
        $data = $this->createCsv($csvContent);
        $fields = $data[0];

        $filename = self::TEST_FILEPATH;

        if (!$expectImportException) {
            $platform = new PostgreSQLPlatform();
            $this->connection->getDatabasePlatform()->willReturn($platform);
            $this->connection->quote($filename)->willReturn($platform->quoteStringLiteral($filename));
            $this->connection->quote(',')->willReturn($platform->quoteStringLiteral(','));
            $this->connection->quote('\n')->willReturn($platform->quoteStringLiteral('\n'));
            $this->connection->quote('"')->willReturn($platform->quoteStringLiteral('"'));
            $this->connection->quote('\\')->willReturn($platform->quoteStringLiteral('\\'));
            $this->connection->quote('')->willReturn($platform->quoteStringLiteral(''));
            $this->connection->quote('test_csv_loader.csv')->willReturn($platform->quoteStringLiteral('test_csv_loader.csv'));
            $this->connection->quoteIdentifier('id')->willReturn($platform->quoteIdentifier('id'));
            $this->connection->quoteIdentifier('name')->willReturn($platform->quoteIdentifier('name'));
            $this->connection->quoteIdentifier('age')->willReturn($platform->quoteIdentifier('age'));
            $this->connection->quoteIdentifier('file_line_no')->willReturn($platform->quoteIdentifier('file_line_no'));
            $this->connection->getDatabase()->willReturn('test');

            $prophecy = $this->phpmockProphet->prophesize('LePhare\Import\Load');
            $pgsqlConnection = new \stdClass();
            $prophecy->pg_connect(sprintf( // @phpstan-ignore-line
                'host=%s user=%s password=%s dbname=%s port=%s',
                $this->credentials->getHost(),
                $this->credentials->getUsername(),
                $this->credentials->getPassword(),
                'test',
                $this->credentials->getPort()
            ))->willReturn($pgsqlConnection);

            foreach ($data as $row) {
                $prophecy->pg_put_line($pgsqlConnection, implode(',', $row)."\n")->shouldBeCalledOnce()->willReturn(true); // @phpstan-ignore-line
            }

            $prophecy->pg_end_copy($pgsqlConnection)->shouldBeCalledOnce()->willReturn(true); // @phpstan-ignore-line
            $prophecy->pg_last_error($pgsqlConnection)->shouldBeCalledOnce()->willReturn('0'); // @phpstan-ignore-line
            $prophecy->pg_close($pgsqlConnection)->shouldBeCalledOnce()->willReturn(true); // @phpstan-ignore-line
            $joinedFields = implode(',', array_map(fn ($field): string => '"'.$field.'"', $fields));

            $sql = <<<SQL
                COPY "import"."users" ($joinedFields)
                FROM STDIN
                WITH (
                    FORMAT csv,
                    QUOTE '"',
                    DELIMITER ',',
                    ESCAPE '\',
                    HEADER,
                    NULL ''
                )
            SQL;
            $prophecy->pg_query($pgsqlConnection, $sql)->shouldBeCalledOnce()->willReturn(false); // @phpstan-ignore-line
            $prophecy->pg_query($pgsqlConnection, "SET datestyle = 'ISO, DMY'")->shouldBeCalledOnce()->willReturn(false); // @phpstan-ignore-line
            $prophecy->reveal();
        } else {
            $this->expectException(ImportException::class);
        }

        $loader = new CsvLoader($this->connection->reveal(), $this->credentials);
        $loadedResourcesCount = $loader->load($this->resource, [
            'file' => $filename,
        ]);

        $this->phpmockProphet->checkPredictions();

        $this->assertEquals(count($data), $loadedResourcesCount);
    }

    /**
     * @dataProvider dataProvider
     */
    public function testMysqlLoad(bool $expectImportException, string $csvContent): void
    {
        if (!class_exists(\mysqli::class)) {
            $this->markTestSkipped('mysqli is not installed');
        }

        $data = $this->createCsv($csvContent);
        $fields = $data[0];
        $filename = self::TEST_FILEPATH;
        $mysqli = $this->prophesize(\mysqli::class);

        if (!$expectImportException) {
            $platform = new MySQLPlatform();
            $mysqli->init()->willReturn(true);
            $mysqli->close()->willReturn(true);
            $mysqli->options(MYSQLI_OPT_LOCAL_INFILE, 1)->willReturn(true);
            $mysqli->query('set @row = 1')->willReturn(true);
            $mysqli->real_connect(
                $this->credentials->getHost(),
                $this->credentials->getUsername(),
                $this->credentials->getPassword(),
                'test',
                $this->credentials->getPort()
            )->willReturn(true);

            $joinedFields = implode(',', array_map(fn ($field): string => '`'.$field.'`', $fields));

            $from = $platform->quoteStringLiteral($filename);
            $tablename = '`import`.`users`';

            $sql = <<<SQL
            LOAD DATA LOCAL INFILE $from IGNORE
            INTO TABLE $tablename
            FIELDS
                TERMINATED BY ','
                OPTIONALLY ENCLOSED BY '"'
                ESCAPED BY '\\\'
            LINES
                TERMINATED BY '\\\\n'
            IGNORE 1 LINES
            ($joinedFields)
            SET file_line_no = CONCAT($from,':',@row:=@row+1)
            SQL;

            $mysqli->query($sql)->shouldBeCalledOnce()->willReturn(true);
            $mysqliResult = $this->prophesize(\mysqli_result::class);
            $mysqliResult->fetch_array()->willReturn([3]);
            $mysqli->query("SELECT COUNT(*) FROM $tablename")->shouldBeCalledOnce()->willReturn($mysqliResult->reveal());

            $this->connection->getDatabasePlatform()->willReturn($platform);
            $this->connection->quote($filename)->willReturn($platform->quoteStringLiteral($filename));
            $this->connection->quote(',')->willReturn($platform->quoteStringLiteral(','));
            $this->connection->quote('\n')->willReturn($platform->quoteStringLiteral('\n'));
            $this->connection->quote('"')->willReturn($platform->quoteStringLiteral('"'));
            $this->connection->quote('\\')->willReturn($platform->quoteStringLiteral('\\'));
            $this->connection->quote('')->willReturn($platform->quoteStringLiteral(''));
            $this->connection->quote('test_csv_loader.csv')->willReturn($platform->quoteStringLiteral('test_csv_loader.csv'));
            $this->connection->quoteIdentifier('id')->willReturn($platform->quoteIdentifier('id'));
            $this->connection->quoteIdentifier('name')->willReturn($platform->quoteIdentifier('name'));
            $this->connection->quoteIdentifier('age')->willReturn($platform->quoteIdentifier('age'));
            $this->connection->quoteIdentifier('file_line_no')->willReturn($platform->quoteIdentifier('file_line_no'));
            $this->connection->getDatabase()->willReturn('test');
        } else {
            $this->expectException(ImportException::class);
        }

        $loader = new CsvLoader($this->connection->reveal(), $this->credentials, $mysqli->reveal());
        $loadedResourcesCount = $loader->load($this->resource, [
            'file' => $filename,
        ]);

        $this->assertEquals(count($data) - 1, $loadedResourcesCount);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->prophesize(Connection::class);
        $this->credentials = new Credentials('host', 3306, 'user', 'password');
        $this->phpmockProphet = new PHPProphet();
        $this->resource = new ImportResource(self::TEST_TABLENAME, [
            'tablename' => 'import.users',
            'load' => [
                'format' => 'csv',
                'fields' => [
                    'id' => 'integer',
                    'name' => 'string',
                    'age' => 'integer',
                ],
                'format_options' => [
                    'field_delimiter' => ',',
                    'line_delimiter' => '\n',
                    'quote_character' => '"',
                    'escape_character' => '\\',
                    'null_string' => '',
                    'with_header' => true,
                    'validate_headers' => true,
                ],
                'add_file_line_number' => false,
            ],
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // delete the test file
        unlink(self::TEST_FILEPATH);

        Mock::disableAll();
    }

    private function createCsv(string $csvContent): array
    {
        // create the test file
        $handle = fopen(self::TEST_FILEPATH, 'w+');
        fwrite($handle, $csvContent);
        fseek($handle, 0);
        $data = [];

        while ($row = fgetcsv($handle)) {
            $data[] = $row;
        }

        fclose($handle);

        return $data;
    }
}
