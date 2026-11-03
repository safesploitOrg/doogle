<?php

declare(strict_types=1);

namespace Doogle\Search;

final class SearchAnalyticsTerm
{
    public function __construct(
        public readonly string $term,
        public readonly string $type,
        public readonly int $searches,
        public readonly int $averageResultCount,
        public readonly string $lastSearchedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            term: (string) ($row['term'] ?? ''),
            type: (string) ($row['type'] ?? ''),
            searches: (int) ($row['searches'] ?? 0),
            averageResultCount: (int) round((float) ($row['average_result_count'] ?? 0)),
            lastSearchedAt: (string) ($row['last_searched_at'] ?? ''),
        );
    }
}
