<?php

declare(strict_types=1);

namespace Doogle\Auth;

use PDO;
use RuntimeException;

final class UserRepository
{
    private ?bool $hasRoleColumn = null;
    private ?bool $hasTotpColumns = null;

    public function __construct(private readonly PDO $pdo)
    {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByUsername(string $username): ?array
    {
        $roleSelect = $this->hasRoleColumn() ? 'role' : "'admin' AS role";
        $totpSelect = $this->hasTotpColumns()
            ? 'totp_enabled, totp_secret'
            : '0 AS totp_enabled, NULL AS totp_secret';
        $statement = $this->pdo->prepare(
            'SELECT id, username, email, password, ' . $roleSelect . ', ' . $totpSelect . '
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

    public function isTotpEnabled(int $id): bool
    {
        if (!$this->hasTotpColumns()) {
            return false;
        }

        $statement = $this->pdo->prepare('SELECT totp_enabled FROM users WHERE id = :id LIMIT 1');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        return (int) $statement->fetchColumn() === 1;
    }

    public function totpSecret(int $id): ?string
    {
        if (!$this->hasTotpColumns()) {
            return null;
        }

        $statement = $this->pdo->prepare('SELECT totp_secret FROM users WHERE id = :id LIMIT 1');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $secret = $statement->fetchColumn();

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    public function enableTotp(int $id, string $secret): bool
    {
        if (!$this->hasTotpColumns()) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE users
             SET totp_secret = :totp_secret,
                 totp_enabled = 1,
                 totp_confirmed_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );

        return $statement->execute([
            ':id' => $id,
            ':totp_secret' => $secret,
        ]);
    }

    public function clearTotp(int $id): bool
    {
        if (!$this->hasTotpColumns()) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE users
             SET totp_secret = NULL,
                 totp_enabled = 0,
                 totp_confirmed_at = NULL
             WHERE id = :id'
        );

        return $statement->execute([':id' => $id]);
    }

    public function clearTotpForUsername(string $username): bool
    {
        if (!$this->hasTotpColumns()) {
            return false;
        }

        $statement = $this->pdo->prepare(
            'UPDATE users
             SET totp_secret = NULL,
                 totp_enabled = 0,
                 totp_confirmed_at = NULL
             WHERE username = :username'
        );

        return $statement->execute([':username' => $username]);
    }

    private function hasRoleColumn(): bool
    {
        if ($this->hasRoleColumn !== null) {
            return $this->hasRoleColumn;
        }

        return $this->hasRoleColumn = $this->hasColumn('role');
    }

    private function hasTotpColumns(): bool
    {
        if ($this->hasTotpColumns !== null) {
            return $this->hasTotpColumns;
        }

        return $this->hasTotpColumns = $this->hasColumn('totp_enabled')
            && $this->hasColumn('totp_secret')
            && $this->hasColumn('totp_confirmed_at');
    }

    private function hasColumn(string $columnName): bool
    {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $columns = $this->pdo->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);

            foreach ($columns as $column) {
                if (($column['name'] ?? null) === $columnName) {
                    return true;
                }
            }

            return false;
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
            ':columnName' => $columnName,
        ]);

        return (int) $statement->fetchColumn() > 0;
    }
}
