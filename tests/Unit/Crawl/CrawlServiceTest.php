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
}
