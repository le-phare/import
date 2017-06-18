<?php

namespace LePhare\Import\Event;

use LePhare\Import\ImportResource;

class ImportValidateEvent extends ImportEvent
{
    protected $resource;
    protected $file;
    protected $valid = true;

    public function __construct(array $config, ImportResource $resource, $file, $logger)
    {
        parent::__construct($config, $logger);
        $this->resource = $resource;
        $this->file = $file;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function getFile()
    {
        return $this->file;
    }

    public function setValid($valid)
    {
        $this->valid = (bool) $valid;

        return $this;
    }

    public function isValid()
    {
        return $this->valid;
    }
}
