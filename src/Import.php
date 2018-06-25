<?php

namespace LePhare\Import;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Exception;
use LePhare\Import\Event\ImportCopyEvent;
use LePhare\Import\Event\ImportEvent;
use LePhare\Import\Event\ImportExceptionEvent;
use LePhare\Import\Event\ImportValidateEvent;
use LePhare\Import\Exception\ImportException;
use LePhare\Import\Load\CsvLoader;
use LePhare\Import\Load\ExcelLoader;
use LePhare\Import\Load\TextLoader;
use LePhare\Import\Strategy\StrategyRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Yaml\Yaml;

class Import
{
    protected $loader;
    protected $configuration;
    protected $connection;
    protected $dispatcher;
    protected $logger;
    protected $config;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $dispatcher,
        StrategyRepositoryInterface $strategyRepository,
        ConfigurationInterface $configuration,
        LoggerInterface $logger
    ) {
        $this->configuration = $configuration;
        $this->connection = $connection;
        $this->dispatcher = $dispatcher;
        $this->strategyRepository = $strategyRepository;
        $this->logger = $logger;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function init($file)
    {
        $this->logger->info('Initialize import');

        $config = Yaml::parse(file_get_contents($file));
        $processor = new Processor();
        $this->config = $processor->processConfiguration($this->configuration, ['faros_import' => $config]);

        $resources = [];
        foreach ($this->config['resources'] as $name => $resource) {
            $resources[$name] = new ImportResource($name, $resource);
        }

        $this->config['resources'] = $resources;

        if ($this->dispatcher->hasListeners(ImportEvents::POST_INIT)) {
            $this->logger->debug('Dispatch POST_INIT event');
            $event = new ImportEvent($this->config, $this->logger);
            $this->dispatcher->dispatch(ImportEvents::POST_INIT, $event);
        }
    }

    public function execute($load = true)
    {
        if ($this->dispatcher->hasListeners(ImportEvents::PRE_EXECUTE)) {
            $this->logger->debug('Dispatch PRE_EXECUTE event');
            $event = new ImportEvent($this->config, $this->logger);
            $this->dispatcher->dispatch(ImportEvents::PRE_EXECUTE, $event);
        }

        try {
            if (!$load || $this->load()) {
                $this->copy();
            }

            if ($this->dispatcher->hasListeners(ImportEvents::POST_EXECUTE)) {
                $this->logger->debug('Dispatch POST_EXECUTE event');
                $event = new ImportEvent($this->config, $this->logger);
                $this->dispatcher->dispatch(ImportEvents::POST_EXECUTE, $event);
            }

            $success = true;
        } catch (\Exception $e) {
            $this->logger->critical('An exception occured: '.$e->getMessage());

            if ($this->dispatcher->hasListeners(ImportEvents::EXCEPTION)) {
                $this->logger->debug('Dispatch EXCEPTION event');
                $event = new ImportExceptionEvent($this->config, $e, $this->logger);
                $this->dispatcher->dispatch(ImportEvents::EXCEPTION, $event);
            }

            $success = false;
        }

        return $success;
    }

    public function load()
    {
        $this->logger->info('Load data');

        $loadableResources = array_filter($this->config['resources'], function ($resource) {
            return $resource->isLoadable();
        });

        foreach ($loadableResources as $name => $resource) {
            $this->createTable($resource);
        }

        if ($this->dispatcher->hasListeners(ImportEvents::PRE_LOAD)) {
            $this->logger->debug('Dispatch PRE_LOAD event');
            $event = new ImportEvent($this->config, $this->logger);
            $this->dispatcher->dispatch(ImportEvents::PRE_LOAD, $event);
        }

        $loaded = false;
        foreach ($this->config['resources'] as $name => $resource) {
            if ($resource->isLoadable()) {
                $loaded = $this->loadResource($resource) || $loaded;
            }
        }

        if ($this->dispatcher->hasListeners(ImportEvents::POST_LOAD)) {
            $this->logger->debug('Dispatch POST_LOAD event');
            $event = new ImportEvent($this->config, $this->logger);
            $event = $this->dispatcher->dispatch(ImportEvents::POST_LOAD, $event);
        }

        if (!$loaded) {
            $this->logger->info('Nothing to load');
        }

        return $loaded;
    }

    public function loadResource($resource)
    {
        $loaded = false;

        $files = $resource->getSourceFiles($this->config['source_dir']);

        foreach ($files as $file) {
            if ($this->dispatcher->hasListeners(ImportEvents::VALIDATE_SOURCE)) {
                $this->logger->debug('Dispatch VALIDATE_SOURCE event');
                $event = new ImportValidateEvent($this->config, $resource, $file, $this->logger);
                $event = $this->dispatcher->dispatch(ImportEvents::VALIDATE_SOURCE, $event);

                if (!$event->isValid()) {
                    throw new ImportException(
                        sprintf("The file '%s' is not valid", $file->getBasename())
                    );
                }
            }

            if ($count = $this->loadData($resource, $file)) {
                $this->logger->notice(sprintf('%s: %d lines loaded', $file->getBasename(), $count));
                $loaded = true;
            }
        }

        return $loaded;
    }

    public function copy()
    {
        $this->logger->info('Copy data');

        if ($this->dispatcher->hasListeners(ImportEvents::PRE_COPY)) {
            $this->logger->debug('Dispatch PRE_COPY event.');

            $event = new ImportEvent($this->config, $this->logger);
            $event = $this->dispatcher->dispatch(ImportEvents::PRE_COPY, $event);
        }

        foreach ($this->config['resources'] as $name => $resource) {
            if (!$resource->isCopyable()) {
                continue;
            }

            $strategy = $this
                ->strategyRepository
                ->getStrategy($resource->getCopyStrategy())
            ;

            if (null !== $strategy) {
                try {
                    $lines = $strategy->copy($resource);
                    $this->logger->notice("$name: $lines lines copied");
                } catch (Exception $e) {
                    if (!$e instanceof ImportException) {
                        $this->logger->error("$name: ".$e->getMessage());
                    }

                    throw $e;
                }

                if ($this->dispatcher->hasListeners(ImportEvents::COPY)) {
                    $this->logger->debug('Dispatch COPY event.');

                    $event = new ImportCopyEvent($this->config, $resource, $this->logger);
                    $event = $this->dispatcher->dispatch(ImportEvents::COPY, $event);
                }
            } else {
                throw new ImportException('Unkown import strategy '.$resource->getCopyStrategy());
            }
        }

        if ($this->dispatcher->hasListeners(ImportEvents::POST_COPY)) {
            $this->logger->debug('Dispatch POST_COPY event.');

            $event = new ImportEvent($this->config, $this->logger);
            $event = $this->dispatcher->dispatch(ImportEvents::POST_COPY, $event);
        }
    }

    protected function createTable($resource)
    {
        $connection = $this->connection;

        $platform = $connection->getDatabasePlatform();

        try {
            $connection->executeQuery($platform->getDropTableSQL($resource->getTablename()));
        } catch (DBALException $e) {
            // Do nothing;
        }

        foreach ($platform->getCreateTableSQL($resource->getTable()) as $sql) {
            $connection->executeQuery($sql);
        }
    }

    protected function loadData($resource, $file)
    {
        $connection = $this->connection;

        if ('csv' === $resource->getFormat()) {
            $formatLoader = new CsvLoader($connection);
        } elseif ('text' === $resource->getFormat()) {
            $formatLoader = new TextLoader($connection);
        } elseif ('xls' === $resource->getFormat()) {
            if (!class_exists('PHPExcel_IOFactory')) {
                throw new ImportException('PHPExcel library is missing. Try "composer require phpoffice/phpexcel"');
            }

            $formatLoader = new ExcelLoader($connection);
        } else {
            throw new ImportException($resource->getFormat().' format is not supported');
        }

        return $formatLoader->load($resource, $file);
    }

    public function getConfig()
    {
        return $this->config;
    }
}
