<?php

namespace LePhare\Import\Handler;

use Monolog\Handler\RotatingFileHandler as BaseRotatingFileHandler;
use Monolog\Logger;

if (Logger::API === 1) {
    class RotatingFileHandler extends BaseRotatingFileHandler
    {
        use RotatingFileHandlerTrait {
            setFilenameFormat as private typedSetFilenameFormat;
        }

        public function setFilenameFormat($filenameFormat, $dateFormat)
        {
            return $this->typedSetFilenameFormat($filenameFormat, $dateFormat);
        }
    }
} else {
    class RotatingFileHandler extends BaseRotatingFileHandler
    {
        use RotatingFileHandlerTrait;
    }
}
