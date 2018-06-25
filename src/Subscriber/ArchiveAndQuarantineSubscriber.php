<?php

namespace LePhare\Import\Subscriber;

use LePhare\Import\ImportEvents;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;

class ArchiveAndQuarantineSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ImportEvents::POST_COPY => ['onPostCopy', -128], // Ultra low priority
        ];
    }

    public function onPostCopy(Event $event)
    {
        $config = $event->getConfig();
        $logger = $event->getLogger();
        $date = date('Ymd-His');

        if ($config['archive']['enabled']) {
            $this->archive($date, $config, $logger);
        }
        if ($config['quarantine']['enabled']) {
            $this->quarantine($date, $config, $logger);
        }
    }

    public function archive($date, $config, $logger)
    {
        $archivableResources = array_filter($config['resources'], function ($resource) {
            return $resource->isArchivable();
        });

        if (0 === count($archivableResources)) {
            return;
        }

        $logger->info('Archive files');

        $archiveDirectory = $config['archive']['dir'] ?: $config['source_dir'].'/archives';
        $archiveDirectory .= '/'.$date;

        $archived = 0;

        foreach ($archivableResources as $name => $resource) {
            $sourceFiles = $resource->getSourceFiles($config['source_dir']);

            foreach ($sourceFiles as $sourceFile) {
                $sourceDir = basename(dirname($sourceFile->getRealPath()));
                $archiveDest = "$archiveDirectory/$sourceDir/".$sourceFile->getBasename();

                if (!is_dir("$archiveDirectory/$sourceDir/")) {
                    mkdir("$archiveDirectory/$sourceDir/", 0777, true);
                }

                if (!rename($sourceFile->getRealPath(), $archiveDest)) {
                    throw new \RuntimeException(
                        sprintf('Unable to archive file %s', $sourceFile->getRealPath(), $archiveDest)
                    );
                } else {
                    $logger->debug(sprintf('File %s successfully archived', $sourceFile->getBasename()));
                }

                ++$archived;
            }
        }

        if (isset($this->config['archive_rotation'])) {
            @trigger_error('archive_rotation is deprecated. Please use archive.rotation instead.', E_USER_DEPRECATED);
            $rotation = $this->config['archive_rotation'];
        } else {
            $rotation = $config['archive']['rotation'];
        }

        $this->rotate(dirname($archiveDirectory), $rotation, $logger);
    }

    public function quarantine($date, $config, $logger)
    {
        $quarantinableResources = array_filter($config['resources'], function ($resource) {
            return $resource->isQuarantinable();
        });

        if (0 === count($quarantinableResources)) {
            return;
        }

        $quarantineDirectory = $config['quarantine']['dir'] ?: $config['source_dir'].'/quarantine';
        $quarantineDirectory .= '/'.$date;

        $quarantined = 0;

        foreach ($quarantinableResources as $name => $resource) {
            // List pattern matching files that must not be imported anymore
            $quarantinableFiles = $resource->getLoadMatchingFiles($config['source_dir']);

            foreach ($quarantinableFiles as $quarantinableFile) {
                $sourceDir = basename(dirname($quarantinableFile->getRealPath()));
                $quarantineDest = "$quarantineDirectory/$sourceDir/".$quarantinableFile->getBasename();

                if (!is_dir("$quarantineDirectory/$sourceDir/")) {
                    mkdir("$quarantineDirectory/$sourceDir/", 0777, true);
                }

                if (!rename($quarantinableFile->getRealPath(), $quarantineDest)) {
                    throw new \RuntimeException(
                        sprintf('Unable to quarantine file %s', $quarantinableFile->getRealPath(), $quarantineDest)
                    );
                } else {
                    $logger->warning(sprintf('Extra file found. Put %s into quarantine.', $quarantinableFile->getBasename()));
                }

                ++$quarantined;
            }
        }

        if (isset($this->config['quarantine_rotation'])) {
            @trigger_error('quarantine_rotation is deprecated. Please use quarantine.rotation instead.', E_USER_DEPRECATED);
            $rotation = $this->config['quarantine_rotation'];
        } else {
            $rotation = $config['quarantine']['rotation'];
        }

        $this->rotate(dirname($quarantineDirectory), $rotation, $logger);
    }

    protected function rotate($dir, $rotation, $logger)
    {
        $finder = new Finder();

        $finder = $finder
            ->directories()
            ->in($dir)
            ->depth('== 0')
            ->name('#^[0-9]{8}\-[0-9]{6}$#')
            ->sortByName()
        ;

        while ($finder->count() > $rotation) {
            $directory = $finder->getIterator()->current();

            if (!$this->removeDir($directory->getRealPath())) {
                $logger->warning(sprintf('Deleting directory %s faild !', $directory->getRealPath()));
            }
        }
    }

    /**
     * Remove a directory and its content
     *
     * @param  string $dir
     *
     * @return bool
     */
    protected function removeDir($dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->removeDir("$dir/$file") : unlink("$dir/$file");
        }

        return rmdir($dir);
    }
}
