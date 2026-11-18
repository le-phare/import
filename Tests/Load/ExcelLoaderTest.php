<?php

namespace LePhare\Import\Tests\Load;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use LePhare\Import\Configuration\Credentials;
use LePhare\Import\ImportResource;
use LePhare\Import\Load\ExcelLoader;
use phpmock\Mock;
use phpmock\prophecy\PHPProphet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \LePhare\Import\Load\ExcelLoader
 *
 * @internal
 */
class ExcelLoaderTest extends TestCase
{
    use ProphecyTrait;

    private string $file;

    protected function setUp(): void
    {
        $this->file = sys_get_temp_dir().'/lephare-import-'.uniqid().'.xls';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->file)) {
            unlink($this->file);
        }

        Mock::disableAll();
    }

    public function testSupportsOnlyXlsResourcesWithAFile(): void
    {
        $loader = new ExcelLoader($this->prophesize(Connection::class)->reveal(), new Credentials('host', 5432, 'user', 'password'));

        $this->assertTrue($loader->supports($this->createResource('xls'), ['file' => $this->file]));
        $this->assertFalse($loader->supports($this->createResource('xls'), []));
        $this->assertFalse($loader->supports($this->createResource('csv'), ['file' => $this->file]));
    }

    public function testLoadsTheFirstSheetAsCsv(): void
    {
        if (!function_exists('pg_connect')) {
            $this->markTestSkipped('pgsql is not installed');
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->fromArray([
            ['id', 'name', 'price'],
            [1, 'Doe; Jane', 1234.5],
            [2, 'Éloïse', 30],
        ]);
        IOFactory::createWriter($spreadsheet, 'Xls')->save($this->file);

        $platform = new PostgreSQLPlatform();
        $connection = $this->prophesize(Connection::class);
        $connection->getDatabasePlatform()->willReturn($platform);
        $connection->getDatabase()->willReturn('test');
        $connection->quote(Argument::type('string'))->will(fn (array $args): string => $platform->quoteStringLiteral($args[0]));
        $connection->quoteIdentifier(Argument::type('string'))->will(fn (array $args): string => $platform->quoteIdentifier($args[0]));

        $lines = [];
        $pg = new \stdClass();
        $prophet = new PHPProphet();
        $functions = $prophet->prophesize('LePhare\Import\Load');
        $functions->pg_connect(Argument::type('string'))->willReturn($pg); // @phpstan-ignore-line
        $functions->pg_query($pg, Argument::type('string'))->willReturn(true); // @phpstan-ignore-line
        $functions->pg_put_line($pg, Argument::type('string'))->will(function (array $args) use (&$lines): bool { // @phpstan-ignore-line
            $lines[] = $args[1];

            return true;
        });
        $functions->pg_end_copy($pg)->willReturn(true); // @phpstan-ignore-line
        $functions->pg_last_error($pg)->willReturn(''); // @phpstan-ignore-line
        $functions->pg_close($pg)->willReturn(true); // @phpstan-ignore-line
        $functions->reveal();

        $loader = new ExcelLoader($connection->reveal(), new Credentials('host', 5432, 'user', 'password'));
        $loaded = $loader->load($this->createResource('xls'), ['file' => $this->file]);

        $this->assertSame(3, $loaded);
        $this->assertSame([
            ['id', 'name', 'price'],
            ['1', 'Doe; Jane', '1234.5'],
            ['2', 'Éloïse', '30'],
        ], array_map(fn (string $line): array => str_getcsv(rtrim($line, "\n"), ';', '"', '\\'), $lines));
        $this->assertFileDoesNotExist($this->file.'.csv');
    }

    private function createResource(string $format): ImportResource
    {
        return new ImportResource('products', [
            'tablename' => 'import.products',
            'load' => [
                'format' => $format,
                'fields' => [
                    'id' => 'integer',
                    'name' => 'string',
                    'price' => 'string',
                ],
                'format_options' => [
                    'field_delimiter' => ';',
                    'line_delimiter' => "\n",
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
}
