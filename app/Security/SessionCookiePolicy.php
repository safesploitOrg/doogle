<?php

declare(strict_types=1);

namespace Doogle\Security;

final readonly class SessionCookiePolicy
{
    public function __construct(
        public bool $secure,
        public bool $httpOnly = true,
        public string $sameSite = 'Lax',
    ) {
    }

    public static function fromEnvironment(): self
    {
        $environment = (string) (getenv('APP_ENV') ?: 'local');
        $secureDefault = in_array($environment, ['production', 'prod'], true);

        return new self(
            secure: self::envBool('SESSION_COOKIE_SECURE', $secureDefault),
            httpOnly: true,
            sameSite: self::sameSite((string) (getenv('SESSION_COOKIE_SAMESITE') ?: 'Lax')),
        );
    }

    /**
     * @return array{lifetime: int, path: string, domain: string, secure: bool, httponly: bool, samesite: string}
     */
    public function cookieParams(): array
    {
        return [
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $this->secure,
            'httponly' => $this->httpOnly,
            'samesite' => $this->sameSite,
        ];
    }

    public function apply(): void
    {
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', $this->httpOnly ? '1' : '0');
        ini_set('session.cookie_secure', $this->secure ? '1' : '0');
        ini_set('session.cookie_samesite', $this->sameSite);
        session_set_cookie_params($this->cookieParams());
    }

    private static function envBool(string $name, bool $default): bool
    {
        $value = getenv($name);

        if ($value === false || $value === '') {
            return $default;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }

    private static function sameSite(string $value): string
    {
        $normalised = ucfirst(strtolower($value));

        return in_array($normalised, ['Strict', 'Lax', 'None'], true) ? $normalised : 'Lax';
    }
}
