<?php

namespace LePhare\Import\Subscriber;

use LePhare\Import\ImportEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ValidateCSVHeadersSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ImportEvents::VALIDATE_SOURCE => 'onValidateSource',
        ];
    }

    public function onValidateSource($event)
    {
        $config = $event->getResource()->getConfig();
        $logger = $event->getLogger();

        // Only validate CSV files with headers and validation enabled
        if ('csv' !== $config['load']['format']
            || !$config['load']['format_options']['with_header']
            || !$config['load']['format_options']['validate_headers']
        ) {
            return;
        }

        $file = $event->getFile();

        $fh = fopen($file, 'r+');
        $bom = fread($fh, 3);
        if (b"\xEF\xBB\xBF" != $bom) {
            fseek($fh, 0);
        }

        $headers = fgetcsv($fh, 0, $config['load']['format_options']['field_delimiter']);
        fclose($fh);

        $expectedHeaders = array_keys($config['load']['fields']);

        $diff = array_diff($headers, $expectedHeaders);
        if (count($diff) > 0) {
            $event->setValid(false);
            $logger->warning(
                sprintf('Unexpected columns : %s - %s', $file->getBasename(), implode(', ', $diff))
            );
        }

        $diff = array_diff($expectedHeaders, $headers);
        if (count($diff) > 0) {
            $event->setValid(false);
            $logger->warning(
                sprintf('Expected columns not found : %s - %s', $file->getBasename(), implode(', ', $diff))
            );
        }
    }
}
