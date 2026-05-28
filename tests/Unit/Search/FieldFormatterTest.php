<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Search;

use Doogle\Search\FieldFormatter;
use PHPUnit\Framework\TestCase;

final class FieldFormatterTest extends TestCase
{
    private FieldFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new FieldFormatter();
    }

    public function testShortValueIsUnchanged(): void
    {
        self::assertSame('Doogle', $this->formatter->trim('Doogle', 10));
    }

    public function testValueAtLimitDoesNotReceiveEllipsis(): void
    {
        self::assertSame('Doogle', $this->formatter->trim('Doogle', 6));
    }

    public function testLongValueIsTrimmedWithLegacyEllipsis(): void
    {
        self::assertSame('Doo...', $this->formatter->trim('Doogle', 3));
    }

    public function testNegativeLimitNormalisesToZero(): void
    {
        self::assertSame('...', $this->formatter->trim('Doogle', -5));
    }
}
