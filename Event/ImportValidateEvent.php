<?php

namespace LePhare\Import\Event;

use Doctrine\Common\Collections\Collection;
use LePhare\Import\ImportResource;
use Psr\Log\LoggerInterface;

class ImportValidateEvent extends ImportEvent
{
    protected ImportResource $resource;
    protected \SplFileInfo $file;
    protected bool $valid = true;

    public function __construct(Collection $config, ImportResource $resource, \SplFileInfo $file, LoggerInterface $logger)
    {
        parent::__construct($config, $logger);
        $this->resource = $resource;
        $this->file = $file;
    }

    public function getResource(): ImportResource
    {
        return $this->resource;
    }

    public function getFile(): \SplFileInfo
    {
        return $this->file;
    }

    public function setValid($valid): self
    {
        $this->valid = (bool) $valid;

        return $this;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }
}
