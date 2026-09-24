<?php
declare(strict_types=1);

namespace Stranezze\Tests\Unit\Domain;

use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use Stranezze\Domain\User;
use Stranezze\Tests\TestCase;

final class UserTest extends TestCase
{
    #[Test]
    public function itExposesReadonlyTypedPropertiesAndNullableId(): void
    {
        $user = new User(
            id: null,
            username: 'alice',
            passwordHash: 'hash',
            role: 'user',
            isActive: true,
            createdAt: '2026-09-24 10:00:00',
            updatedAt: '2026-09-24 10:00:00',
            lastLoginAt: null,
        );

        self::assertNull($user->id);
        self::assertSame('alice', $user->username);
        self::assertTrue($user->isActive);

        $reflection = new ReflectionClass(User::class);
        self::assertTrue($reflection->isReadOnly());
        self::assertSame('?int', (string)$reflection->getProperty('id')->getType());
        self::assertSame('string', (string)$reflection->getProperty('username')->getType());
        self::assertSame('bool', (string)$reflection->getProperty('isActive')->getType());
    }
}