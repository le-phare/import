<?php

namespace LePhare\Import;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ImportConfiguration implements ConfigurationInterface
{
    private array $parameters;

    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('import');

        /** @var ArrayNodeDefinition */
        $rootNode = $treeBuilder->getRootNode();

        $replaceParameter = function ($string) {
            foreach ($this->parameters as $parameter => $value) {
                if (is_string($value)) {
                    $string = str_replace("%{$parameter}%", $value, $string);
                }
            }

            return $string;
        };

        $rootNode
            ->fixXmlConfig('annotation')
            ->children()
                ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('label')->defaultNull()->end()
                ->variableNode('log_dir')
                    ->defaultNull()
                    ->beforeNormalization()
                        ->ifString()
                        ->then($replaceParameter)
                    ->end()
                    ->validate()
                        ->ifTrue(function ($v) {
                            return !is_dir($v);
                        })
                        ->thenInvalid("Log directory '%s' does not exist.")
                    ->end()
                ->end()
                ->variableNode('source_dir')
                    ->isRequired()
                    ->cannotBeEmpty()
                    ->beforeNormalization()
                        ->ifString()
                        ->then($replaceParameter)
                    ->end()
                ->end()
                ->arrayNode('archive')
                    ->children()
                        ->scalarNode('dir')
                            ->defaultNull()
                            ->cannotBeEmpty()
                            ->beforeNormalization()
                                ->ifString()
                                ->then($replaceParameter)
                            ->end()
                        ->end()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('rotation')->defaultValue(30)->end()
                    ->end()
                    ->addDefaultsIfNotSet()
                ->end()
                ->arrayNode('quarantine')
                    ->children()
                        ->scalarNode('dir')
                            ->defaultNull()
                            ->cannotBeEmpty()
                            ->beforeNormalization()
                                ->ifString()
                                ->then($replaceParameter)
                            ->end()
                        ->end()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('rotation')->defaultValue(30)->end()
                    ->end()
                    ->addDefaultsIfNotSet()
                ->end()
                ->arrayNode('email_report')
                    ->children()
                        ->scalarNode('email_from')->isRequired()->end()
                        ->variableNode('recipients')
                            ->defaultValue([])
                            ->beforeNormalization()
                                ->ifString()
                                ->then(function ($value) use ($replaceParameter) {
                                    return array_map($replaceParameter, explode(',', $value));
                                })
                            ->end()
                        ->end()
                        ->scalarNode('subject_pattern')->defaultValue('[%status%] Import report : %name%')->end()
                        ->scalarNode('email_template')->defaultNull()->end()
                    ->end()
                    ->addDefaultsIfNotSet()
                ->end()
                ->arrayNode('resources')
                    ->isRequired()
                    ->useAttributeAsKey('name')

                    ->prototype('array')
                        ->addDefaultsIfNotSet()
                        ->children()
                            ->scalarNode('tablename')->isRequired()->end()
                            ->arrayNode('load')
                                ->children()
                                    ->scalarNode('pattern')->cannotBeEmpty()->end()
                                    ->booleanNode('add_file_line_number')->defaultTrue()->end()

                                    ->scalarNode('format')
                                        ->cannotBeEmpty()
                                        ->defaultValue('csv')
                                    ->end()

