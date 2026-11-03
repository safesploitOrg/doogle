<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Security;

use Doogle\Security\PrivateNetworkBlocker;
use PHPUnit\Framework\TestCase;

final class PrivateNetworkBlockerTest extends TestCase
{
    private PrivateNetworkBlocker $blocker;

    protected function setUp(): void
    {
        $this->blocker = new PrivateNetworkBlocker(static fn (string $host): array => []);
    }

    public function testBlocksLocalhostNames(): void
    {
        self::assertTrue($this->blocker->isBlockedHost('localhost'));
        self::assertTrue($this->blocker->isBlockedHost('app.localhost'));
    }

    public function testDefaultResolverCanBeConstructedWithoutResolvingIpLiterals(): void
    {
        self::assertTrue((new PrivateNetworkBlocker())->isBlockedHost('127.0.0.1'));
    }

    public function testBlocksPrivateAndReservedIpv4Ranges(): void
    {
        self::assertTrue($this->blocker->isBlockedHost('127.0.0.1'));
        self::assertTrue($this->blocker->isBlockedHost('10.0.0.10'));
        self::assertTrue($this->blocker->isBlockedHost('172.16.0.1'));
        self::assertTrue($this->blocker->isBlockedHost('192.168.1.5'));
        self::assertTrue($this->blocker->isBlockedHost('169.254.10.1'));
    }

    public function testBlocksPrivateAndReservedIpv6Ranges(): void
    {
        self::assertTrue($this->blocker->isBlockedHost('[::1]'));
        self::assertTrue($this->blocker->isBlockedHost('fc00::1'));
        self::assertTrue($this->blocker->isBlockedHost('fe80::1'));
    }

    public function testAllowsPublicIps(): void
    {
        self::assertFalse($this->blocker->isBlockedHost('8.8.8.8'));
        self::assertFalse($this->blocker->isBlockedHost('2001:4860:4860::8888'));
    }

    public function testBlocksHostnamesThatResolveToPrivateAddresses(): void
    {
        $blocker = new PrivateNetworkBlocker(
            static fn (string $host): array => $host === 'internal.example' ? ['10.0.0.5'] : []
        );

        self::assertTrue($blocker->isBlockedHost('internal.example'));
    }
}
