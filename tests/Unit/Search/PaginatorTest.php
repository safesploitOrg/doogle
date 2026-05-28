<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Search;

use Doogle\Search\Paginator;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    private Paginator $paginator;

    protected function setUp(): void
    {
        $this->paginator = new Paginator();
    }

    public function testFirstPageOffsetIsZero(): void
    {
        self::assertSame(0, $this->paginator->offset(1, 20));
    }

    public function testSecondPageOffsetIsPageSize(): void
    {
        self::assertSame(20, $this->paginator->offset(2, 20));
    }

    public function testInvalidPageNormalisesToFirstPage(): void
    {
        self::assertSame(1, $this->paginator->normalizePage(0));
        self::assertSame(0, $this->paginator->offset(-3, 20));
    }

    public function testInvalidPageSizeReturnsZeroOffset(): void
    {
        self::assertSame(0, $this->paginator->offset(2, 0));
    }
}