                                    ->arrayNode('format_options')
                                        ->addDefaultsIfNotSet()
                                        ->children()
                                            ->booleanNode('with_header')->defaultTrue()->end()
                                            ->booleanNode('validate_headers')->defaultTrue()->end()
                                            ->integerNode('sheet_index')->defaultValue(0)->end()
                                            ->scalarNode('null_string')->defaultValue('')->end()
                                            ->scalarNode('field_delimiter')
                                                ->defaultValue(';')
                                                ->validate()
                                                    ->ifString()
                                                    ->then(function ($v) {
                                                        eval("\$delimiter = \"$v\";");

                                                        return $delimiter; // @phpstan-ignore-line
                                                    })
                                                ->end()
                                            ->end()
                                            ->scalarNode('quote_character')
                                                ->defaultValue('"')
                                                ->validate()
                                                    ->ifString()
                                                    ->then(function ($v) {
                                                        eval("\$delimiter = \"$v\";");

                                                        return $delimiter; // @phpstan-ignore-line
                                                    })
                                                ->end()
                                            ->end()
                                            ->scalarNode('line_delimiter')
                                                ->defaultValue("\n")
                                                ->validate()
                                                    ->ifString()
                                                    ->then(function ($v) {
                                                        eval("\$delimiter = \"$v\";");

                                                        return $delimiter; // @phpstan-ignore-line
                                                    })
                                                ->end()
                                            ->end()
                                            ->scalarNode('escape_character')
                                                ->defaultValue('\\')
                                                ->validate()
                                                    ->ifString()
                                                    ->then(function ($v) {
                                                        eval("\$delimiter = \"$v\";");

                                                        return $delimiter; // @phpstan-ignore-line
                                                    })
                                                ->end()
                                            ->end()
                                            ->scalarNode('pgsql_format')->defaultValue('csv')->end()
                                        ->end()
                                    ->end()
                                    ->booleanNode('loop')->defaultFalse()->end()
                                    ->scalarNode('strategy')
                                        ->cannotBeEmpty()
                                        ->defaultValue('load_alphabetically')
                                        ->beforeNormalization()
                                            ->ifString()
                                            ->then(function (string $v) {
                                                switch ($v) {
                                                    case 'first_by_name':
                                                        return 'load_alphabetically';
                                                    case 'last_by_name':
                                                        return 'load_reverse_alphabetically';
                                                    default:
                                                        return $v;
                                                }
                                            })
                                        ->end()
                                    ->end()
                                    ->append($this->addColumnsNode('fields'))
                                    ->append($this->addColumnsNode('extra_fields'))

                                    ->variableNode('indexes')
                                        ->defaultValue([])
                                    ->end()

                                ->end()
                            ->end()
                            ->arrayNode('copy')
                                ->children()
                                    ->scalarNode('target')
                                        ->isRequired()
                                        ->cannotBeEmpty()
                                    ->end()

                                    ->scalarNode('strategy')
                                        ->cannotBeEmpty()
                                        ->defaultValue('insert_or_update')
                                    ->end()

                                    ->arrayNode('strategy_options')
                                        ->children()
                                            ->scalarNode('copy_condition')->defaultValue('')->end()
                                            ->booleanNode('distinct')->defaultFalse()->end()
                                            ->scalarNode('joins')->defaultValue('')->end()
                                            ->scalarNode('conflict_target')
                                                ->beforeNormalization()
                                                    ->ifString()
                                                    ->then(function ($v) { return ['sql' => '('.$v.')']; })
                                                ->end()
                                                ->children()
                                                    ->scalarNode('sql')->defaultValue('')->end()
                                                ->end()
                                            ->end()
                                            ->variableNode('non_updateable_fields')
                                                ->defaultValue([])
                                            ->end()
                                        ->end()
                                        ->addDefaultsIfNotSet()
                                    ->end()

                                    ->arrayNode('mapping')
                                        ->useAttributeAsKey('name')
                                        ->requiresAtLeastOneElement()
                                        ->isRequired()
                                        ->prototype('array')
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function ($v) { return ['property' => $v]; })
                                            ->end()
                                            ->children()
                                                ->scalarNode('sql')->defaultNull()->end()
                                                ->scalarNode('update_sql')->defaultNull()->end()
                                                ->variableNode('property')
                                                    ->beforeNormalization()
                                                        ->ifString()
                                                        ->then(function ($v) {
                                                            return [$v];
                                                        })
                                                    ->end()
                                                ->end()
                                            ->end()
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;

        return $treeBuilder;
    }

    private function addColumnsNode(string $sectionName): NodeDefinition
    {
        $builder = new TreeBuilder($sectionName);
        $node = $builder->getRootNode();

        $node
            ->useAttributeAsKey('name')
            ->cannotBeEmpty()
            ->prototype('array')
                ->beforeNormalization()
                    ->ifString()
                    ->then(fn ($v) => ['type' => $v, 'options' => ['notnull' => false]])
                ->end()
                ->children()
                    ->scalarNode('type')->defaultValue('string')->end()
                    ->variableNode('options')->defaultValue(['notnull' => false])->end()
                ->end()
            ->end()
        ;

        return $node;
    }
}
