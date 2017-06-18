<?php

namespace LePhare\Import\Event;

use Exception;

class ImportExceptionEvent extends ImportEvent
{
    protected $exception;

    public function __construct(array $config, Exception $exception, $logger)
    {
        parent::__construct($config, $logger);
        $this->exception = $exception;
    }

    public function getException()
    {
        return $this->exception;
    }
}
