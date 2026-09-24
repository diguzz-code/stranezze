<?php
declare(strict_types=1);

namespace Stranezze\Tests;

use PDO;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;
use Stranezze\Infrastructure\DatabaseFactory;

abstract class TestCase extends PHPUnitTestCase
{
    /** @var list<string> */
    private array $temporaryDatabasePaths = [];

    protected function createTemporaryDatabase(): PDO
    {
        $path = tempnam(sys_get_temp_dir(), 'stranezze-test-');
        if ($path === false) {
            self::fail('Impossibile creare il database temporaneo.');
        }

        $this->temporaryDatabasePaths[] = $path;
        $pdo = DatabaseFactory::create($path);
        static::createSchema($pdo);

        return $pdo;
    }

    protected static function createSchema(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
CREATE TABLE observations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT NOT NULL CHECK (length(title) BETWEEN 1 AND 80),
    content TEXT NOT NULL CHECK (length(content) BETWEEN 1 AND 500),
    category TEXT NOT NULL CHECK (category IN ('quotidiana', 'natura', 'persone', 'tecnologia', 'altro')),
    observed_on TEXT NOT NULL,
    place TEXT NOT NULL DEFAULT '',
    is_favorite INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
)
SQL);
        $pdo->exec('CREATE INDEX idx_observations_observed_on ON observations(observed_on DESC)');
        $pdo->exec('CREATE INDEX idx_observations_category ON observations(category)');
        $pdo->exec(<<<'SQL'
CREATE TABLE users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT NOT NULL UNIQUE CHECK (length(username) BETWEEN 1 AND 80),
    password_hash TEXT NOT NULL CHECK (length(password_hash) BETWEEN 1 AND 255),
    role TEXT NOT NULL DEFAULT 'user' CHECK (role IN ('admin', 'user')),
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_login_at TEXT NULL
)
SQL);
        $pdo->exec(<<<'SQL'
CREATE TABLE login_attempts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    ip TEXT NOT NULL CHECK (length(ip) BETWEEN 1 AND 45),
    username TEXT NOT NULL CHECK (length(username) BETWEEN 1 AND 80),
    attempted_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
    success INTEGER NOT NULL CHECK (success IN (0, 1))
)
SQL);
        $pdo->exec('CREATE INDEX idx_login_attempts_ip_attempted_at ON login_attempts (ip, attempted_at)');
        $pdo->exec('CREATE INDEX idx_login_attempts_username_attempted_at ON login_attempts (username, attempted_at)');
    }

    protected function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stranezze-test-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700, true);
        return $directory;
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDatabasePaths as $path) {
            foreach ([$path, $path . '-wal', $path . '-shm'] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        $this->temporaryDatabasePaths = [];
        parent::tearDown();
    }
}