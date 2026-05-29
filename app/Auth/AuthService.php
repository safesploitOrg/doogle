<?php

declare(strict_types=1);

namespace Doogle\Auth;

final class AuthService
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    public function authenticate(string $username, string $password): ?User
    {
        $row = $this->users->findByUsername($username);

        if ($row === null || !isset($row['password']) || !is_string($row['password'])) {
            return null;
        }

        if (!password_verify($password, $row['password'])) {
            return null;
        }

        return User::fromRow($row);
    }
}
