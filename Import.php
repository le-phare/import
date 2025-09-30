<?php

namespace LePhare\Import;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use LePhare\Import\Event\ImportCopyEvent;
use LePhare\Import\Event\ImportEvent;
use LePhare\Import\Event\ImportExceptionEvent;
use LePhare\Import\Event\ImportValidateEvent;
use LePhare\Import\Exception\ImportException;
use LePhare\Import\Exception\LowLevelException;
use LePhare\Import\Exception\ResourceNotLoadedException;
use LePhare\Import\Load\LoaderInterface;
use LePhare\Import\LoadStrategy\LoadStrategyInterface;
use LePhare\Import\LoadStrategy\LoadStrategyRepositoryInterface;
use LePhare\Import\Strategy\StrategyRepositoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class Import implements ImportInterface
{
    protected ConfigurationInterface $configuration;
    protected Connection $connection;
    protected EventDispatcherInterface $dispatcher;
    protected LoggerInterface $logger;
    protected Collection $config;
    protected StrategyRepositoryInterface $strategyRepository;
    protected LoadStrategyRepositoryInterface $loadStrategyRepository;
    protected ?\SplFileInfo $file = null;

    /** @var ArrayCollection<int, LoaderInterface> */
    private ArrayCollection $loaders;

    public function __construct(
        Connection $connection,
        EventDispatcherInterface $dispatcher,
        StrategyRepositoryInterface $strategyRepository,
        LoadStrategyRepositoryInterface $loadStrategyRepository,
        ConfigurationInterface $configuration,
        ?LoggerInterface $logger = null
    ) {
        $this->configuration = $configuration;
        $this->connection = $connection;
        $this->dispatcher = $dispatcher;
        $this->strategyRepository = $strategyRepository;
        $this->loadStrategyRepository = $loadStrategyRepository;
        $this->logger = $logger ?? new NullLogger();
        $this->loaders = new ArrayCollection();
    }

    public function addLoader(LoaderInterface $loader): void
    {
        $this->loaders->add($loader);
    }

    public function init(array $config): void
    {
        $this->logger->info('Initialize import');

        $processor = new Processor();
        $this->config = new ArrayCollection($processor->processConfiguration($this->configuration, ['lephare_import' => $config]));

        $resources = [];
        foreach ($this->config['resources'] as $name => $resource) {
            $resource = new ImportResource($name, $resource);
            if ($resource->isLoadable()) {
                /** @var LoadStrategyInterface|null $loadStrategy */
                $loadStrategy = $this->loadStrategyRepository->getLoadStrategy($resource->getConfig()['load']['strategy']);

                if (null !== $loadStrategy) {
                    $resource->setLoadStrategy($loadStrategy);
                } else {
                    $availableStrategiesKey = array_keys($this->loadStrategyRepository->getLoadStrategies());

                    throw new ImportException('Invalid load strategy. Available load strategies are : '.implode(', ', $availableStrategiesKey));
                }
            }
            $resources[$name] = $resource;
        }

        $this->config['resources'] = $resources;

        if ($this->dispatcher->hasListeners(ImportEvents::POST_INIT)) {
            $this->logger->debug('Dispatch POST_INIT event');
            $event = new ImportEvent($this->config, $this->logger);
            $this->dispatcher->dispatch($event, ImportEvents::POST_INIT);
        }

        // Set every resource read-only
        $this->config['resources'] = array_map(fn ($resource) => $resource->setReadOnly(), $this->config['resources']);
    }

    /**
     * @throws LowLevelException If an error occurs during SQL part of the import
     */
    public function execute(bool $load = true): bool
    {
        if ($this->dispatcher->hasListeners(ImportEvents::PRE_EXECUTE)) {
            $this->logger->debug('Dispatch PRE_EXECUTE event');
            $event = new ImportEvent($this->config, $this->logger);
            $this->dispatcher->dispatch($event, ImportEvents::PRE_EXECUTE);
        }

        try {
            if (!$load || $this->load()) {
                $this->copy();
            }

            if ($this->dispatcher->hasListeners(ImportEvents::POST_EXECUTE)) {
                $this->logger->debug('Dispatch POST_EXECUTE event');
                $event = new ImportEvent($this->config, $this->logger);
                $this->dispatcher->dispatch($event, ImportEvents::POST_EXECUTE);
            }

            $success = true;
        } catch (\ErrorException $e) {
            $this->dispatchException($e);

            throw new LowLevelException($e->getMessage(), $e->getCode(), $e);
        } catch (\Exception $e) {
            $this->logger->critical('An exception occurred: {message}', [
                'exception' => $e,
                'message' => $e->getMessage(),
            ]);
            $this->dispatchException($e);

            $success = false;
        }

        return $success;
    }

    private function dispatchException(\Throwable $throwable): void
    {
        if ($this->dispatcher->hasListeners(ImportEvents::EXCEPTION)) {
            $this->logger->debug('Dispatch EXCEPTION event');
            $event = new ImportExceptionEvent($this->config, $throwable, $this->logger, $this->file);
            $this->dispatcher->dispatch($event, ImportEvents::EXCEPTION);
        }
    }

    public function load(): bool
    {
        $this->logger->info('Load data');

        $loadableResources = array_filter($this->config['resources'], fn ($resource) => $resource->isLoadable());

        foreach ($loadableResources as $resource) {
            $this->createTable($resource);
        }

        if ($this->dispatcher->hasListeners(ImportEvents::PRE_LOAD)) {
            $this->logger->debug('Dispatch PRE_LOAD event');
            $event = new ImportEvent($this->config, $this->logger);
            $this->dispatcher->dispatch($event, ImportEvents::PRE_LOAD);
        }

        $loaded = false;
        foreach ($this->config['resources'] as $resource) {
            if ($resource->isLoadable()) {
                $resourceLoaded = $this->loadResource($resource);

                if (!$resourceLoaded && $resource->getFailIfNotLoaded()) {
                    throw new ResourceNotLoadedException(sprintf('Resource "%s" is missing', $resource->getName()));
                }

                $loaded = $resourceLoaded || $loaded;
            }
        }

        if ($this->dispatcher->hasListeners(ImportEvents::POST_LOAD)) {
            $this->logger->debug('Dispatch POST_LOAD event');
            $event = new ImportEvent($this->config, $this->logger);
            $this->dispatcher->dispatch($event, ImportEvents::POST_LOAD);
        }

        if (!$loaded) {
            $this->logger->info('Nothing to load');
        }

        return $loaded;
    }

    private function loadResource(ImportResource $resource): bool
    {
        $loaded = false;

        $files = $resource->getSourceFiles($this->config['source_dir']);

        foreach ($files as $file) {
            $this->file = $file;
            $this->logger->debug('load resource {file}', [
                'file' => $file->getBasename(),
                'path' => $file->getPath(),
            ]);
            if ($this->dispatcher->hasListeners(ImportEvents::VALIDATE_SOURCE)) {
                $this->logger->debug('Dispatch VALIDATE_SOURCE event');
                $event = new ImportValidateEvent($this->config, $resource, $file, $this->logger);
                $event = $this->dispatcher->dispatch($event, ImportEvents::VALIDATE_SOURCE);

                if (!$event->isValid()) {
                    throw new ImportException(sprintf("The file '%s' is not valid", $file->getPathname()));
                }
            }

            if (($count = $this->loadData($resource, $file)) !== 0) {
                $this->logger->notice('{file}: {count} lines loaded', [
                    'file' => $file->getBasename(),
                    'count' => $count,
                ]);
                $loaded = true;
            }
        }

        return $loaded;
    }

    public function copy(): void
    {
        $this->logger->info('Copy data');

        if ($this->dispatcher->hasListeners(ImportEvents::PRE_COPY)) {
            $this->logger->debug('Dispatch PRE_COPY event.');

            $event = new ImportEvent($this->config, $this->logger);
            $event = $this->dispatcher->dispatch($event, ImportEvents::PRE_COPY);
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
                    $this->logger->notice("{$name}: {$lines} lines copied");
                } catch (\Exception $e) {
                    if (!$e instanceof ImportException) {
                        $this->logger->error('{name}: {message}', [
                            'name' => $name,
                            'message' => $e->getMessage(),
                        ]);
                    }

                    throw $e;
                }

                if ($this->dispatcher->hasListeners(ImportEvents::COPY)) {
                    $this->logger->debug('Dispatch COPY event.');

                    $event = new ImportCopyEvent($this->config, $resource, $this->logger);
                    $event = $this->dispatcher->dispatch($event, ImportEvents::COPY);
                }
            } else {
                throw new ImportException('Unknown import strategy '.$resource->getCopyStrategy());
            }
        }

        if ($this->dispatcher->hasListeners(ImportEvents::POST_COPY)) {
            $this->logger->debug('Dispatch POST_COPY event.');

            $event = new ImportEvent($this->config, $this->logger);
            $event = $this->dispatcher->dispatch($event, ImportEvents::POST_COPY);
        }
    }

    private function createTable(ImportResource $resource): void
    {
        $connection = $this->connection;

        $platform = $connection->getDatabasePlatform();

        if ($platform->supportsSchemas() && false !== strpos($resource->getTablename(), '.')) {
            [$schema, $table] = explode('.', $resource->getTablename());
            $connection->executeQuery("CREATE SCHEMA IF NOT EXISTS {$schema}");
        }

        try {
            $connection->executeQuery($platform->getDropTableSQL($resource->getTablename()));
        } catch (DBALException|\Exception $e) {
            // Do nothing
        }

        foreach ($platform->getCreateTableSQL($resource->getTable()) as $sql) {
            $connection->executeQuery($sql);
        }
    }

    private function loadData(ImportResource $resource, string $file): int
    {
        $context = [LoaderInterface::FILE => $file];
        foreach ($this->loaders as $loader) {
            if ($loader->supports($resource, $context)) {
                return $loader->load($resource, $context);
            }
        }

        throw new ImportException($resource->getFormat().' format is not supported');
    }

    public function getConfig(): Collection
    {
        return $this->config;
    }
}
