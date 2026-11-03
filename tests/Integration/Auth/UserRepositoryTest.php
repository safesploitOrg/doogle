<?php

declare(strict_types=1);

namespace Doogle\Tests\Integration\Auth;

use Doogle\Auth\User;
use Doogle\Auth\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class UserRepositoryTest extends TestCase
{
    private PDO $pdo;
    private UserRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(100) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT "admin",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )'
        );

        $this->repository = new UserRepository($this->pdo);
    }

    public function testCreateStoresPasswordHash(): void
    {
        $id = $this->repository->create('admin', 'admin@example.com', 'plain-password');

        $row = $this->repository->findByUsername('admin');

        self::assertSame(1, $id);
        self::assertIsArray($row);
        self::assertSame('admin', $row['role']);
        self::assertSame(0, (int) $row['totp_enabled']);
        self::assertNull($row['totp_secret']);
        self::assertNotSame('plain-password', $row['password']);
        self::assertTrue(password_verify('plain-password', (string) $row['password']));
    }

    public function testFindByUsernameReturnsPasswordHashForAuthService(): void
    {
        $this->repository->create('admin', 'admin@example.com', 'plain-password', 'admin');

        $row = $this->repository->findByUsername('admin');

        self::assertIsArray($row);
        self::assertSame('admin', $row['username']);
        self::assertArrayHasKey('password', $row);
    }

    public function testUpdatePasswordReplacesStoredHash(): void
    {
        $id = $this->repository->create('admin', 'admin@example.com', 'plain-password', 'admin');

        self::assertTrue($this->repository->updatePassword($id, 'new-password'));

        $row = $this->repository->findByUsername('admin');

        self::assertIsArray($row);
        self::assertFalse(password_verify('plain-password', (string) $row['password']));
        self::assertTrue(password_verify('new-password', (string) $row['password']));
    }

    public function testFindByIdReturnsUserWithoutPasswordHash(): void
    {
        $id = $this->repository->create('admin', 'admin@example.com', 'plain-password', 'admin');

        $user = $this->repository->findById($id);

        self::assertInstanceOf(User::class, $user);
        self::assertSame('admin', $user->username);
        self::assertSame('admin@example.com', $user->email);
        self::assertSame('admin', $user->role);
    }

    public function testTotpCanBeEnabledAndClearedWhenColumnsExist(): void
    {
        $pdo = $this->createPdoWithTotpColumns();
        $repository = new UserRepository($pdo);
        $id = $repository->create('admin', 'admin@example.com', 'plain-password', 'admin');

        self::assertFalse($repository->isTotpEnabled($id));
        self::assertNull($repository->totpSecret($id));

        self::assertTrue($repository->enableTotp($id, 'ABCDEF234567'));

        $row = $repository->findByUsername('admin');

        self::assertTrue($repository->isTotpEnabled($id));
        self::assertSame('ABCDEF234567', $repository->totpSecret($id));
        self::assertIsArray($row);
        self::assertSame(1, (int) $row['totp_enabled']);
        self::assertSame('ABCDEF234567', $row['totp_secret']);

        self::assertTrue($repository->clearTotp($id));

        self::assertFalse($repository->isTotpEnabled($id));
        self::assertNull($repository->totpSecret($id));
    }

    public function testTotpCanBeClearedByUsername(): void
    {
        $pdo = $this->createPdoWithTotpColumns();
        $repository = new UserRepository($pdo);
        $id = $repository->create('admin', 'admin@example.com', 'plain-password', 'admin');

        self::assertTrue($repository->enableTotp($id, 'ABCDEF234567'));
        self::assertTrue($repository->clearTotpForUsername('admin'));

        self::assertFalse($repository->isTotpEnabled($id));
        self::assertNull($repository->totpSecret($id));
    }

    public function testFindByEmailReturnsUserWithoutPasswordHash(): void
    {
        $this->repository->create('admin', 'admin@example.com', 'plain-password', 'admin');

        $user = $this->repository->findByEmail('admin@example.com');

        self::assertInstanceOf(User::class, $user);
        self::assertSame('admin', $user->username);
        self::assertSame('admin@example.com', $user->email);
    }

    public function testMissingUserReturnsNull(): void
    {
        self::assertNull($this->repository->findByUsername('missing'));
        self::assertNull($this->repository->findByEmail('missing@example.com'));
        self::assertNull($this->repository->findById(123));
    }

    public function testLegacyUsersTableWithoutRoleDefaultsUsersToAdmin(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL
            )'
        );
        $repository = new UserRepository($pdo);

        $id = $repository->create('legacy', 'legacy@example.com', 'plain-password');

        $row = $repository->findByUsername('legacy');
        $user = $repository->findById($id);

        self::assertIsArray($row);
        self::assertSame('admin', $row['role']);
        self::assertInstanceOf(User::class, $user);
        self::assertSame('admin', $user->role);
        self::assertFalse($repository->isTotpEnabled($id));
        self::assertNull($repository->totpSecret($id));
        self::assertFalse($repository->enableTotp($id, 'ABCDEF234567'));
        self::assertFalse($repository->clearTotp($id));
        self::assertFalse($repository->clearTotpForUsername('legacy'));
    }

    private function createPdoWithTotpColumns(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(100) NOT NULL UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT "admin",
                totp_secret VARCHAR(64) DEFAULT NULL,
                totp_enabled INTEGER NOT NULL DEFAULT 0,
                totp_confirmed_at TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )'
        );

        return $pdo;
    }
}
