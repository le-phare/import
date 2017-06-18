<?php

namespace LePhare\Import\Event;

use Symfony\Component\EventDispatcher\Event;

class ImportEvent extends Event
{
    protected $config;
    protected $logger;
    protected $logFile;

    public function __construct(array $config, $logger)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function getLogFile()
    {
        if (null !== $this->logFile) {
            return $this->logFile;
        }

        // Implies that first handler is the one
        $handlers = $this->logger->getHandlers();
        if (null !== $handler = reset($handlers)) {
            $this->logFile = $handler->getUrl();
        }

        return $this->logFile;
    }
}
