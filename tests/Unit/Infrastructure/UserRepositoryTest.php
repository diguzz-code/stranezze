<?php
declare(strict_types=1);

namespace Stranezze\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use Stranezze\Infrastructure\UserRepository;
use Stranezze\Tests\TestCase;

final class UserRepositoryTest extends TestCase
{
    private UserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new UserRepository($this->createTemporaryDatabase());
    }

    #[Test]
    public function itCreatesAndFindsUsersByUsernameAndId(): void
    {
        $created = $this->repository->create('alice', 'password-hash', 'admin');

        self::assertNotNull($created->id);
        self::assertSame('admin', $created->role);
        self::assertTrue($created->isActive);
        self::assertSame($created->id, $this->repository->findByUsername('alice')?->id);
        self::assertSame('alice', $this->repository->findById((int)$created->id)?->username);
    }

    #[Test]
    public function itReturnsNullForUnknownUsers(): void
    {
        self::assertNull($this->repository->findByUsername('missing'));
        self::assertNull($this->repository->findById(99999));
    }

    #[Test]
    public function itUpdatesLastLogin(): void
    {
        $created = $this->repository->create('alice', 'password-hash');
        $at = new DateTimeImmutable('2026-09-24 12:34:56');

        $this->repository->updateLastLogin((int)$created->id, $at);

        self::assertSame('2026-09-24 12:34:56', $this->repository->findById((int)$created->id)?->lastLoginAt);
    }
}