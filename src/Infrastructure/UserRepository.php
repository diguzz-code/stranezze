<?php
declare(strict_types=1);

namespace Stranezze\Infrastructure;

use DateTimeImmutable;
use PDO;
use Stranezze\Domain\User;

interface UserRepositoryInterface
{
    public function findByUsername(string $username): ?User;

    public function findById(int $id): ?User;

    public function create(
        string $username,
        string $passwordHash,
        string $role = 'user',
        bool $isActive = true,
    ): User;

    public function updateLastLogin(int $id, ?DateTimeImmutable $at = null): void;
}

final class UserRepository implements UserRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function findByUsername(string $username): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, password_hash, role, is_active, created_at, updated_at, last_login_at '
            . 'FROM users WHERE username = :username LIMIT 1'
        );
        $statement->execute(['username' => $username]);

        $row = $statement->fetch();
        return $row === false ? null : $this->mapRow($row);
    }

    public function findById(int $id): ?User
    {
        $statement = $this->pdo->prepare(
            'SELECT id, username, password_hash, role, is_active, created_at, updated_at, last_login_at '
            . 'FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        $row = $statement->fetch();
        return $row === false ? null : $this->mapRow($row);
    }

    public function create(
        string $username,
        string $passwordHash,
        string $role = 'user',
        bool $isActive = true,
    ): User {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (username, password_hash, role, is_active) '
            . 'VALUES (:username, :password_hash, :role, :is_active)'
        );
        $statement->execute([
            'username' => $username,
            'password_hash' => $passwordHash,
            'role' => $role,
            'is_active' => $isActive ? 1 : 0,
        ]);

        $user = $this->findById((int)$this->pdo->lastInsertId());
        if ($user === null) {
            throw new \RuntimeException('Utente creato ma non recuperabile.');
        }

        return $user;
    }

    public function updateLastLogin(int $id, ?DateTimeImmutable $at = null): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE users SET last_login_at = :last_login_at, updated_at = CURRENT_TIMESTAMP '
            . 'WHERE id = :id'
        );
        $statement->execute([
            'last_login_at' => ($at ?? new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'id' => $id,
        ]);
    }

    /** @param array<string, mixed> $row */
    private function mapRow(array $row): User
    {
        return new User(
            id: (int)$row['id'],
            username: (string)$row['username'],
            passwordHash: (string)$row['password_hash'],
            role: (string)$row['role'],
            isActive: (int)$row['is_active'] === 1,
            createdAt: (string)$row['created_at'],
            updatedAt: (string)$row['updated_at'],
            lastLoginAt: $row['last_login_at'] === null ? null : (string)$row['last_login_at'],
        );
    }
}