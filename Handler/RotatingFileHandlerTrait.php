<?php

namespace LePhare\Import\Handler;

use Monolog\Handler\RotatingFileHandler;

trait RotatingFileHandlerTrait
{
    /**
     * Override reason: our dateFormat is deprecated by base RotatingFileHandler.
     */
    public function setFilenameFormat(string $filenameFormat, string $dateFormat): RotatingFileHandler
    {
        if (false === strpos($filenameFormat, '{date}')) {
            throw new \InvalidArgumentException('Invalid filename format - format should contain at least `{date}`, because otherwise rotating is impossible.');
        }

        $this->filenameFormat = $filenameFormat;
        $this->dateFormat = $dateFormat;
        $this->url = $this->getTimedFilename();
        $this->close();

        return $this;
    }

    protected function getTimedFilename(): string
    {
        $fileInfo = pathinfo($this->filename);
        $timedFilename = str_replace(
            ['{filename}', '{date}', '{pid}'],
            [$fileInfo['filename'], date($this->dateFormat), getmypid()],
            $fileInfo['dirname'].'/'.$this->filenameFormat
        );

        if (isset($fileInfo['extension'])) {
            $timedFilename .= '.'.$fileInfo['extension'];
        }

        return $timedFilename;
    }

    protected function getGlobPattern(): string
    {
        $fileInfo = pathinfo($this->filename);
        $glob = str_replace(
            ['{filename}', '{date}', '{pid}'],
            [$fileInfo['filename'], '[0-9][0-9][0-9][0-9]*', '[0-9]*'],
            $fileInfo['dirname'].'/'.$this->filenameFormat
        );

        if (isset($fileInfo['extension'])) {
            $glob .= '.'.$fileInfo['extension'];
        }

        return $glob;
    }
}
