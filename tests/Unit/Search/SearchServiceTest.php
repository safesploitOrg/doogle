<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Search;

use Doogle\Search\Paginator;
use Doogle\Search\SearchResult;
use Doogle\Search\SearchService;
use PHPUnit\Framework\TestCase;

final class SearchServiceTest extends TestCase
{
    public function testCountDelegatesToRepository(): void
    {
        $repository = new FakeSiteSearchRepository(7);
        $service = new SearchService($repository, new Paginator());

        self::assertSame(7, $service->count('linux'));
        self::assertSame(['linux'], $repository->countTerms);
    }

    public function testSearchReturnsDtoPageAndUsesPaginatorOffset(): void
    {
        $repository = new FakeSiteSearchRepository(2, [
            [
                'id' => '5',
                'url' => 'https://example.com/php',
                'title' => 'PHP',
                'description' => 'PHP guide',
                'clicks' => '12',
            ],
        ]);
        $service = new SearchService($repository, new Paginator());

        $page = $service->search('php', 2, 20);

        self::assertSame(2, $page->total);
        self::assertSame(2, $page->page);
        self::assertSame(20, $page->pageSize);
        self::assertSame([['php', 20, 20]], $repository->searchCalls);
        self::assertContainsOnlyInstancesOf(SearchResult::class, $page->results);
        self::assertSame(5, $page->results[0]->id);
        self::assertSame('https://example.com/php', $page->results[0]->url);
        self::assertSame(12, $page->results[0]->clicks);
    }

    public function testInvalidPageIsNormalisedWithoutRejectingSearchTerm(): void
    {
        $repository = new FakeSiteSearchRepository(0);
        $service = new SearchService($repository, new Paginator());

        $page = $service->search('x', -4, 20);

        self::assertSame(1, $page->page);
        self::assertSame([['x', 0, 20]], $repository->searchCalls);
    }
}
