<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Security;

use Doogle\Security\CrawlerSecurityPolicy;
use PHPUnit\Framework\TestCase;

final class CrawlerSecurityPolicyTest extends TestCase
{
    public function testDefaultsAreBoundedAndPrivateNetworkBlockingIsOn(): void
    {
        $policy = new CrawlerSecurityPolicy();

        self::assertSame(['http', 'https'], $policy->allowedSchemes());
        self::assertFalse($policy->allowsPrivateNetworks());
        self::assertSame(2, $policy->maxDepth());
        self::assertSame(100, $policy->maxPages());
        self::assertSame(10, $policy->timeoutSeconds());
        self::assertSame(1048576, $policy->maxResponseBytes());
        self::assertSame('doogleBot/1.0', $policy->userAgent());
    }

    public function testReadsCrawlerSettingsFromEnvironmentArray(): void
    {
        $policy = CrawlerSecurityPolicy::fromEnvironment([
            'CRAWLER_ALLOWED_SCHEMES' => 'https',
            'CRAWLER_ALLOW_PRIVATE_NETWORKS' => 'true',
            'CRAWLER_MAX_DEPTH' => '4',
            'CRAWLER_MAX_PAGES_PER_JOB' => '25',
            'CRAWLER_TIMEOUT_SECONDS' => '3',
            'CRAWLER_MAX_RESPONSE_BYTES' => '4096',
            'CRAWLER_USER_AGENT' => 'doogleBot/test',
        ]);

        self::assertSame(['https'], $policy->allowedSchemes());
        self::assertTrue($policy->allowsPrivateNetworks());
        self::assertSame(4, $policy->maxDepth());
        self::assertSame(25, $policy->maxPages());
        self::assertSame(3, $policy->timeoutSeconds());
        self::assertSame(4096, $policy->maxResponseBytes());
        self::assertSame('doogleBot/test', $policy->userAgent());
    }

    public function testInvalidNumericLimitsAreClampedToSafeMinimums(): void
    {
        $policy = new CrawlerSecurityPolicy(
            maxDepth: -5,
            maxPages: 0,
            timeoutSeconds: 0,
            maxResponseBytes: 10,
            userAgent: '',
        );

        self::assertSame(0, $policy->maxDepth());
        self::assertSame(1, $policy->maxPages());
        self::assertSame(1, $policy->timeoutSeconds());
        self::assertSame(1024, $policy->maxResponseBytes());
        self::assertSame('doogleBot/1.0', $policy->userAgent());
    }

    public function testDepthAndPageLimitsAreEnforced(): void
    {
        $policy = new CrawlerSecurityPolicy(maxDepth: 1, maxPages: 2);

        self::assertTrue($policy->allowsDepth(0));
        self::assertTrue($policy->allowsDepth(1));
        self::assertFalse($policy->allowsDepth(2));
        self::assertTrue($policy->allowsMorePages(0));
        self::assertTrue($policy->allowsMorePages(1));
        self::assertFalse($policy->allowsMorePages(2));
    }
}
