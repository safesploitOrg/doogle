<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Crawl;

use Doogle\Crawl\CrawlResult;
use PHPUnit\Framework\TestCase;

final class CrawlResultTest extends TestCase
{
    public function testRejectedResultCapturesReason(): void
    {
        $result = CrawlResult::rejected('private network');

        self::assertFalse($result->successful);
        self::assertSame(1, $result->urlsRejected);
        self::assertSame(0, $result->videosIndexed);
        self::assertSame(['private network'], $result->errors);
    }

    public function testFailedResultCapturesReasonAndOutput(): void
    {
        $result = CrawlResult::failed('failed', 'partial output');

        self::assertFalse($result->successful);
        self::assertSame(0, $result->videosIndexed);
        self::assertSame(['failed'], $result->errors);
        self::assertSame('partial output', $result->output);
    }
}
