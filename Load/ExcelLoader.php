<?php

namespace LePhare\Import\Load;

use LePhare\Import\Exception\ImportException;
use LePhare\Import\ImportResource;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;
use PhpOffice\PhpSpreadsheet\Writer\Csv;

/** @api */
class ExcelLoader extends CsvLoader
{
    public function supports(ImportResource $resource, array $context): bool
    {
        if ('xls' !== $resource->getFormat() || !isset($context[LoaderInterface::FILE])) {
            return false;
        }

        if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
            throw new ImportException('PhpSpreadsheet library is missing. Try "composer require phpoffice/phpspreadsheet"');
        }

        return true;
    }

    public function load(ImportResource $resource, array $context): int
    {
        $file = $context[LoaderInterface::FILE];
        $excel = IOFactory::load($file);

        $writer = new Csv($excel);
        $writer->setDelimiter($resource->getFieldDelimiter());
        $writer->setLineEnding($resource->getLineDelimiter());
        StringHelper::setDecimalSeparator('.');
        StringHelper::setThousandsSeparator('');
        $writer->save($file.'.csv');

        $loaded = parent::load($resource, [
            LoaderInterface::FILE => $file.'.csv',
        ]);

        unlink($file.'.csv');

        return $loaded;
    }
}
