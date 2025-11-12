<?php

namespace LePhare\Import;

use Doctrine\DBAL\Schema\Table;
use LePhare\Import\Exception\ImportException;
use LePhare\Import\LoadStrategy\LoadStrategyInterface;
use LePhare\Import\Util\Transliterator;
use Symfony\Component\Finder\Finder;

class ImportResource
{
    private bool $readOnly = false;
    private bool $isSharedConnection = false;
    protected string $name;
    protected array $config;
    protected ?LoadStrategyInterface $loadStrategy = null;

    public function __construct(string $name, array $config)
    {
        $this->name = $name;
        $this->config = $config;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @throws ImportException if the resource is marked as read-only
     */
    public function setConfig(array $config)
    {
        if ($this->readOnly) {
            throw new ImportException('Resource is read-only');
        }

        $this->config = $config;

        return $this;
    }

    public function setReadOnly(): self
    {
        $this->readOnly = true;

        return $this;
    }

    public function setLoadStrategy(LoadStrategyInterface $loadStrategy)
    {
        $this->loadStrategy = $loadStrategy;

        return $this;
    }

    public function setSharedConnection(bool $isSharedConnection): self
    {
        $this->isSharedConnection = $isSharedConnection;

        return $this;
    }

    public function isSharedConnection(): bool
    {
        return $this->isSharedConnection;
    }

    public function isLoadable(): bool
    {
        return isset($this->config['load']);
    }

    public function getTablename(): string
    {
        return $this->config['tablename'];
    }

    public function getTable(): Table
    {
        $table = new Table($this->getTablename());
        $table->addColumn('file_line_no', 'string', ['notnull' => false]);

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

    public function getCsvFields(): array
    {
        return $this->config['load']['fields'];
    }

    public function getAddLineNumber(): bool
    {
        return $this->config['load']['add_file_line_number'];
    }

    public function getFieldDelimiter(): string
    {
        return $this->config['load']['format_options']['field_delimiter'];
    }

    public function getLineDelimiter(): string
    {
        return $this->config['load']['format_options']['line_delimiter'];
    }

    public function getQuoteCharacter(): string
    {
        return $this->config['load']['format_options']['quote_character'];
    }

    public function getEscapeCharacter(): string
    {
        return $this->config['load']['format_options']['escape_character'];
    }

    public function getNullString(): string
    {
        return $this->config['load']['format_options']['null_string'];
    }

    public function isLoopable(): bool
    {
        return $this->config['load']['loop'];
    }

    public function isCopyable(): bool
    {
        return isset($this->config['copy']);
    }

    public function getCopyStrategy(): string
    {
        return $this->config['copy']['strategy'];
    }

    public function getMapping(): array
    {
        return $this->config['copy']['mapping'];
    }

    public function isUpdateableField($field): bool
    {
        return !isset($this->config['copy']['strategy_options'])
            || !in_array($field, $this->config['copy']['strategy_options']['non_updateable_fields']);
    }

    public function getCopyCondition(): ?string
    {
        return $this->config['copy']['strategy_options']['copy_condition'] ?: null;
    }

    public function getJoins(): ?string
    {
        return $this->config['copy']['strategy_options']['joins'] ?: null;
    }

    public function getConflictTarget(): ?string
    {
        return $this->config['copy']['strategy_options']['conflict_target']['sql'] ?: null;
    }

    public function isDistinct(): bool
    {
        return $this->config['copy']['strategy_options']['distinct'];
    }

    public function getTargetTablename(): string
    {
        return $this->config['copy']['target'];
    }

    public function getLoadMatchingFiles(string $dir): array
    {
        $pattern = $this->config['load']['pattern'];
        $finder = new Finder();

        $finder = $finder
            ->in($dir)
            ->depth('== 0')
            ->name('#'.$pattern.'#')
            ->sortByName()
        ;

        $files = iterator_to_array($finder->files());

        if (null !== $this->loadStrategy) {
            $this->loadStrategy->sort($files);
        }

        return $files;
    }

    public function getSourceFiles(string $dir): array
    {
        $files = $this->getLoadMatchingFiles($dir);

        if (!$this->config['load']['loop']) {
            $files = array_slice($files, 0, 1);
        }

        return $files;
    }

    public function isArchivable(): bool
    {
        return isset($this->config['load']);
    }

    public function isQuarantinable(): bool
    {
        return isset($this->config['load']);
    }

    public function getFormat(): string
    {
        return $this->config['load']['format'];
    }

    public function getFailIfNotLoaded(): bool
    {
        return $this->config['load']['fail_if_not_loaded'] ?? false;
    }
}
