<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Search;

use Doogle\Search\ImageResult;
use Doogle\Search\ImageSearchPage;
use Doogle\Search\SearchPage;
use Doogle\Search\SearchResult;
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
}
