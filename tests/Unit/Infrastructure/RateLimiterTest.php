<?php
declare(strict_types=1);

namespace Stranezze\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\Test;
use Stranezze\Infrastructure\Logger;
use Stranezze\Infrastructure\RateLimiter;
use Stranezze\Tests\TestCase;

final class RateLimiterTest extends TestCase
{
    #[Test]
    public function itBlocksAfterFiveFailures(): void
    {
        $pdo = $this->createTemporaryDatabase();
        $limiter = new RateLimiter($pdo, Logger::create($this->temporaryDirectory()));

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $limiter->recordFailure('127.0.0.1', 'alice');
        }

        self::assertGreaterThan(0, $limiter->retryAfter('127.0.0.1', 'alice'));
    }

    #[Test]
    public function successResetsFailures(): void
    {
        $pdo = $this->createTemporaryDatabase();
        $limiter = new RateLimiter($pdo, Logger::create($this->temporaryDirectory()));
        $limiter->recordFailure('127.0.0.1', 'alice');
        $limiter->recordFailure('127.0.0.1', 'alice');

        $limiter->recordSuccess('127.0.0.1', 'alice');

        self::assertSame(0, $limiter->retryAfter('127.0.0.1', 'alice'));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM login_attempts WHERE success = 1')->fetchColumn());
    }

    #[Test]
    public function itCleansUpOldEventsBeforeCounting(): void
    {
        $pdo = $this->createTemporaryDatabase();
        $limiter = new RateLimiter($pdo, Logger::create($this->temporaryDirectory()));
        $insert = $pdo->prepare(
            "INSERT INTO login_attempts (ip, username, attempted_at, success) VALUES (:ip, :username, datetime('now', '-2 hours'), 0)"
        );
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $insert->execute(['ip' => '127.0.0.1', 'username' => 'alice']);
        }
        $limiter->recordFailure('127.0.0.1', 'alice');

        self::assertSame(0, $limiter->retryAfter('127.0.0.1', 'alice'));
        self::assertSame(1, (int)$pdo->query('SELECT COUNT(*) FROM login_attempts')->fetchColumn());
    }
}