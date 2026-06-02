<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Search;

use Doogle\Search\Paginator;
use Doogle\Search\VideoResult;
use Doogle\Search\VideoSearchService;
use PHPUnit\Framework\TestCase;

final class VideoSearchServiceTest extends TestCase
{
    public function testCountDelegatesToRepository(): void
    {
        $repository = new FakeVideoSearchRepository(6);
        $service = new VideoSearchService($repository, new Paginator());

        self::assertSame(6, $service->count('linux'));
        self::assertSame(['linux'], $repository->countTerms);
    }

    public function testSearchReturnsDtoPageAndUsesPaginatorOffset(): void
    {
        $repository = new FakeVideoSearchRepository(1, [
            [
                'id' => '12',
                'siteUrl' => 'https://example.com/page',
                'videoUrl' => 'https://example.com/video.mp4',
                'thumbnailUrl' => 'https://example.com/thumb.jpg',
                'title' => 'Linux Video',
                'description' => 'Linux tutorial',
                'source' => 'video',
                'clicks' => '13',
            ],
        ]);
        $service = new VideoSearchService($repository, new Paginator());

        $page = $service->search('linux', 2, 24);

        self::assertSame(1, $page->total);
        self::assertSame(2, $page->page);
        self::assertSame(24, $page->pageSize);
        self::assertSame([['linux', 24, 24]], $repository->searchCalls);
        self::assertContainsOnlyInstancesOf(VideoResult::class, $page->results);
        self::assertSame(12, $page->results[0]->id);
        self::assertSame('https://example.com/video.mp4', $page->results[0]->videoUrl);
        self::assertSame(13, $page->results[0]->clicks);
    }

    public function testInvalidPageIsNormalisedWithoutRejectingSearchTerm(): void
    {
        $repository = new FakeVideoSearchRepository(0);
        $service = new VideoSearchService($repository, new Paginator());

        $page = $service->search('x', -4, 24);

        self::assertSame(1, $page->page);
        self::assertSame([['x', 0, 24]], $repository->searchCalls);
    }
}
