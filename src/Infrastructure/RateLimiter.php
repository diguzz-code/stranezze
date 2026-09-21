<?php
declare(strict_types=1);

namespace Stranezze\Infrastructure;

use PDO;

final class RateLimiter
{
    private const LIMIT = 5;
    private const WINDOW_SECONDS = 900;

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function retryAfter(string $ip, string $username): int
    {
        $this->cleanup();

        return max(
            $this->retryAfterFor('ip', $ip),
            $this->retryAfterFor('username', $username),
        );
    }

    public function recordFailure(string $ip, string $username): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_attempts (ip, username, success) VALUES (:ip, :username, 0)'
        );
        $statement->execute(['ip' => $ip, 'username' => $username]);
    }

    public function recordSuccess(string $ip, string $username): void
    {
        $this->pdo->beginTransaction();
        try {
            $delete = $this->pdo->prepare(
                'DELETE FROM login_attempts
                 WHERE success = 0 AND (ip = :ip OR username = :username)'
            );
            $delete->execute(['ip' => $ip, 'username' => $username]);

            $insert = $this->pdo->prepare(
                'INSERT INTO login_attempts (ip, username, success) VALUES (:ip, :username, 1)'
            );
            $insert->execute(['ip' => $ip, 'username' => $username]);
            $this->pdo->commit();
        } catch (\Throwable $error) {
            $this->pdo->rollBack();
            throw $error;
        }
    }

    private function cleanup(): void
    {
        $this->pdo->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-1 hour')");
    }

    private function retryAfterFor(string $column, string $value): int
    {
        $statement = $this->pdo->prepare(
            "SELECT COUNT(*) AS attempts, MIN(strftime('%s', attempted_at)) AS oldest
             FROM login_attempts
             WHERE {$column} = :value
               AND success = 0
               AND attempted_at >= datetime('now', '-15 minutes')"
        );
        $statement->execute(['value' => $value]);
        $result = $statement->fetch();
        if ((int)$result['attempts'] < self::LIMIT || $result['oldest'] === null) {
            return 0;
        }

        return max(1, (int)$result['oldest'] + self::WINDOW_SECONDS - time());
    }
}