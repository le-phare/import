<?php

namespace LePhare\Import\Event;

use Doctrine\Common\Collections\Collection;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\Event;

class ImportEvent extends Event
{
    protected Collection $config;
    protected LoggerInterface $logger;
    protected $logFile;
    protected ?\SplFileInfo $file = null;

    public function __construct(Collection $config, LoggerInterface $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function getConfig(): Collection
    {
        return $this->config;
    }

    public function getLogger(): LoggerInterface
    {
        return $this->logger;
    }

    /** @return string|null */
    public function getLogFile()
    {
        if (null !== $this->logFile) {
            return $this->logFile;
        }

        if (!$this->logger instanceof Logger) {
            return null;
        }

        // Implies that first handler is the one
        $handlers = $this->logger->getHandlers();
        if (null !== ($handler = reset($handlers)) && $handler instanceof StreamHandler) {
            $this->logFile = $handler->getUrl();
        }

        return $this->logFile;
    }

    public function setFile(?\SplFileInfo $file): ImportEvent
    {
        $this->file = $file;

        return $this;
    }

    public function getFile(): ?\SplFileInfo
    {
        return $this->file;
    }
}
