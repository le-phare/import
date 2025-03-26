<?php

namespace LePhare\Import\Subscriber;

use Doctrine\Common\Collections\Collection;
use LePhare\Import\Event\ImportEvent;
use LePhare\Import\ImportEvents;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Finder\Finder;

class ArchiveAndQuarantineSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ImportEvents::POST_COPY => ['onPostCopy', -128],  // Ultra low priority
            ImportEvents::EXCEPTION => ['onException', -128], // Ultra low priority
        ];
    }

    public function onPostCopy(ImportEvent $event): void
    {
        $config = $event->getConfig();
        $logger = $event->getLogger();
        $date = date('Ymd-His');

        if ($config['archive']['enabled']) {
            $this->archive($date, $config, $logger);
        }
    }

    public function onException(ImportEvent $event): void
    {
        $config = $event->getConfig();
        $logger = $event->getLogger();
        $date = date('Ymd-His');

        if ($config['quarantine']['enabled']) {
            $this->quarantine($date, $config, $logger);
        }
    }

    protected function archive(string $date, Collection $config, LoggerInterface $logger): void
    {
        $archivableResources = array_filter($config['resources'], fn ($resource) => $resource->isArchivable());

        if ([] === (array) $archivableResources) {
            return;
        }

        $logger->info('Archive files');

        $archiveDirectory = $config['archive']['dir'] ?: $config['source_dir'].'/archives';
        $archiveDirectory .= '/'.$date;

        $archived = 0;

        foreach ($archivableResources as $name => $resource) {
            $archivableFiles = $resource->getSourceFiles($config['source_dir']);

            foreach ($archivableFiles as $archivableFile) {
                $sourceDir = basename(dirname($archivableFile->getRealPath()));
                $archiveDest = "{$archiveDirectory}/{$sourceDir}/".$archivableFile->getBasename();

                if (!is_dir("{$archiveDirectory}/{$sourceDir}/")) {
                    mkdir("{$archiveDirectory}/{$sourceDir}/", 0777, true);
                }

                if (!rename($archivableFile->getRealPath(), $archiveDest)) {
                    throw new \RuntimeException(sprintf('Unable to archive file %s', $archivableFile->getRealPath()));
                }
                $logger->debug(sprintf('File %s successfully archived', $archivableFile->getBasename()));

                ++$archived;
            }
        }

        $rotation = $config['archive']['rotation'];

        $this->rotate(dirname($archiveDirectory), $rotation, $logger);
    }

    protected function quarantine(string $date, Collection $config, LoggerInterface $logger): void
    {
        $quarantinableResources = array_filter($config['resources'], fn ($resource) => $resource->isQuarantinable());

        if ([] === (array) $quarantinableResources) {
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
                $quarantineDest = "{$quarantineDirectory}/{$sourceDir}/".$quarantinableFile->getBasename();

                if (!is_dir("{$quarantineDirectory}/{$sourceDir}/")) {
                    mkdir("{$quarantineDirectory}/{$sourceDir}/", 0777, true);
                }

                if (!rename($quarantinableFile->getRealPath(), $quarantineDest)) {
                    throw new \RuntimeException(sprintf('Unable to quarantine file %s', $quarantinableFile->getRealPath()));
                }
                $logger->warning(sprintf('Extra file found. Put %s into quarantine.', $quarantinableFile->getBasename()));

                ++$quarantined;
            }
        }

        $rotation = $config['quarantine']['rotation'];

        $this->rotate(dirname($quarantineDirectory), $rotation, $logger);
    }

    protected function rotate(string $dir, int $rotation, LoggerInterface $logger): void
    {
        $finder = new Finder();

        $finder = $finder
            ->directories()
            ->in($dir)
            ->depth('== 0')
            ->name('#^[0-9]{8}\-[0-9]{6}$#')
            ->sortByName()
        ;

        $hasError = false;

        while ($finder->count() > $rotation && !$hasError) {
            $directory = $finder->getIterator()->current();

            if (!$this->removeDir($directory->getRealPath())) {
                $logger->warning(sprintf('Deleting directory %s failed !', $directory->getRealPath()));
                $hasError = true;
            }
        }
    }

    /**
     * Remove a directory and its content.
     */
    private function removeDir(string $dir): bool
    {
        $files = array_diff(scandir($dir), ['.', '..']);

        foreach ($files as $file) {
            (is_dir("{$dir}/{$file}")) ? $this->removeDir("{$dir}/{$file}") : unlink("{$dir}/{$file}");
        }

        return rmdir($dir);
    }
}
