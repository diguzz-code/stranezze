<?php
declare(strict_types=1);

namespace Stranezze\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Stranezze\Infrastructure\Logger;
use Stranezze\Tests\TestCase;

final class LoggerTest extends TestCase
{
    #[Test]
    public function itWritesMonolineLogEntries(): void
    {
        $directory = $this->temporaryDirectory();
        $logger = Logger::create($directory);
        $logger->info('Evento di test', ['value' => 'ok']);

        $files = glob($directory . '/app-*.log');
        self::assertNotFalse($files);
        self::assertCount(1, $files);
        $contents = file_get_contents($files[0]);
        self::assertIsString($contents);
        self::assertStringContainsString('INFO [app] Evento di test', $contents);
        self::assertStringContainsString('"value":"ok"', $contents);
        self::assertSame(1, substr_count($contents, PHP_EOL));
    }

    #[Test]
    public function itFailsSafeWhenTheLogDirectoryIsUnavailable(): void
    {
        $parent = $this->temporaryDirectory();
        $file = $parent . '/not-a-directory';
        file_put_contents($file, 'occupied');

        $logger = Logger::create($file . '/logs');
        $logger->error('Questo non deve interrompere il test', [
            'exception' => new RuntimeException('errore di test'),
        ]);

        self::assertFileDoesNotExist($file . '/logs/app-' . date('Y-m-d') . '.log');
    }
}