<?php

declare(strict_types=1);

namespace Doogle\Search;

final class SearchAnalyticsTypeSummary
{
    public function __construct(
        public readonly string $type,
        public readonly int $searches,
        public readonly int $zeroResultSearches,
        public readonly int $averageResultCount,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            type: (string) ($row['type'] ?? ''),
            searches: (int) ($row['searches'] ?? 0),
            zeroResultSearches: (int) ($row['zero_result_searches'] ?? 0),
            averageResultCount: (int) round((float) ($row['average_result_count'] ?? 0)),
        );
    }
}
