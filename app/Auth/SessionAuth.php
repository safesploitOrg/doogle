<?php

declare(strict_types=1);

namespace Doogle\Auth;

final class SessionAuth
{
    /** @var array<string, mixed> */
    private array $session;
    private bool $usesNativeSession;

    /**
     * @param array<string, mixed>|null $session
     */
    public function __construct(?array &$session = null)
    {
        if ($session === null) {
            if (!isset($_SESSION) || !is_array($_SESSION)) {
                $_SESSION = [];
            }

            $this->session =& $_SESSION;
            $this->usesNativeSession = true;

            return;
        }

        $this->session =& $session;
        $this->usesNativeSession = false;
    }

    public function start(): void
    {
        if (!$this->usesNativeSession) {
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION) || !is_array($_SESSION)) {
            $_SESSION = [];
        }

        $this->session =& $_SESSION;
    }

    public function login(User $user): void
    {
        $this->start();

        if ($this->usesNativeSession && session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        $this->session['user'] = $user->toSessionArray();
    }

    public function user(): ?User
    {
        $this->start();
        $user = $this->session['user'] ?? null;

        if (!is_array($user)) {
            return null;
        }

        return User::fromRow($user);
    }

    public function isAuthenticated(): bool
    {
        return $this->user() !== null;
    }

    public function isAdmin(): bool
    {
        return $this->user()?->role === 'admin';
    }

    public function logout(): void
    {
        $this->start();
        unset($this->session['user']);

        if ($this->usesNativeSession && session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
