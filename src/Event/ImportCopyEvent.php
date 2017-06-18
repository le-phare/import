<?php

namespace LePhare\Import\Event;

use LePhare\Import\ImportResource;

class ImportCopyEvent extends ImportEvent
{
    protected $resource;

    public function __construct(array $config, ImportResource $resource, $logger)
    {
        parent::__construct($config, $logger);
        $this->resource = $resource;
    }

    public function getResource()
    {
        return $this->resource;
    }
}
