<?php

namespace LePhare\Import\Load;

use Doctrine\DBAL\Connection;
use LePhare\Import\Report\Writer\CSV;

class ExcelLoader extends CsvLoader
{
    protected $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function load($resource, $file)
    {
        $excel = @\PHPExcel_IOFactory::load($file);

        $writer = new CSV($excel);
        $writer->setDelimiter($resource->getFieldDelimiter());
        $writer->setLineEnding($resource->getLineDelimiter());
        \PHPExcel_Shared_String::setDecimalSeparator('.');
        \PHPExcel_Shared_String::setThousandsSeparator('');
        $writer->save($file.'.csv');

        $loaded = parent::load($resource, $file.'.csv');

        unlink($file.'.csv');

        return $loaded;
    }
}
