<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Auth;

use Doogle\Auth\AuthService;
use Doogle\Auth\UserRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private PDO $pdo;
    private AuthService $auth;

    protected function setUp(): void
    {
        $this->pdo = $this->createPdo();
        $this->auth = new AuthService(new UserRepository($this->pdo));
    }

    public function testValidUsernameAndPasswordReturnsUser(): void
    {
        $this->insertUser('admin', 'admin@example.com', 'correct-password', 'admin');

        $user = $this->auth->authenticate('admin', 'correct-password');

        self::assertNotNull($user);
        self::assertSame('admin', $user->username);
        self::assertSame('admin', $user->role);
    }

    public function testInvalidPasswordReturnsNull(): void
    {
        $this->insertUser('admin', 'admin@example.com', 'correct-password', 'admin');

        self::assertNull($this->auth->authenticate('admin', 'wrong-password'));
    }

    public function testUnknownUserReturnsNull(): void
    {
        self::assertNull($this->auth->authenticate('missing', 'password'));
    }

    private function createPdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                username VARCHAR(100) NOT NULL,
                email VARCHAR(255) NOT NULL,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT "admin"
            )'
        );

        return $pdo;
    }

    private function insertUser(string $username, string $email, string $password, string $role): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO users (username, email, password, role)
             VALUES (:username, :email, :password, :role)'
        );

        $statement->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => $role,
        ]);
    }
}
