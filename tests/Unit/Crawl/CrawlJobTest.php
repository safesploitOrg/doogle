<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Crawl;

use Doogle\Crawl\CrawlJob;
use PHPUnit\Framework\TestCase;

final class CrawlJobTest extends TestCase
{
    public function testFromRowCastsDatabaseValues(): void
    {
        $job = CrawlJob::fromRow([
            'id' => '5',
            'start_url' => 'https://example.com',
            'requested_by_user_id' => '2',
            'status' => 'completed',
            'pages_discovered' => '7',
            'pages_indexed' => '3',
            'images_indexed' => '4',
            'videos_indexed' => '2',
            'urls_rejected' => '1',
            'error_message' => null,
            'created_at' => '2026-06-01 10:00:00',
            'updated_at' => '2026-06-01 10:01:00',
        ]);

        self::assertSame(5, $job->id);
        self::assertSame('https://example.com', $job->startUrl);
        self::assertSame(2, $job->requestedByUserId);
        self::assertSame('completed', $job->status);
        self::assertSame(7, $job->pagesDiscovered);
        self::assertSame(3, $job->pagesIndexed);
        self::assertSame(4, $job->imagesIndexed);
        self::assertSame(2, $job->videosIndexed);
        self::assertSame(1, $job->urlsRejected);
        self::assertNull($job->errorMessage);
        self::assertSame('2026-06-01 10:00:00', $job->createdAt);
        self::assertSame('2026-06-01 10:01:00', $job->updatedAt);
    }

    public function testFromRowAllowsNullRequester(): void
    {
        $job = CrawlJob::fromRow([
            'id' => 1,
            'start_url' => 'https://example.com',
            'requested_by_user_id' => null,
        ]);

        self::assertNull($job->requestedByUserId);
        self::assertSame('pending', $job->status);
    }
}
