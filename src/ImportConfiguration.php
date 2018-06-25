<?php

namespace LePhare\Import;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ImportConfiguration implements ConfigurationInterface
{
    protected $parameters;

    public function __construct(array $parameters = [])
    {
        $this->parameters = $parameters;
    }

    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('import');

        $replaceParameter = function ($string) {
            foreach ($this->parameters as $parameter => $value) {
                if (is_string($value)) {
                    $string = str_replace("%${parameter}%", $value, $string);
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
                ->scalarNode('archive_rotation')->end()
                ->scalarNode('quarantine_rotation')->end()
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
                        ->variableNode('email_from')->isRequired()->end()
                        ->variableNode('recipients')->defaultValue([])->end()
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

                                                        return $delimiter;
                                                    })
                                                ->end()
                                            ->end()
                                            ->scalarNode('quote_character')
                                                ->defaultValue('"')
                                                ->validate()
                                                    ->ifString()
                                                    ->then(function ($v) {
                                                        eval("\$delimiter = \"$v\";");
                                                        return $delimiter;
                                                    })
                                                ->end()
                                            ->end()
                                            ->scalarNode('line_delimiter')
                                                ->defaultValue("\n")
                                                ->validate()
                                                    ->ifString()
                                                    ->then(function ($v) {
                                                        eval("\$delimiter = \"$v\";");

                                                        return $delimiter;
                                                    })
                                                ->end()
                                            ->end()
                                            ->scalarNode('escape_character')
                                                ->defaultValue('\\')
                                                ->validate()
                                                    ->ifString()
                                                    ->then(function ($v) {
                                                        eval("\$delimiter = \"$v\";");

                                                        return $delimiter;
                                                    })
                                                ->end()
                                            ->end()
                                            ->scalarNode('pgsql_format')->defaultValue("csv")->end()
                                        ->end()
                                    ->end()
                                    ->booleanNode('loop')->defaultFalse()->end()
                                    ->scalarNode('strategy')
                                        ->cannotBeEmpty()
                                        ->defaultValue('first_by_name')
                                        ->validate()
                                            ->ifNotInArray(['first_by_name', 'last_by_name'])
                                            ->thenInvalid('Valid strategy are : first_by_name, last_by_name')
                                        ->end()
                                    ->end()
                                    ->append($this->addColumnsNode('fields'))
                                    ->append($this->addColumnsNode('extra_fields'))

                                    ->variableNode('indexes')
                                        ->defaultValue(array())
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
                                            ->scalarNode('conflict_target')->defaultValue('')->end()
                                            ->variableNode('non_updateable_fields')
                                                ->defaultValue(array())
                                            ->end()
                                        ->end()
                                    ->end()

                                    ->arrayNode('mapping')
                                        ->useAttributeAsKey('name')
                                        ->requiresAtLeastOneElement()
                                        ->isRequired()
                                        ->prototype('array')
                                            ->beforeNormalization()
                                                ->ifString()
                                                ->then(function ($v) {
                                                    return array('property'=> $v);
                                                })
                                            ->end()
                                            ->children()
                                                ->scalarNode('sql')->defaultNull()->end()
                                                ->scalarNode('update_sql')->defaultNull()->end()
                                                ->variableNode('property')
                                                    ->beforeNormalization()
                                                        ->ifString()
                                                        ->then(function ($v) {
                                                            return array($v);
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

    public function addColumnsNode($sectionName)
    {
        $builder = new TreeBuilder();
        $node = $builder->root($sectionName);

        $node
            ->useAttributeAsKey('name')
            ->prototype('array')
                ->cannotBeEmpty()
                ->beforeNormalization()
                    ->ifString()
                    ->then(function ($v) {
                        return ['type' => $v, 'options' => ['notnull' => false]];
                    })
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
