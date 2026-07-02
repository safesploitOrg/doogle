<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Auth;

use Doogle\Auth\QrCodeSvg;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class QrCodeSvgTest extends TestCase
{
    public function testRenderReturnsInlineSvg(): void
    {
        $svg = (new QrCodeSvg())->render('otpauth://totp/Doogle:admin?secret=ABCDEF234567&issuer=Doogle');

        self::assertStringStartsWith('<svg ', $svg);
        self::assertStringContainsString('aria-label="TOTP QR code"', $svg);
        self::assertStringContainsString('<path fill="#111827"', $svg);
    }

    public function testOverlongTextIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new QrCodeSvg())->render(str_repeat('a', 107));
    }
}
