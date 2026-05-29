<?php

declare(strict_types=1);

namespace Doogle\Auth;

final readonly class User
{
    public function __construct(
        public int $id,
        public string $username,
        public string $email,
        public string $role,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            username: (string) ($row['username'] ?? ''),
            email: (string) ($row['email'] ?? ''),
            role: (string) ($row['role'] ?? 'admin'),
        );
    }

    /**
     * @return array{id: int, username: string, email: string, role: string}
     */
    public function toSessionArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'role' => $this->role,
        ];
    }
}
