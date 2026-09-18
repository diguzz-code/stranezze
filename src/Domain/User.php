<?php
declare(strict_types=1);

namespace Stranezze\Domain;

final readonly class User
{
    public function __construct(
        public ?int $id,
        public string $username,
        public string $passwordHash,
        public string $role,
        public bool $isActive,
        public string $createdAt,
        public string $updatedAt,
        public ?string $lastLoginAt,
    ) {
    }
}