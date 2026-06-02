<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Security;

use Doogle\Security\SessionCookiePolicy;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SessionCookiePolicyTest extends TestCase
{
    public function testCookieParamsUseSecureHttpOnlySameSiteValues(): void
    {
        $policy = new SessionCookiePolicy(secure: true, httpOnly: true, sameSite: 'Strict');

        self::assertSame(
            [
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => true,
                'httponly' => true,
                'samesite' => 'Strict',
            ],
            $policy->cookieParams()
        );
    }

    public function testEnvironmentDefaultsToSecureCookiesInProduction(): void
    {
        putenv('APP_ENV=production');
        putenv('SESSION_COOKIE_SECURE');
        putenv('SESSION_COOKIE_SAMESITE=Strict');

        $policy = SessionCookiePolicy::fromEnvironment();

        self::assertTrue($policy->secure);
        self::assertSame('Strict', $policy->sameSite);

        putenv('APP_ENV');
        putenv('SESSION_COOKIE_SAMESITE');
    }

    public function testInvalidSameSiteFallsBackToLax(): void
    {
        putenv('APP_ENV=local');
        putenv('SESSION_COOKIE_SECURE=false');
        putenv('SESSION_COOKIE_SAMESITE=invalid');

        $policy = SessionCookiePolicy::fromEnvironment();

        self::assertFalse($policy->secure);
        self::assertSame('Lax', $policy->sameSite);

        putenv('APP_ENV');
        putenv('SESSION_COOKIE_SECURE');
        putenv('SESSION_COOKIE_SAMESITE');
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testApplyConfiguresStrictNativeSessionSettings(): void
    {
        $policy = new SessionCookiePolicy(secure: true, httpOnly: true, sameSite: 'Lax');

        $policy->apply();

        self::assertSame('1', ini_get('session.use_strict_mode'));
        self::assertSame('1', ini_get('session.use_only_cookies'));
        self::assertSame('1', ini_get('session.cookie_httponly'));
        self::assertSame('1', ini_get('session.cookie_secure'));
        self::assertSame('Lax', ini_get('session.cookie_samesite'));
    }
}
