<?php

declare(strict_types=1);

namespace Doogle\Search;

final readonly class ImageResult
{
    public function __construct(
        public int $id,
        public string $siteUrl,
        public string $imageUrl,
        public ?string $alt,
        public ?string $title,
        public int $clicks,
        public bool $broken,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            siteUrl: (string) ($row['siteUrl'] ?? ''),
            imageUrl: (string) ($row['imageUrl'] ?? ''),
            alt: array_key_exists('alt', $row) ? (string) $row['alt'] : null,
            title: array_key_exists('title', $row) ? (string) $row['title'] : null,
            clicks: (int) ($row['clicks'] ?? 0),
            broken: (bool) ((int) ($row['broken'] ?? 0)),
        );
    }

    public function displayText(): string
    {
        return $this->title ?: ($this->alt ?: $this->imageUrl);
    }
}
