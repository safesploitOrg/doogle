<?php

declare(strict_types=1);

namespace Doogle\Crawl;

final readonly class CrawlJob
{
    public function __construct(
        public int $id,
        public string $startUrl,
        public ?int $requestedByUserId,
        public string $status,
        public int $pagesDiscovered,
        public int $pagesIndexed,
        public int $imagesIndexed,
        public int $urlsRejected,
        public ?string $errorMessage,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromRow(array $row): self
    {
        return new self(
            id: (int) ($row['id'] ?? 0),
            startUrl: (string) ($row['start_url'] ?? ''),
            requestedByUserId: isset($row['requested_by_user_id']) ? (int) $row['requested_by_user_id'] : null,
            status: (string) ($row['status'] ?? 'pending'),
            pagesDiscovered: (int) ($row['pages_discovered'] ?? 0),
            pagesIndexed: (int) ($row['pages_indexed'] ?? 0),
            imagesIndexed: (int) ($row['images_indexed'] ?? 0),
            urlsRejected: (int) ($row['urls_rejected'] ?? 0),
            errorMessage: isset($row['error_message']) ? (string) $row['error_message'] : null,
            createdAt: (string) ($row['created_at'] ?? ''),
            updatedAt: (string) ($row['updated_at'] ?? ''),
        );
    }
}
