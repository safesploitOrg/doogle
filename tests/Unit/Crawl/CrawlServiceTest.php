<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Crawl;

use Doogle\Crawl\CrawlRequest;
use Doogle\Crawl\CrawlService;
use Doogle\Crawl\UrlValidator;
use Doogle\Security\CrawlerSecurityPolicy;
use Doogle\Security\PrivateNetworkBlocker;
use PDO;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CrawlServiceTest extends TestCase
{
    private CrawlerSecurityPolicy $policy;
    private UrlValidator $validator;
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->policy = new CrawlerSecurityPolicy();
        $this->validator = new UrlValidator(
            $this->policy,
            new PrivateNetworkBlocker(static fn (string $host): array => [])
        );
        $this->pdo = new PDO('sqlite::memory:');
    }

    public function testDefaultCrawlerIndexesLinkedPagesImagesAndVideosWithoutExternalHttp(): void
    {
        $this->createCrawlerTables();
        $policy = new CrawlerSecurityPolicy(allowPrivateNetworks: true);
        $validator = new UrlValidator(
            $policy,
            new PrivateNetworkBlocker(static fn (string $host): array => [])
        );
        $pages = [
            'https://example.com/' => '<html><body><a href="/page">Page</a></body></html>',
            'https://example.com/page' => '<html><head>'
                . '<title>Example Page</title>'
                . '<meta name="description" content="Example description">'
                . '<meta name="keywords" content="example, page">'
                . '<meta property="og:image" content="/video-thumb.jpg">'
                . '</head><body>'
                . '<img src="/image.png" alt="Example image">'
                . '<video title="Example video" poster="/video-thumb.jpg">'
                . '<source src="/video.mp4" type="video/mp4"></video>'
                . '</body></html>',
        ];

        $service = new CrawlService(
            $this->pdo,
            $policy,
            $validator,
            null,
            static fn (string $url): string => $pages[$url] ?? ''
        );

        $result = $service->crawl(new CrawlRequest('https://example.com/', 2, 100, 10, 1024));

        self::assertTrue($result->successful);
        self::assertSame(1, $result->pagesIndexed);
        self::assertSame(1, $result->imagesIndexed);
        self::assertSame(1, $result->videosIndexed);
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM sites')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM images')->fetchColumn());
        self::assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM videos')->fetchColumn());
        self::assertStringContainsString('<b>URL:</b> https://example.com/page', $result->output);
        self::assertStringContainsString('<b>src:</b>', $result->output);
        self::assertStringContainsString('<b>video:</b>', $result->output);
    }

    public function testRejectsUnsafeStartUrlBeforeRunningCrawler(): void
    {
        $ran = false;
        $service = new CrawlService(
            $this->pdo,
            $this->policy,
            $this->validator,
            function () use (&$ran): string {
                $ran = true;

                return '';
            }
        );

        $result = $service->crawl(new CrawlRequest('http://127.0.0.1/', 2, 100, 10, 1024));

        self::assertFalse($ran);
        self::assertFalse($result->successful);
        self::assertSame(1, $result->urlsRejected);
        self::assertSame(['private or reserved network host'], $result->errors);
    }

    public function testRunsCrawlerForAllowedUrl(): void
    {
        $service = new CrawlService(
            $this->pdo,
            $this->policy,
            $this->validator,
            static fn (CrawlRequest $request): string => 'SUCCESS: '
                . $request->startUrl
                . '<br><b>URL:</b> '
                . $request->startUrl
                . '<br>'
        );

        $result = $service->crawl(new CrawlRequest('https://example.com/', 2, 100, 10, 1024));

        self::assertTrue($result->successful);
        self::assertSame(2, $result->pagesDiscovered);
        self::assertSame(1, $result->pagesIndexed);
        self::assertSame(
            'SUCCESS: https://example.com/<br><b>URL:</b> https://example.com/<br>',
            $result->output
        );
    }

    public function testRunnerFailureReturnsFailedResult(): void
    {
        $service = new CrawlService(
            $this->pdo,
            $this->policy,
            $this->validator,
            static function (): string {
                throw new RuntimeException('crawl failed');
            }
        );

        $result = $service->crawl(new CrawlRequest('https://example.com/', 2, 100, 10, 1024));

        self::assertFalse($result->successful);
        self::assertSame(['crawl failed'], $result->errors);
    }

    private function createCrawlerTables(): void
    {
        $this->pdo->exec(
            'CREATE TABLE sites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                url VARCHAR(512) NOT NULL,
                title VARCHAR(512) NOT NULL,
                description VARCHAR(512) NOT NULL,
                keywords VARCHAR(512) NOT NULL,
                clicks INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE images (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                siteUrl VARCHAR(512) NOT NULL,
                imageUrl VARCHAR(512) NOT NULL,
                alt VARCHAR(512) NOT NULL,
                title VARCHAR(512) NOT NULL,
                clicks INTEGER NOT NULL DEFAULT 0,
                broken INTEGER NOT NULL DEFAULT 0
            )'
        );
        $this->pdo->exec(
            'CREATE TABLE videos (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                siteUrl VARCHAR(512) NOT NULL,
                videoUrl VARCHAR(512) NOT NULL,
                thumbnailUrl VARCHAR(512) NOT NULL DEFAULT "",
                title VARCHAR(512) NOT NULL DEFAULT "",
                description VARCHAR(512) NOT NULL DEFAULT "",
                source VARCHAR(100) NOT NULL DEFAULT "",
                clicks INTEGER NOT NULL DEFAULT 0
            )'
        );
    }
}
