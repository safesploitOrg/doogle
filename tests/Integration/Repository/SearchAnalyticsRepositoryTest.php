<?php

declare(strict_types=1);

namespace Doogle\Tests\Integration\Repository;

use Doogle\Repository\SearchAnalyticsRepository;
use PDO;
use PHPUnit\Framework\TestCase;

final class SearchAnalyticsRepositoryTest extends TestCase
{
    private PDO $pdo;
    private SearchAnalyticsRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE search_queries (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                term VARCHAR(255) NOT NULL,
                type VARCHAR(20) NOT NULL,
                result_count INTEGER NOT NULL DEFAULT 0,
                page INTEGER NOT NULL DEFAULT 1,
                ip_hash VARCHAR(64) NOT NULL DEFAULT "",
                user_agent_hash VARCHAR(64) NOT NULL DEFAULT "",
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->repository = new SearchAnalyticsRepository($this->pdo);
    }

    public function testRecordStoresSearchEvent(): void
    {
        self::assertTrue(
            $this->repository->record('linux', 'sites', 12, 2, str_repeat('a', 64), str_repeat('b', 64))
        );

        $events = $this->repository->recent();

        self::assertCount(1, $events);
        self::assertSame('linux', $events[0]->term);
        self::assertSame('sites', $events[0]->type);
        self::assertSame(12, $events[0]->resultCount);
        self::assertSame(2, $events[0]->page);
        self::assertSame(str_repeat('a', 64), $events[0]->ipHash);
        self::assertSame(str_repeat('b', 64), $events[0]->userAgentHash);
    }

    public function testRecordNormalizesNegativeCountsAndPages(): void
    {
        $this->repository->record('linux', 'sites', -2, -5, '', '');

        $event = $this->repository->recent()[0];

        self::assertSame(0, $event->resultCount);
        self::assertSame(1, $event->page);
    }

    public function testRecentOrdersNewestFirstAndAppliesLimit(): void
    {
        $this->repository->record('one', 'sites', 1, 1, '', '');
        $this->repository->record('two', 'images', 2, 1, '', '');

        $events = $this->repository->recent(1);

        self::assertCount(1, $events);
        self::assertSame('two', $events[0]->term);
    }

    public function testTopTermsGroupsByTermAndType(): void
    {
        $this->repository->record('linux', 'sites', 12, 1, '', '');
        $this->repository->record('linux', 'sites', 8, 1, '', '');
        $this->repository->record('linux', 'videos', 4, 1, '', '');

        $terms = $this->repository->topTerms();

        self::assertSame('linux', $terms[0]->term);
        self::assertSame('sites', $terms[0]->type);
        self::assertSame(2, $terms[0]->searches);
        self::assertSame(10, $terms[0]->averageResultCount);
    }

    public function testZeroResultTermsOnlyIncludesZeroResultSearches(): void
    {
        $this->repository->record('missing', 'sites', 0, 1, '', '');
        $this->repository->record('linux', 'sites', 3, 1, '', '');

        $terms = $this->repository->zeroResultTerms();

        self::assertCount(1, $terms);
        self::assertSame('missing', $terms[0]->term);
    }

    public function testTypeSummaryAggregatesBySearchVertical(): void
    {
        $this->repository->record('linux', 'sites', 10, 1, '', '');
        $this->repository->record('missing', 'sites', 0, 1, '', '');
        $this->repository->record('video', 'videos', 6, 1, '', '');

        $summary = $this->repository->typeSummary();

        self::assertSame('sites', $summary[0]->type);
        self::assertSame(2, $summary[0]->searches);
        self::assertSame(1, $summary[0]->zeroResultSearches);
        self::assertSame(5, $summary[0]->averageResultCount);
        self::assertSame('videos', $summary[1]->type);
    }
}
