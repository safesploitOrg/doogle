<?php

declare(strict_types=1);

namespace Doogle\Tests\Integration\Repository;

use Doogle\Crawl\CrawlResult;
use Doogle\Repository\CrawlJobRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class CrawlJobRepositoryTest extends TestCase
{
    private PDO $pdo;
    private CrawlJobRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE crawl_jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                start_url VARCHAR(512) NOT NULL,
                requested_by_user_id INTEGER NULL,
                status VARCHAR(20) NOT NULL DEFAULT "pending",
                pages_discovered INTEGER NOT NULL DEFAULT 0,
                pages_indexed INTEGER NOT NULL DEFAULT 0,
                images_indexed INTEGER NOT NULL DEFAULT 0,
                urls_rejected INTEGER NOT NULL DEFAULT 0,
                error_message TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->repository = new CrawlJobRepository($this->pdo);
    }

    public function testCreateStoresPendingJob(): void
    {
        $id = $this->repository->create('https://example.com', 7);
        $jobs = $this->repository->recent();

        self::assertSame(1, $id);
        self::assertCount(1, $jobs);
        self::assertSame('https://example.com', $jobs[0]->startUrl);
        self::assertSame(7, $jobs[0]->requestedByUserId);
        self::assertSame('pending', $jobs[0]->status);
    }

    public function testMarkRunningUpdatesStatus(): void
    {
        $id = $this->repository->create('https://example.com', null);

        self::assertTrue($this->repository->markRunning($id));

        $jobs = $this->repository->recent();
        self::assertSame('running', $jobs[0]->status);
        self::assertNull($jobs[0]->requestedByUserId);
    }

    public function testMarkFromSuccessfulResultStoresStats(): void
    {
        $id = $this->repository->create('https://example.com', 1);
        $result = new CrawlResult(
            successful: true,
            pagesDiscovered: 5,
            pagesIndexed: 2,
            imagesIndexed: 3,
            urlsRejected: 1,
        );

        self::assertTrue($this->repository->markFromResult($id, $result));

        $job = $this->repository->recent()[0];
        self::assertSame('completed', $job->status);
        self::assertSame(5, $job->pagesDiscovered);
        self::assertSame(2, $job->pagesIndexed);
        self::assertSame(3, $job->imagesIndexed);
        self::assertSame(1, $job->urlsRejected);
        self::assertNull($job->errorMessage);
    }

    public function testMarkFromRejectedResultStoresError(): void
    {
        $id = $this->repository->create('http://127.0.0.1', 1);

        self::assertTrue($this->repository->markFromResult($id, CrawlResult::rejected('private host')));

        $job = $this->repository->recent()[0];
        self::assertSame('rejected', $job->status);
        self::assertSame('private host', $job->errorMessage);
    }

    public function testMarkFromFailedResultStoresFailedStatus(): void
    {
        $id = $this->repository->create('https://example.com', 1);

        self::assertTrue($this->repository->markFromResult($id, CrawlResult::failed('fetch failed')));

        $job = $this->repository->recent()[0];
        self::assertSame('failed', $job->status);
        self::assertSame('fetch failed', $job->errorMessage);
    }
}
