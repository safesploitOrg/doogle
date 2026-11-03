<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Crawl;

use Doogle\Crawl\UrlValidator;
use Doogle\Security\CrawlerSecurityPolicy;
use Doogle\Security\PrivateNetworkBlocker;
use PHPUnit\Framework\TestCase;

final class UrlValidatorTest extends TestCase
{
    private PrivateNetworkBlocker $blocker;

    protected function setUp(): void
    {
        $this->blocker = new PrivateNetworkBlocker(static fn (string $host): array => []);
    }

    public function testAllowsHttpAndHttpsUrls(): void
    {
        $validator = new UrlValidator(new CrawlerSecurityPolicy(), $this->blocker);

        self::assertTrue($validator->isAllowed('https://example.com/page'));
        self::assertTrue($validator->isAllowed('http://example.com/page'));
    }

    public function testRejectsNonHttpUrls(): void
    {
        $validator = new UrlValidator(new CrawlerSecurityPolicy(), $this->blocker);

        self::assertSame('unsupported URL scheme', $validator->rejectionReason('file:///etc/passwd'));
        self::assertSame('unsupported URL scheme', $validator->rejectionReason('javascript:alert(1)'));
    }

    public function testRejectsUrlsWithoutHost(): void
    {
        $validator = new UrlValidator(new CrawlerSecurityPolicy(), $this->blocker);

        self::assertSame('URL must include a scheme and host', $validator->rejectionReason('/relative/path'));
    }

    public function testRejectsCredentialsInUrls(): void
    {
        $validator = new UrlValidator(new CrawlerSecurityPolicy(), $this->blocker);

        self::assertSame(
            'credentials in URLs are not allowed',
            $validator->rejectionReason('https://user:pass@example.com/')
        );
    }

    public function testRejectsPrivateNetworkUrlsByDefault(): void
    {
        $validator = new UrlValidator(new CrawlerSecurityPolicy(), $this->blocker);

        self::assertSame(
            'private or reserved network host',
            $validator->rejectionReason('http://127.0.0.1/admin')
        );
        self::assertSame(
            'private or reserved network host',
            $validator->rejectionReason('http://172.16.0.1/')
        );
    }

    public function testAllowsPrivateNetworkUrlsWhenPolicyAllowsThem(): void
    {
        $validator = new UrlValidator(new CrawlerSecurityPolicy(allowPrivateNetworks: true), $this->blocker);

        self::assertTrue($validator->isAllowed('http://127.0.0.1/admin'));
    }

    public function testRejectsUrlsBeyondMaxDepth(): void
    {
        $validator = new UrlValidator(new CrawlerSecurityPolicy(maxDepth: 1), $this->blocker);

        self::assertTrue($validator->isAllowed('https://example.com/', 1));
        self::assertSame(
            'maximum crawl depth exceeded',
            $validator->rejectionReason('https://example.com/deeper', 2)
        );
    }
}
