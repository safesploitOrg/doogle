<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Auth;

use Doogle\Auth\TotpService;
use PHPUnit\Framework\TestCase;

final class TotpServiceTest extends TestCase
{
    public function testGeneratesBase32Secret(): void
    {
        $secret = (new TotpService())->generateSecret();

        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        self::assertSame(32, strlen($secret));
    }

    public function testGeneratesExpectedCodeForKnownCounter(): void
    {
        $service = new TotpService();
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';

        self::assertSame('287082', $service->code($secret, 1));
        self::assertTrue($service->verify($secret, '287082', timestamp: 59));
        self::assertFalse($service->verify($secret, '000000', timestamp: 59));
    }

    public function testProvisioningUriIsCompactAndEscaped(): void
    {
        $uri = (new TotpService())->provisioningUri(
            'Doogle Search Engine',
            'admin user@example.local',
            'ABCDEF234567'
        );

        self::assertStringStartsWith('otpauth://totp/DoogleSearchEngi:adminuser%40example.local?', $uri);
        self::assertStringContainsString('secret=ABCDEF234567', $uri);
        self::assertStringContainsString('issuer=DoogleSearchEngi', $uri);
        self::assertLessThanOrEqual(106, strlen($uri));
    }
}
