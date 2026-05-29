<?php

declare(strict_types=1);

namespace Doogle\Auth;

use PDO;
use RuntimeException;

final class UserRepository
{
    private ?bool $hasRoleColumn = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $roleSelect = $this->hasRoleColumn() ? 'role' : "'admin' AS role";
        $statement = $this->pdo->prepare(
            'SELECT id, username, email, password, ' . $roleSelect . '
             FROM users
             WHERE username = :username
             LIMIT 1'
        );

        $statement->bindValue(':username', $username);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    public function findByEmail(string $email): ?User
    {
        $roleSelect = $this->hasRoleColumn() ? 'role' : "'admin' AS role";
        $statement = $this->pdo->prepare(
            'SELECT id, username, email, ' . $roleSelect . '
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $statement->bindValue(':email', $email);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? User::fromRow($row) : null;
    }

    public function findById(int $id): ?User
    {
        $roleSelect = $this->hasRoleColumn() ? 'role' : "'admin' AS role";
        $statement = $this->pdo->prepare(
            'SELECT id, username, email, ' . $roleSelect . '
             FROM users
             WHERE id = :id
             LIMIT 1'
        );

        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? User::fromRow($row) : null;
    }

    public function create(string $username, string $email, string $plainPassword, string $role = 'admin'): int
    {
        if (!$this->hasRoleColumn()) {
            $statement = $this->pdo->prepare(
                'INSERT INTO users (username, email, password)
                 VALUES (:username, :email, :password)'
            );

            $created = $statement->execute([
                ':username' => $username,
                ':email' => $email,
                ':password' => password_hash($plainPassword, PASSWORD_DEFAULT),
            ]);

            if (!$created) {
                throw new RuntimeException('Failed to create user.');
            }

            return (int) $this->pdo->lastInsertId();
        }

        $statement = $this->pdo->prepare(
            'INSERT INTO users (username, email, password, role)
             VALUES (:username, :email, :password, :role)'
        );

        $created = $statement->execute([
            ':username' => $username,
            ':email' => $email,
            ':password' => password_hash($plainPassword, PASSWORD_DEFAULT),
            ':role' => $role,
        ]);

        if (!$created) {
            throw new RuntimeException('Failed to create user.');
        }

        return (int) $this->pdo->lastInsertId();
    }

    private function hasRoleColumn(): bool
    {
        if ($this->hasRoleColumn !== null) {
            return $this->hasRoleColumn;
        }

        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $columns = $this->pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);

            foreach ($columns as $column) {
                if (($column['name'] ?? null) === 'role') {
                    return $this->hasRoleColumn = true;
                }
            }

            return $this->hasRoleColumn = false;
        }

        $statement = $this->pdo->prepare(
            'SELECT COUNT(*)
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tableName
               AND COLUMN_NAME = :columnName'
        );

        $statement->execute([
            ':tableName' => 'users',
            ':columnName' => 'role',
        ]);

        return $this->hasRoleColumn = (int) $statement->fetchColumn() > 0;
    }
}
