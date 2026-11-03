<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Crawl;

use Doogle\Crawl\CrawlRequest;
use Doogle\Security\CrawlerSecurityPolicy;
use PHPUnit\Framework\TestCase;

final class CrawlRequestTest extends TestCase
{
    public function testBuildsRequestFromSecurityPolicy(): void
    {
        $policy = new CrawlerSecurityPolicy(
            maxDepth: 3,
            maxPages: 20,
            timeoutSeconds: 5,
            maxResponseBytes: 2048,
        );

        $request = CrawlRequest::fromPolicy(' https://example.com ', $policy);

        self::assertSame('https://example.com', $request->startUrl);
        self::assertSame(3, $request->maxDepth);
        self::assertSame(20, $request->maxPages);
        self::assertSame(5, $request->timeoutSeconds);
        self::assertSame(2048, $request->maxResponseBytes);
    }
}
