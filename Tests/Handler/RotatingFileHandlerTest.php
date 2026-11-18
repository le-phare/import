<?php

namespace LePhare\Import\Tests\Handler;

use LePhare\Import\Handler\RotatingFileHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

/**
 * @covers \LePhare\Import\Handler\RotatingFileHandler
 *
 * @internal
 */
class RotatingFileHandlerTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/lephare-import-'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->dir.'/*') ?: []);
        rmdir($this->dir);
    }

    public function testWritesToTheDatedFile(): void
    {
        $handler = new RotatingFileHandler($this->dir.'/import.log', 30, Logger::INFO);
        $logger = new Logger('import', [$handler]);

        $logger->info('Load data');
        $handler->close();

        $file = $this->dir.'/import-'.date('Y-m-d').'.log';
        $this->assertFileExists($file);
        $this->assertStringContainsString('Load data', (string) file_get_contents($file));
    }
}
