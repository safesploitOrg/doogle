<?php

declare(strict_types=1);

namespace Doogle\Search;

final readonly class VideoResult
{
    public function __construct(
        public int $id,
        public string $siteUrl,
        public string $videoUrl,
        public string $thumbnailUrl,
        public string $title,
        public string $description,
        public string $source,
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
            siteUrl: (string) ($row['siteUrl'] ?? ''),
            videoUrl: (string) ($row['videoUrl'] ?? ''),
            thumbnailUrl: (string) ($row['thumbnailUrl'] ?? ''),
            title: (string) ($row['title'] ?? ''),
            description: (string) ($row['description'] ?? ''),
            source: (string) ($row['source'] ?? ''),
            clicks: (int) ($row['clicks'] ?? 0),
        );
    }

    public function displayTitle(): string
    {
        return $this->title !== '' ? $this->title : $this->videoUrl;
    }
}
