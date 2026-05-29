<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Auth;

use Doogle\Auth\SessionAuth;
use Doogle\Auth\User;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

final class SessionAuthTest extends TestCase
{
    public function testLoginStoresMinimalUserDataInSession(): void
    {
        $session = [];
        $auth = new SessionAuth($session);

        $auth->login(new User(1, 'admin', 'admin@example.com', 'admin'));

        self::assertSame(
            [
                'id' => 1,
                'username' => 'admin',
                'email' => 'admin@example.com',
                'role' => 'admin',
            ],
            $session['user']
        );
        self::assertTrue($auth->isAuthenticated());
        self::assertTrue($auth->isAdmin());
    }

    public function testNativeSessionConstructorInitializesMissingSessionArray(): void
    {
        $previousSession = $_SESSION ?? null;
        unset($_SESSION);

        try {
            new SessionAuth();

            self::assertIsArray($_SESSION);
        } finally {
            if ($previousSession === null) {
                unset($_SESSION);
            } else {
                $_SESSION = $previousSession;
            }
        }
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testNativeLoginStoresUserInPhpSessionAfterSessionStart(): void
    {
        session_save_path(sys_get_temp_dir());
        session_id('doogle-' . bin2hex(random_bytes(8)));
        unset($_SESSION);

        $auth = new SessionAuth();
        $auth->login(new User(1, 'admin', 'admin@example.com', 'admin'));
        $sessionId = session_id();

        self::assertSame('admin', $_SESSION['user']['username'] ?? null);
        self::assertSame('admin', $_SESSION['user']['role'] ?? null);

        session_write_close();
        @unlink(sys_get_temp_dir() . '/sess_' . $sessionId);
    }

    public function testUserReturnsNullWhenSessionHasNoUser(): void
    {
        $session = [];
        $auth = new SessionAuth($session);

        self::assertNull($auth->user());
        self::assertFalse($auth->isAuthenticated());
        self::assertFalse($auth->isAdmin());
    }

    public function testNonAdminUserIsNotAdmin(): void
    {
        $session = [];
        $auth = new SessionAuth($session);

        $auth->login(new User(2, 'editor', 'editor@example.com', 'editor'));

        self::assertTrue($auth->isAuthenticated());
        self::assertFalse($auth->isAdmin());
    }

    public function testLogoutClearsSessionUser(): void
    {
        $session = [];
        $auth = new SessionAuth($session);

        $auth->login(new User(1, 'admin', 'admin@example.com', 'admin'));
        $auth->logout();

        self::assertArrayNotHasKey('user', $session);
        self::assertFalse($auth->isAuthenticated());
    }
}
