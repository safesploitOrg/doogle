<?php

declare(strict_types=1);

namespace Doogle\Security;

final class CsrfToken
{
    /** @var array<string, mixed> */
    private array $session;

    /**
     * @param array<string, mixed> $session
     */
    public function __construct(array &$session, private readonly string $sessionKey = '_csrf_token')
    {
        $this->session =& $session;
    }

    public function token(): string
    {
        $token = $this->session[$this->sessionKey] ?? null;

        if (is_string($token) && $token !== '') {
            return $token;
        }

        return $this->regenerate();
    }

    public function regenerate(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session[$this->sessionKey] = $token;

        return $token;
    }

    public function verify(?string $token): bool
    {
        $storedToken = $this->session[$this->sessionKey] ?? null;

        if (!is_string($storedToken) || $storedToken === '' || !is_string($token)) {
            return false;
        }

        return hash_equals($storedToken, $token);
    }

    public function invalidate(): void
    {
        unset($this->session[$this->sessionKey]);
    }
}
