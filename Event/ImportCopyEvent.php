<?php

namespace LePhare\Import\Event;

use Doctrine\Common\Collections\Collection;
use LePhare\Import\ImportResource;
use Psr\Log\LoggerInterface;

class ImportCopyEvent extends ImportEvent
{
    protected ImportResource $resource;

    public function __construct(Collection $config, ImportResource $resource, LoggerInterface $logger)
    {
        parent::__construct($config, $logger);
        $this->resource = $resource;
    }

    public function getResource(): ImportResource
    {
        return $this->resource;
    }
}
