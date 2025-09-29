<?php

namespace LePhare\Import\Tests;

use LePhare\Import\ImportConfiguration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

/**
 * @covers \LePhare\Import\ImportConfiguration
 *
 * @internal
 */
class ImportConfigurationTest extends TestCase
{
    private function createConfiguration(array $parameters = []): ImportConfiguration
    {
        return new ImportConfiguration($parameters);
    }

    public function testInvocation(): void
    {
        $this->assertInstanceOf(ConfigurationInterface::class, $this->createConfiguration());
    }

    public function testGetConfigTreeBuilder(): void
    {
        $configuration = $this->createConfiguration();
        $treeBuilder = $configuration->getConfigTreeBuilder();
        $this->assertInstanceOf(TreeBuilder::class, $treeBuilder);
    }

    /**
     * @param string|array $expectation
     *
     * @dataProvider providerConfigurations
     */
    public function testConfiguration($expectation, array $config, array $parameters = []): void
    {
        if (is_string($expectation)) {
            $this->expectException(InvalidConfigurationException::class);
            $this->expectExceptionMessageMatches($expectation);
        }

        $configuration = $this->createConfiguration($parameters);
        $treeBuilder = $configuration->getConfigTreeBuilder();
        $processor = new Processor();
        $this->assertEquals($expectation, $processor->process($treeBuilder->buildTree(), $config));
    }

    public function providerConfigurations(): iterable
    {
        yield 'Missing “name” configuration' => [
            '/the child config "name" under "import" must be configured/i',
            [],
        ];

        $config = [];
        $config['name'] = 'import-identifier';
        yield 'Missing “source_dir” configuration' => [
            '/the child config "source_dir" under "import" must be configured/i',
            [
                'lephare_import' => $config,
            ],
        ];

        $config['source_dir'] = '%kernel.project_dir%/var/exchange/input';
        yield 'Missing resources” configuration' => [
            '/the child config "resources" under "import" must be configured/i',
            [
                'lephare_import' => $config,
            ],
        ];

        $defaults = [
            'label' => null,
            'log_dir' => null,
            'archive' => [
                'dir' => null,
                'enabled' => true,
                'rotation' => 30,
            ],
            'quarantine' => [
                'dir' => null,
                'enabled' => true,
                'rotation' => 30,
            ],
            'email_report' => [
                'recipients' => [],
                'subject_pattern' => '[%status%] Import report : %name%',
                'email_template' => null,
            ],
        ];

        $config['resources'] = [];
        yield 'Minimal valid configuration' => [
            array_merge($defaults, [
                'name' => $config['name'],
                'source_dir' => str_replace('%kernel.project_dir%', '/app', $config['source_dir']),
                'resources' => $config['resources'],
            ]), [
                'lephare_import' => $config,
            ], [
                'kernel.project_dir' => '/app',
            ],
        ];

        $config['resources'] = [
            'foo' => [],
        ];

        yield 'Missing “tablename” resource configuration' => [
            '/the child config "tablename" under "import.resources.foo" must be configured/i',
            [
                'lephare_import' => $config,
            ],
        ];

        $config['resources'] = [
            'foo' => [
                'tablename' => 'foo',
                'load' => [
                    'format' => 'csv',
                    'fields' => [
                        'foo' => 'string',
                    ],
                    'format_options' => [
                        'field_delimiter' => '\t',
                        'quote_character' => '\t',
                        'line_delimiter' => '\n',
                        'escape_character' => '\t',
                    ],
                ],
            ],
        ];

        yield '“delimiter” should be evaluated' => [
            array_merge($defaults, [
                'name' => $config['name'],
                'source_dir' => str_replace('%kernel.project_dir%', '/app', $config['source_dir']),
                'resources' => [
                    'foo' => [
                        'tablename' => 'foo',
                        'load' => [
                            'format' => 'csv',
                            'format_options' => [
                                'field_delimiter' => "\t",
                                'line_delimiter' => PHP_EOL,
                                'with_header' => true,
                                'validate_headers' => true,
                                'sheet_index' => 0,
                                'null_string' => '',
                                'quote_character' => "\t",
                                'escape_character' => "\t",
                                'pgsql_format' => 'csv',
                            ],
                            'add_file_line_number' => true,
                            'loop' => false,
                            'strategy' => 'load_alphabetically',
                            'fields' => [
                                'foo' => [
                                    'type' => 'string',
                                    'options' => [
                                        'notnull' => false,
                                    ],
                                ],
                            ],
                            'extra_fields' => [],
                            'indexes' => [],
                            'fail_if_not_loaded' => false,
                        ],
                    ],
                ],
            ]), [
                'lephare_import' => $config,
            ], [
                'kernel.project_dir' => '/app',
            ],
        ];
    }
}
