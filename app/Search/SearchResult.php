<?php

declare(strict_types=1);

namespace Doogle\Search;

final readonly class SearchResult
{
    public function __construct(
        public int $id,
        public string $url,
        public string $title,
        public ?string $description,
        public int $clicks,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            url: (string) ($row['url'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            description: array_key_exists('description', $row) ? (string) $row['description'] : null,
            clicks: (int) ($row['clicks'] ?? 0),
        );
    }
}
