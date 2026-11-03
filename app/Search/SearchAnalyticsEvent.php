<?php

declare(strict_types=1);

namespace Doogle\Search;

final class SearchAnalyticsEvent
{
    public function __construct(
        public readonly int $id,
        public readonly string $term,
        public readonly string $type,
        public readonly int $resultCount,
        public readonly int $page,
        public readonly string $ipHash,
        public readonly string $userAgentHash,
        public readonly string $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            term: (string) ($row['term'] ?? ''),
            type: (string) ($row['type'] ?? ''),
            resultCount: (int) ($row['result_count'] ?? 0),
            page: (int) ($row['page'] ?? 1),
            ipHash: (string) ($row['ip_hash'] ?? ''),
            userAgentHash: (string) ($row['user_agent_hash'] ?? ''),
            createdAt: (string) ($row['created_at'] ?? ''),
        );
    }
}
