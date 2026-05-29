<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Auth;

use Doogle\Auth\User;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    public function testBuildsUserFromDatabaseRow(): void
    {
        $user = User::fromRow([
            'id' => '7',
            'username' => 'admin',
            'email' => 'admin@example.com',
            'role' => 'admin',
        ]);

        self::assertSame(7, $user->id);
        self::assertSame('admin', $user->username);
        self::assertSame('admin@example.com', $user->email);
        self::assertSame('admin', $user->role);
    }

    public function testDefaultsMissingRoleToAdminForLegacyRows(): void
    {
        $user = User::fromRow([
            'id' => 3,
            'username' => 'legacy',
            'email' => 'legacy@example.com',
        ]);

        self::assertSame('admin', $user->role);
    }

    public function testConvertsToMinimalSessionArray(): void
    {
        $user = new User(1, 'admin', 'admin@example.com', 'admin');

        self::assertSame(
            [
                'id' => 1,
                'username' => 'admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
            ],
            $user->toSessionArray()
        );
    }
}
