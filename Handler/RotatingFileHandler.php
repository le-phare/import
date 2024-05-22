<?php

namespace LePhare\Import\Handler;

use Monolog\Handler\RotatingFileHandler as BaseRotatingFileHandler;

class RotatingFileHandler extends BaseRotatingFileHandler
{
    use RotatingFileHandlerTrait;
}
