<?php

namespace LePhare\Import\Event;

use Doctrine\Common\Collections\Collection;
use Psr\Log\LoggerInterface;

class ImportExceptionEvent extends ImportEvent
{
    protected \Throwable $throwable;

    public function __construct(Collection $config, \Throwable $throwable, LoggerInterface $logger, \SplFileInfo $file)
    {
        parent::__construct($config, $logger);
        $this->throwable = $throwable;
        $this->setFile($file);
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }
}
