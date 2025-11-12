<?php

namespace LePhare\Import\Tests;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Result;
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

    public function testExecuteWithFailIfNotLoadedFalseOptionShouldSucceed(): void
    {
        $this->connection->getDatabasePlatform()->willReturn(new PostgreSQLPlatform());
        $result = $this->prophesize(Result::class);
        $this->connection->executeQuery(Argument::any())->willReturn($result->reveal());

        $this->import->init(array_merge(self::config, [
            'resources' => [
                'foo' => [
                    'tablename' => 'import.users',
                    'load' => [
                        'fail_if_not_loaded' => false,
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
            ],
        ]));

        $this->assertTrue($this->import->execute());
    }

    public function testExecuteWithFailIfNotLoadedTrueOptionShouldFail(): void
    {
        $this->connection->getDatabasePlatform()->willReturn(new PostgreSQLPlatform());
        $result = $this->prophesize(Result::class);
        $this->connection->executeQuery(Argument::any())->willReturn($result->reveal());

        $this->import->init(array_merge(self::config, [
            'resources' => [
                'foo' => [
                    'tablename' => 'import.users',
                    'load' => [
                        'fail_if_not_loaded' => true,
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
            ],
        ]));

        $this->assertFalse($this->import->execute());
    }

    public function testSharedConnectionModeIsSetCorrectly(): void
    {
        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->hasListeners(Argument::any())->willReturn(false);

        $import = new Import(
            $this->connection->reveal(),
            $eventDispatcher->reveal(),
            $this->prophesize(StrategyRepositoryInterface::class)->reveal(),
            $this->loadStrategyRepository->reveal(),
            new ImportConfiguration(),
            $this->logger->reveal(),
            true // Enable shared connection mode
        );

        $this->assertTrue($import->isSharedConnection());
    }

    public function testSharedConnectionModeDefaultsToFalse(): void
    {
        $this->assertFalse($this->import->isSharedConnection());
    }

    /**
     * @uses \LePhare\Import\ImportResource
     * @uses \LePhare\Import\Strategy\InsertStrategy
     */
    public function testTransactionsAreUsedWhenSharedConnectionModeIsDisabled(): void
    {
        // Create a real InsertStrategy with a mocked connection
        $connection = $this->prophesize(Connection::class);
        $platform = new PostgreSQLPlatform();
        $connection->getDatabasePlatform()->willReturn($platform);
        $connection->quoteIdentifier(Argument::any())->will(function ($args) use ($platform) {
            return $platform->quoteIdentifier($args[0]);
        });

        // Create strategy repository with InsertStrategy
        $strategyRepository = $this->prophesize(StrategyRepositoryInterface::class);
        $insertStrategy = new \LePhare\Import\Strategy\InsertStrategy($connection->reveal());
        $strategyRepository->getStrategy('insert')->willReturn($insertStrategy);

        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->hasListeners(Argument::any())->willReturn(false);

        $import = new Import(
            $connection->reveal(),
            $eventDispatcher->reveal(),
            $strategyRepository->reveal(),
            $this->loadStrategyRepository->reveal(),
            new ImportConfiguration(),
            $this->logger->reveal(),
            false // Shared connection mode disabled (default)
        );

        // Setup expectations: beginTransaction and commit should be called
        $connection->beginTransaction()->shouldBeCalledOnce();
        $result = $this->prophesize(Result::class);
        $result->rowCount()->willReturn(5);
        $connection->executeQuery(Argument::any())->willReturn($result->reveal());
        $connection->commit()->shouldBeCalledOnce();
        $connection->executeQuery(Argument::containingString('CREATE SCHEMA'))->willReturn($result->reveal());
        $connection->executeQuery(Argument::containingString('DROP TABLE'))->willReturn($result->reveal());
        $connection->executeQuery(Argument::containingString('CREATE TABLE'))->willReturn($result->reveal());

        $config = [
            'name' => 'test',
            'source_dir' => '/tmp',
            'resources' => [
                'test_resource' => [
                    'tablename' => 'import.test_table',
                    'load' => [
                        'pattern' => 'test.csv',
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
                    'copy' => [
                        'target' => 'public.target_table',
                        'strategy' => 'insert',
                        'mapping' => [
                            'id' => 'id',
                        ],
                    ],
                ],
            ],
        ];

        $import->init($config);
        $import->copy();

        // Verify that beginTransaction and commit were called
        $connection->beginTransaction()->shouldHaveBeenCalled();
        $connection->commit()->shouldHaveBeenCalled();
    }

    /**
     * @uses \LePhare\Import\ImportResource
     * @uses \LePhare\Import\Strategy\InsertStrategy
     */
    public function testTransactionsAreNotUsedWhenSharedConnectionModeIsEnabled(): void
    {
        // Create a real InsertStrategy with a mocked connection
        $connection = $this->prophesize(Connection::class);
        $platform = new PostgreSQLPlatform();
        $connection->getDatabasePlatform()->willReturn($platform);
        $connection->quoteIdentifier(Argument::any())->will(function ($args) use ($platform) {
            return $platform->quoteIdentifier($args[0]);
        });

        // Create strategy repository with InsertStrategy
        $strategyRepository = $this->prophesize(StrategyRepositoryInterface::class);
        $insertStrategy = new \LePhare\Import\Strategy\InsertStrategy($connection->reveal());
        $strategyRepository->getStrategy('insert')->willReturn($insertStrategy);

        $eventDispatcher = $this->prophesize(EventDispatcherInterface::class);
        $eventDispatcher->hasListeners(Argument::any())->willReturn(false);

        $import = new Import(
            $connection->reveal(),
            $eventDispatcher->reveal(),
            $strategyRepository->reveal(),
            $this->loadStrategyRepository->reveal(),
            new ImportConfiguration(),
            $this->logger->reveal(),
            true // Shared connection mode ENABLED
        );

        // Setup expectations: beginTransaction and commit should NOT be called
        $connection->beginTransaction()->shouldNotBeCalled();
        $result = $this->prophesize(Result::class);
        $result->rowCount()->willReturn(5);
        $connection->executeQuery(Argument::any())->willReturn($result->reveal());
        $connection->commit()->shouldNotBeCalled();
        $connection->rollback()->shouldNotBeCalled();
        $connection->executeQuery(Argument::containingString('CREATE SCHEMA'))->willReturn($result->reveal());
        $connection->executeQuery(Argument::containingString('DROP TABLE'))->willReturn($result->reveal());
        $connection->executeQuery(Argument::containingString('CREATE TABLE'))->willReturn($result->reveal());

        $config = [
            'name' => 'test',
            'source_dir' => '/tmp',
            'resources' => [
                'test_resource' => [
                    'tablename' => 'import.test_table',
                    'load' => [
                        'pattern' => 'test.csv',
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
                    'copy' => [
                        'target' => 'public.target_table',
                        'strategy' => 'insert',
                        'mapping' => [
                            'id' => 'id',
                        ],
                    ],
                ],
            ],
        ];

        $import->init($config);
        $import->copy();

        // Verify that beginTransaction, commit, and rollback were NOT called
        $connection->beginTransaction()->shouldNotHaveBeenCalled();
        $connection->commit()->shouldNotHaveBeenCalled();
        $connection->rollback()->shouldNotHaveBeenCalled();
    }

    public function tearDown(): void
    {
        @unlink('/tmp/config.yml');
    }
}
