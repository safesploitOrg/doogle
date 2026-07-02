<?php

declare(strict_types=1);

namespace Doogle\Auth;

final readonly class AdminLoginEvent
{
    public function __construct(
        public int $id,
        public ?int $userId,
        public string $username,
        public bool $successful,
        public string $failureReason,
        public string $ipAddress,
        public string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        $userId = $row['user_id'] ?? null;

        return new self(
            id: (int) ($row['id'] ?? 0),
            userId: $userId === null ? null : (int) $userId,
            username: (string) ($row['username'] ?? ''),
            successful: (bool) ($row['successful'] ?? false),
            failureReason: (string) ($row['failure_reason'] ?? ''),
            ipAddress: (string) ($row['ip_address'] ?? ''),
            createdAt: (string) ($row['created_at'] ?? ''),
        );
    }
}
