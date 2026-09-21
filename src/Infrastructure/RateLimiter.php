<?php
declare(strict_types=1);

namespace Stranezze\Infrastructure;

use PDO;

final class RateLimiter
{
    private const LIMIT = 5;
    private const WINDOW_SECONDS = 900;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Logger $logger,
    )
    {
    }

    public function retryAfter(string $ip, string $username): int
    {
        $this->cleanup();

        $retryAfter = max(
            $this->retryAfterFor('ip', $ip),
            $this->retryAfterFor('username', $username),
        );

        if ($retryAfter > 0) {
            $this->logger->warning('Login rate limit raggiunto.', [
                'ip' => $ip,
                'username' => $username,
                'retry_after_seconds' => $retryAfter,
            ]);
        }

        return $retryAfter;
    }

    public function recordFailure(string $ip, string $username): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO login_attempts (ip, username, success) VALUES (:ip, :username, 0)'
        );
        try {
            $statement->execute(['ip' => $ip, 'username' => $username]);
        } catch (\Throwable $error) {
            $this->logDatabaseError('registrazione login fallito', $error);
            throw $error;
        }
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
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $this->logDatabaseError('registrazione login riuscito', $error);
            throw $error;
        }
    }

    private function cleanup(): void
    {
        try {
            $this->pdo->exec("DELETE FROM login_attempts WHERE attempted_at < datetime('now', '-1 hour')");
        } catch (\Throwable $error) {
            $this->logDatabaseError('pulizia tentativi login', $error);
        }
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
        try {
            $statement->execute(['value' => $value]);
            $result = $statement->fetch();
        } catch (\Throwable $error) {
            $this->logDatabaseError('conteggio tentativi login', $error);
            throw $error;
        }
        if ((int)$result['attempts'] < self::LIMIT || $result['oldest'] === null) {
            return 0;
        }

        return max(1, (int)$result['oldest'] + self::WINDOW_SECONDS - time());
    }

    private function logDatabaseError(string $operation, \Throwable $error): void
    {
        $this->logger->error('Errore database nel rate limiter.', [
            'operation' => $operation,
            'sqlstate' => $error instanceof \PDOException ? $error->errorInfo[0] ?? null : null,
            'exception' => $error,
        ]);
    }
}