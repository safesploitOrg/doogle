<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Search;

use Doogle\Search\ImageResult;
use Doogle\Search\ImageSearchService;
use Doogle\Search\Paginator;
use PHPUnit\Framework\TestCase;

final class ImageSearchServiceTest extends TestCase
{
    public function testCountDelegatesToRepository(): void
    {
        $repository = new FakeImageSearchRepository(4);
        $service = new ImageSearchService($repository, new Paginator());

        self::assertSame(4, $service->count('logo'));
        self::assertSame(['logo'], $repository->countTerms);
    }

    public function testSearchReturnsDtoPageAndUsesPaginatorOffset(): void
    {
        $repository = new FakeImageSearchRepository(1, [
            [
                'id' => '8',
                'siteUrl' => 'https://example.com',
                'imageUrl' => 'https://example.com/logo.png',
                'alt' => 'Logo',
                'title' => 'Example Logo',
                'clicks' => '11',
                'broken' => '0',
            ],
        ]);
        $service = new ImageSearchService($repository, new Paginator());

        $page = $service->search('logo', 3, 30);

        self::assertSame(1, $page->total);
        self::assertSame(3, $page->page);
        self::assertSame(30, $page->pageSize);
        self::assertSame([['logo', 60, 30]], $repository->searchCalls);
        self::assertContainsOnlyInstancesOf(ImageResult::class, $page->results);
        self::assertSame(8, $page->results[0]->id);
        self::assertSame('https://example.com/logo.png', $page->results[0]->imageUrl);
        self::assertFalse($page->results[0]->broken);
    }

    public function testInvalidPageIsNormalisedWithoutRejectingSearchTerm(): void
    {
        $repository = new FakeImageSearchRepository(0);
        $service = new ImageSearchService($repository, new Paginator());

        $page = $service->search('x', 0, 30);

        self::assertSame(1, $page->page);
        self::assertSame([['x', 0, 30]], $repository->searchCalls);
    }
}
