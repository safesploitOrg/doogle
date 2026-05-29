<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Security;

use Doogle\Security\CsrfToken;
use PHPUnit\Framework\TestCase;

final class CsrfTokenTest extends TestCase
{
    public function testTokenGeneratesAndStoresToken(): void
    {
        $session = [];
        $csrf = new CsrfToken($session);

        $token = $csrf->token();

        self::assertSame($token, $session['_csrf_token']);
        self::assertSame(64, strlen($token));
    }

    public function testTokenReturnsExistingToken(): void
    {
        $session = ['_csrf_token' => 'existing-token'];
        $csrf = new CsrfToken($session);

        self::assertSame('existing-token', $csrf->token());
    }

    public function testVerifyAcceptsValidToken(): void
    {
        $session = ['_csrf_token' => 'known-token'];
        $csrf = new CsrfToken($session);

        self::assertTrue($csrf->verify('known-token'));
    }

    public function testVerifyRejectsMissingOrInvalidToken(): void
    {
        $session = ['_csrf_token' => 'known-token'];
        $csrf = new CsrfToken($session);

        self::assertFalse($csrf->verify(null));
        self::assertFalse($csrf->verify('wrong-token'));
    }

    public function testInvalidateRemovesToken(): void
    {
        $session = ['_csrf_token' => 'known-token'];
        $csrf = new CsrfToken($session);

        $csrf->invalidate();

        self::assertArrayNotHasKey('_csrf_token', $session);
    }
}
