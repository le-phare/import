<?php

namespace LePhare\Import;

use Behat\Transliterator\Transliterator;
use Doctrine\DBAL\Schema\Table;
use LePhare\Import\Exception\ImportException;
use Symfony\Component\Finder\Finder;

class ImportResource
{
    protected $name;
    protected $config;
    protected $sourceFile;

    public function __construct($name, $config)
    {
        $this->name = $name;
        $this->config = $config;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function isLoadable()
    {
        return isset($this->config['load']);
    }

    public function getTablename()
    {
        return $this->config['tablename'];
    }

    public function getTable()
    {
        $table = new Table($this->getTablename());

        $table->addColumn('file_line_no', 'string', [ 'notnull' => false ]);

        foreach ($this->config['load']['fields'] as $field => $definition) {
            $table->addColumn(Transliterator::urlize($field, '_'), $definition['type'], $definition['options']);
        }

        foreach ($this->config['load']['extra_fields'] as $field => $definition) {
            $table->addColumn(Transliterator::urlize($field, '_'), $definition['type'], $definition['options']);
        }

        foreach ($this->config['load']['indexes'] as $index) {
            $table->addIndex($index);
        }

        return $table;
    }

    public function getCsvFields()
    {
        return $this->config['load']['fields'];
    }

    public function getFieldDelimiter()
    {
        return $this->config['load']['format_options']['field_delimiter'];
    }

    public function getLineDelimiter()
    {
        return $this->config['load']['format_options']['line_delimiter'];
    }

    public function getQuoteCharacter()
    {
        return $this->config['load']['format_options']['quote_character'];
    }

    public function isLoopable()
    {
        return $this->config['load']['loop'];
    }

    public function isCopyable()
    {
        return isset($this->config['copy']);
    }

    public function getCopyStrategy()
    {
        return $this->config['copy']['strategy'];
    }

    public function getMapping()
    {
        return $this->config['copy']['mapping'];
    }

    public function isUpdateableField($field)
    {
        return !isset($this->config['copy']['strategy_options']) ||
            !in_array($field, $this->config['copy']['strategy_options']['non_updateable_fields'])
        ;
    }

    public function getCopyCondition()
    {
        return @$this->config['copy']['strategy_options']['copy_condition'] ?: null;
    }

    public function getJoins()
    {
        return @$this->config['copy']['strategy_options']['joins'] ?: null;
    }

    public function getConflictTarget()
    {
        return @$this->config['copy']['strategy_options']['conflict_target'] ?: null;
    }

    public function isDistinct()
    {
        return (bool) @$this->config['copy']['strategy_options']['distinct'];
    }

    public function getTargetTablename()
    {
        return $this->config['copy']['target'];
    }

    public function getLoadMatchingFiles($dir)
    {
        $pattern = $this->config['load']['pattern'];
        $finder = new Finder();

        $finder = $finder
            ->in($dir)
            ->depth('== 0')
            ->name('#'.$pattern.'#')
            ->sortByName()
        ;

        $files = iterator_to_array($finder);

        return $files;
    }

    public function getSourceFiles($dir)
    {
        if (null !== $this->sourceFile) {
            return $this->sourceFile;
        }

        $files = $this->getLoadMatchingFiles($dir);

        if ('first_by_name' === $this->config['load']['strategy']) {
            ksort($files);
        } elseif ('last_by_name' === $this->config['load']['strategy']) {
            krsort($files);
        } else {
            throw new ImportException('Invalid load strategy');
        }

        if (!$this->config['load']['loop']) {
            $files = array_slice($files, 0, 1);
        }


        return $files;
    }

    public function isArchivable()
    {
        return isset($this->config['load']);
    }

    public function isQuarantinable()
    {
        return isset($this->config['load']) && 'last_by_name' === $this->config['load']['strategy'];
    }

    public function getFormat()
    {
        return $this->config['load']['format'];
    }
}
