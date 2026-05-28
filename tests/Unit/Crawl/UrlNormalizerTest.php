<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Crawl;

use Doogle\Crawl\UrlNormalizer;
use PHPUnit\Framework\TestCase;

final class UrlNormalizerTest extends TestCase
{
    private UrlNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new UrlNormalizer();
    }

    public function testLeavesAbsoluteHttpUrlsUnchanged(): void
    {
        self::assertSame(
            'http://other.example/page',
            $this->normalizer->normalize('http://other.example/page', 'https://example.com/path/page')
        );
    }

    public function testLeavesAbsoluteHttpsUrlsUnchanged(): void
    {
        self::assertSame(
            'https://other.example/page',
            $this->normalizer->normalize('https://other.example/page', 'https://example.com/path/page')
        );
    }

    public function testConvertsProtocolRelativeUrlToBaseScheme(): void
    {
        self::assertSame(
            'https://cdn.example.com/image.png',
            $this->normalizer->normalize('//cdn.example.com/image.png', 'https://example.com/path/page')
        );
    }

    public function testConvertsRootRelativeUrlToAbsoluteUrl(): void
    {
        self::assertSame(
            'https://example.com/about',
            $this->normalizer->normalize('/about', 'https://example.com/path/page')
        );
    }

    public function testConvertsDotRelativeUrlUsingBaseDirectory(): void
    {
        self::assertSame(
            'https://example.com/path/next',
            $this->normalizer->normalize('./next', 'https://example.com/path/page')
        );
    }

    public function testPreservesLegacyParentRelativeBehaviour(): void
    {
        self::assertSame(
            'https://example.com/../next',
            $this->normalizer->normalize('../next', 'https://example.com/path/page')
        );
    }

    public function testConvertsPlainRelativeUrlToHostRoot(): void
    {
        self::assertSame(
            'https://example.com/images/photo.jpg',
            $this->normalizer->normalize('images/photo.jpg', 'https://example.com/path/page')
        );
    }
}
