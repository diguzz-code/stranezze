<?php
declare(strict_types=1);

namespace Stranezze\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\Test;
use Stranezze\Tests\TestCase;

final class DatabaseFactoryTest extends TestCase
{
    #[Test]
    public function itCreatesSqliteWithExpectedPragmas(): void
    {
        $pdo = $this->createTemporaryDatabase();

        self::assertSame('wal', strtolower((string)$pdo->query('PRAGMA journal_mode')->fetchColumn()));
        self::assertSame(5000, (int)$pdo->query('PRAGMA busy_timeout')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('PRAGMA synchronous')->fetchColumn());
        self::assertSame(1, (int)$pdo->query('PRAGMA foreign_keys')->fetchColumn());
    }
}