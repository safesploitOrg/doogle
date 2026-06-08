<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Search;

use Doogle\Search\ImageResult;
use Doogle\Search\ImageSearchPage;
use Doogle\Search\SearchPage;
use Doogle\Search\SearchResult;
use Doogle\Search\SearchAnalyticsEvent;
use Doogle\Search\SearchAnalyticsTerm;
use Doogle\Search\SearchAnalyticsTypeSummary;
use Doogle\Search\VideoResult;
use Doogle\Search\VideoSearchPage;
use PHPUnit\Framework\TestCase;

final class SearchDtoTest extends TestCase
{
    public function testSearchResultCanBeCreatedFromRepositoryRow(): void
    {
        $result = SearchResult::fromRow([
            'id' => '12',
            'url' => 'https://example.com',
            'title' => 'Example',
            'description' => 'Example site',
            'clicks' => '5',
        ]);

        self::assertSame(12, $result->id);
        self::assertSame('https://example.com', $result->url);
        self::assertSame('Example', $result->title);
        self::assertSame('Example site', $result->description);
        self::assertSame(5, $result->clicks);
    }

    public function testImageResultCanBeCreatedFromRepositoryRow(): void
    {
        $result = ImageResult::fromRow([
            'id' => '3',
            'siteUrl' => 'https://example.com',
            'imageUrl' => 'https://example.com/image.png',
            'alt' => 'Alt text',
            'title' => 'Image title',
            'clicks' => '9',
            'broken' => '1',
        ]);

        self::assertSame(3, $result->id);
        self::assertSame('https://example.com', $result->siteUrl);
        self::assertSame('https://example.com/image.png', $result->imageUrl);
        self::assertSame('Alt text', $result->alt);
        self::assertSame('Image title', $result->title);
        self::assertSame(9, $result->clicks);
        self::assertTrue($result->broken);
    }

    public function testImageResultDisplayTextFallsBackToAltThenUrl(): void
    {
        self::assertSame(
            'Title',
            (new ImageResult(1, 'https://example.com', 'https://example.com/a.png', 'Alt', 'Title', 0, false))
                ->displayText()
        );
        self::assertSame(
            'Alt',
            (new ImageResult(1, 'https://example.com', 'https://example.com/a.png', 'Alt', '', 0, false))
                ->displayText()
        );
        self::assertSame(
            'https://example.com/a.png',
            (new ImageResult(1, 'https://example.com', 'https://example.com/a.png', '', '', 0, false))
                ->displayText()
        );
    }

    public function testVideoResultCanBeCreatedFromRepositoryRow(): void
    {
        $result = VideoResult::fromRow([
            'id' => '7',
            'siteUrl' => 'https://example.com/page',
            'videoUrl' => 'https://example.com/video.mp4',
            'thumbnailUrl' => 'https://example.com/thumb.jpg',
            'title' => 'Video title',
            'description' => 'Video description',
            'source' => 'video',
            'clicks' => '12',
        ]);

        self::assertSame(7, $result->id);
        self::assertSame('https://example.com/page', $result->siteUrl);
        self::assertSame('https://example.com/video.mp4', $result->videoUrl);
        self::assertSame('https://example.com/thumb.jpg', $result->thumbnailUrl);
        self::assertSame('Video title', $result->title);
        self::assertSame('Video description', $result->description);
        self::assertSame('video', $result->source);
        self::assertSame(12, $result->clicks);
    }

    public function testVideoResultDisplayTitleFallsBackToUrl(): void
    {
        self::assertSame(
            'Title',
            (new VideoResult(
                1,
                'https://example.com',
                'https://example.com/video.mp4',
                '',
                'Title',
                '',
                'video',
                0
            ))->displayTitle()
        );
        self::assertSame(
            'https://example.com/video.mp4',
            (new VideoResult(
                1,
                'https://example.com',
                'https://example.com/video.mp4',
                '',
                '',
                '',
                'video',
                0
            ))->displayTitle()
        );
    }

    public function testSearchAnalyticsEventCanBeCreatedFromRepositoryRow(): void
    {
        $event = SearchAnalyticsEvent::fromRow([
            'id' => '4',
            'term' => 'linux',
            'type' => 'sites',
            'result_count' => '12',
            'page' => '2',
            'ip_hash' => str_repeat('a', 64),
            'user_agent_hash' => str_repeat('b', 64),
            'created_at' => '2026-06-08 12:00:00',
        ]);

        self::assertSame(4, $event->id);
        self::assertSame('linux', $event->term);
        self::assertSame('sites', $event->type);
        self::assertSame(12, $event->resultCount);
        self::assertSame(2, $event->page);
        self::assertSame(str_repeat('a', 64), $event->ipHash);
        self::assertSame(str_repeat('b', 64), $event->userAgentHash);
        self::assertSame('2026-06-08 12:00:00', $event->createdAt);
    }

    public function testSearchAnalyticsTermCanBeCreatedFromRepositoryRow(): void
    {
        $term = SearchAnalyticsTerm::fromRow([
            'term' => 'linux',
            'type' => 'videos',
            'searches' => '3',
            'average_result_count' => '7.6',
            'last_searched_at' => '2026-06-08 12:00:00',
        ]);

        self::assertSame('linux', $term->term);
        self::assertSame('videos', $term->type);
        self::assertSame(3, $term->searches);
        self::assertSame(8, $term->averageResultCount);
        self::assertSame('2026-06-08 12:00:00', $term->lastSearchedAt);
    }

    public function testSearchAnalyticsTypeSummaryCanBeCreatedFromRepositoryRow(): void
    {
        $summary = SearchAnalyticsTypeSummary::fromRow([
            'type' => 'images',
            'searches' => '5',
            'zero_result_searches' => '2',
            'average_result_count' => '11.2',
        ]);

        self::assertSame('images', $summary->type);
        self::assertSame(5, $summary->searches);
        self::assertSame(2, $summary->zeroResultSearches);
        self::assertSame(11, $summary->averageResultCount);
    }

    public function testSearchPageCanRepresentEmptyResults(): void
    {
        $page = SearchPage::empty(1, 20);

        self::assertSame([], $page->results);
        self::assertSame(0, $page->total);
        self::assertSame(1, $page->page);
        self::assertSame(20, $page->pageSize);
    }

    public function testImageSearchPageCanRepresentEmptyResults(): void
    {
        $page = ImageSearchPage::empty(1, 30);

        self::assertSame([], $page->results);
        self::assertSame(0, $page->total);
        self::assertSame(1, $page->page);
        self::assertSame(30, $page->pageSize);
    }

    public function testVideoSearchPageCanRepresentEmptyResults(): void
    {
        $page = VideoSearchPage::empty(1, 24);

        self::assertSame([], $page->results);
        self::assertSame(0, $page->total);
        self::assertSame(1, $page->page);
        self::assertSame(24, $page->pageSize);
    }
}
