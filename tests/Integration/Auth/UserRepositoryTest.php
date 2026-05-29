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

    public function testFindByIdReturnsUserWithoutPasswordHash(): void
    {
        $id = $this->repository->create('admin', 'admin@example.com', 'plain-password', 'admin');

        $user = $this->repository->findById($id);

        self::assertInstanceOf(User::class, $user);
        self::assertSame('admin', $user->username);
        self::assertSame('admin@example.com', $user->email);
        self::assertSame('admin', $user->role);
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
    }
}
