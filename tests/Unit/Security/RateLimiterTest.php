<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Security;

use Doogle\Security\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    public function testAllowsAttemptsUntilLimitIsReached(): void
    {
        $directory = $this->temporaryDirectory();
        $limiter = new RateLimiter($directory, 2, 60, static fn (): int => 100);

        $first = $limiter->attempt('login', '127.0.0.1:admin');
        $second = $limiter->attempt('login', '127.0.0.1:admin');
        $third = $limiter->attempt('login', '127.0.0.1:admin');

        self::assertTrue($first->allowed);
        self::assertSame(1, $first->remainingAttempts);
        self::assertTrue($second->allowed);
        self::assertSame(0, $second->remainingAttempts);
        self::assertFalse($third->allowed);
        self::assertSame(60, $third->retryAfterSeconds);
    }

    public function testExpiredAttemptsAreIgnored(): void
    {
        $now = 100;
        $directory = $this->temporaryDirectory();
        $limiter = new RateLimiter($directory, 1, 60, static function () use (&$now): int {
            return $now;
        });

        self::assertTrue($limiter->attempt('crawl', 'user:1')->allowed);

        $now = 161;

        self::assertTrue($limiter->attempt('crawl', 'user:1')->allowed);
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir() . '/doogle-rate-limit-test-' . bin2hex(random_bytes(8));

        self::assertTrue(mkdir($directory, 0700, true));

        return $directory;
    }
}
