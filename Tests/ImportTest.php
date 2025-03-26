<?php

namespace LePhare\Import\Tests;

use Doctrine\DBAL\Connection;
use LePhare\Import\Exception\ExceptionInterface;
use LePhare\Import\Exception\ImportException;
use LePhare\Import\Import;
use LePhare\Import\ImportConfiguration;
use LePhare\Import\ImportInterface;
use LePhare\Import\LoadStrategy\LoadStrategyInterface;
use LePhare\Import\LoadStrategy\LoadStrategyRepositoryInterface;
use LePhare\Import\Strategy\StrategyRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PhpUnit\ExpectDeprecationTrait;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @covers \LePhare\Import\Import
 *
 * @uses \LePhare\Import\ImportConfiguration
 *
 * @internal
 */
final class ImportTest extends TestCase
{
    use ProphecyTrait;
    use ExpectDeprecationTrait;

    private Import $import;
    private ObjectProphecy $logger;
    private ObjectProphecy $connection;

    /** @var ObjectProphecy<LoadStrategyRepositoryInterface> */
    private ObjectProphecy $loadStrategyRepository;

    private const config = [
        'name' => 'test',
        'source_dir' => '/tmp',
        'resources' => [],
    ];

    public function setUp(): void
    {
        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->connection = $this->prophesize(Connection::class);
        $this->loadStrategyRepository = $this->prophesize(LoadStrategyRepositoryInterface::class);
        $loadStrategy = $this->prophesize(LoadStrategyInterface::class);
        $this->loadStrategyRepository->getLoadStrategies()->willReturn(['load_alphabetically' => $loadStrategy]);
        $this->loadStrategyRepository->getLoadStrategy('load_alphabetically')->willReturn($loadStrategy->reveal());
        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->hasListeners(Argument::any())->willReturn(false);

        $this->import = new Import(
            $this->connection->reveal(),
            $eventDispatcher->reveal(),
            $this->prophesize(StrategyRepositoryInterface::class)->reveal(),
            $this->loadStrategyRepository->reveal(),
            new ImportConfiguration(),
            $this->logger->reveal()
        );
    }

    public function testImplementsImportInterface(): void
    {
        $this->assertInstanceOf(ImportInterface::class, $this->import);
    }

    public function testInit(): void
    {
        $this->import->init(self::config);
        $config = $this->import->getConfig();
        $this->assertInstanceOf(\Traversable::class, $config);
        $this->assertSame(self::config['name'], $config['name']);
    }

    public function testExecute(): void
    {
        $this->import->init(self::config);
        $this->assertTrue($this->import->execute());
    }

    /** @uses \LePhare\Import\ImportResource */
    public function testExecuteWithException(): void
    {
        $this->connection
            ->getDatabasePlatform()
            ->shouldBeCalled()
            ->willThrow(new \ErrorException('simulate error exception'))
        ;

        $this->expectException(ExceptionInterface::class);
        $this->expectExceptionMessage('simulate error exception');

        $this->import->init(array_merge(self::config, [
            'resources' => [
                'foo' => [
                    'tablename' => 'import.users',
                    'load' => [
                        'pattern' => 'bar.csv',
                        'format_options' => [
                            'validate_headers' => true,
                            'with_header' => true,
                            'field_delimiter' => ',',
                            'line_delimiter' => "\n",
                        ],
                        'fields' => [
                            'id' => 'integer',
                        ],
                        'strategy' => 'load_alphabetically',
                    ],
                ],
            ], ]));

        $this->assertFalse($this->import->execute());
    }

    /** @uses \LePhare\Import\ImportResource */
    public function testExecuteWithWrongStrategyShouldFail(): void
    {
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('Invalid load strategy. Available load strategies are : load_alphabetically');
        $this->loadStrategyRepository->getLoadStrategy('bad_strategy_name')->willReturn(null)->shouldBeCalled();

        $this->import->init(array_merge(self::config, [
            'resources' => [
                'foo' => [
                    'tablename' => 'import.users',
                    'load' => [
                        'pattern' => 'bar.csv',
                        'format_options' => [
                            'validate_headers' => true,
                            'with_header' => true,
                            'field_delimiter' => ',',
                            'line_delimiter' => "\n",
                        ],
                        'fields' => [
                            'id' => 'integer',
                        ],
                        'strategy' => 'bad_strategy_name',
                    ],
                ],
            ], ]));

        $this->assertFalse($this->import->execute());
    }

    public function tearDown(): void
    {
        @unlink('/tmp/config.yml');
    }
}
