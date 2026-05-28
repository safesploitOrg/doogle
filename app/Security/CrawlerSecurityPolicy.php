<?php

declare(strict_types=1);

namespace Doogle\Security;

final class CrawlerSecurityPolicy
{
    private const DEFAULT_ALLOWED_SCHEMES = ['http', 'https'];
    private const DEFAULT_USER_AGENT = 'doogleBot/1.0';

    /** @var list<string> */
    private array $allowedSchemes;
    private bool $allowPrivateNetworks;
    private int $maxDepth;
    private int $maxPages;
    private int $timeoutSeconds;
    private int $maxResponseBytes;
    private string $userAgent;

    /**
     * @param list<string> $allowedSchemes
     */
    public function __construct(
        array $allowedSchemes = self::DEFAULT_ALLOWED_SCHEMES,
        bool $allowPrivateNetworks = false,
        int $maxDepth = 2,
        int $maxPages = 100,
        int $timeoutSeconds = 10,
        int $maxResponseBytes = 1048576,
        string $userAgent = self::DEFAULT_USER_AGENT,
    ) {
        $schemes = array_map(
            static fn (string $scheme): string => strtolower($scheme),
            array_filter($allowedSchemes, static fn (string $scheme): bool => trim($scheme) !== '')
        );

        $this->allowedSchemes = array_values(array_unique($schemes ?: self::DEFAULT_ALLOWED_SCHEMES));
        $this->allowPrivateNetworks = $allowPrivateNetworks;
        $this->maxDepth = max(0, $maxDepth);
        $this->maxPages = max(1, $maxPages);
        $this->timeoutSeconds = max(1, $timeoutSeconds);
        $this->maxResponseBytes = max(1024, $maxResponseBytes);
        $this->userAgent = trim($userAgent) !== '' ? $userAgent : self::DEFAULT_USER_AGENT;
    }

    /**
     * @param array<string, scalar|null>|null $environment
     */
    public static function fromEnvironment(?array $environment = null): self
    {
        $environment ??= self::readRuntimeEnvironment();

        return new self(
            allowedSchemes: self::schemesFromEnvironment($environment),
            allowPrivateNetworks: self::boolFromEnvironment($environment, 'CRAWLER_ALLOW_PRIVATE_NETWORKS', false),
            maxDepth: self::intFromEnvironment($environment, 'CRAWLER_MAX_DEPTH', 2),
            maxPages: self::intFromEnvironment($environment, 'CRAWLER_MAX_PAGES_PER_JOB', 100),
            timeoutSeconds: self::intFromEnvironment($environment, 'CRAWLER_TIMEOUT_SECONDS', 10),
            maxResponseBytes: self::intFromEnvironment($environment, 'CRAWLER_MAX_RESPONSE_BYTES', 1048576),
            userAgent: (string) self::value($environment, 'CRAWLER_USER_AGENT', self::DEFAULT_USER_AGENT),
        );
    }

    public function allowsScheme(string $scheme): bool
    {
        return in_array(strtolower($scheme), $this->allowedSchemes, true);
    }

    public function allowsPrivateNetworks(): bool
    {
        return $this->allowPrivateNetworks;
    }

    public function allowsDepth(int $depth): bool
    {
        return $depth <= $this->maxDepth;
    }

    public function allowsMorePages(int $pagesCrawled): bool
    {
        return $pagesCrawled < $this->maxPages;
    }

    /**
     * @return list<string>
     */
    public function allowedSchemes(): array
    {
        return $this->allowedSchemes;
    }

    public function maxDepth(): int
    {
        return $this->maxDepth;
    }

    public function maxPages(): int
    {
        return $this->maxPages;
    }

    public function timeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function maxResponseBytes(): int
    {
        return $this->maxResponseBytes;
    }

    public function userAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * @return array<string, scalar|null>
     */
    private static function readRuntimeEnvironment(): array
    {
        $environment = array_merge($_SERVER, $_ENV);

        foreach (
            [
                'CRAWLER_ALLOWED_SCHEMES',
                'CRAWLER_ALLOW_PRIVATE_NETWORKS',
                'CRAWLER_MAX_DEPTH',
                'CRAWLER_MAX_PAGES_PER_JOB',
                'CRAWLER_TIMEOUT_SECONDS',
                'CRAWLER_MAX_RESPONSE_BYTES',
                'CRAWLER_USER_AGENT',
            ] as $key
        ) {
            $value = getenv($key);

            if ($value !== false) {
                $environment[$key] = $value;
            }
        }

        return $environment;
    }

    /**
     * @param array<string, scalar|null> $environment
     * @return list<string>
     */
    private static function schemesFromEnvironment(array $environment): array
    {
        $schemes = (string) self::value($environment, 'CRAWLER_ALLOWED_SCHEMES', implode(',', self::DEFAULT_ALLOWED_SCHEMES));

        return array_values(array_filter(
            array_map(static fn (string $scheme): string => trim($scheme), explode(',', $schemes)),
            static fn (string $scheme): bool => $scheme !== ''
        ));
    }

    /**
     * @param array<string, scalar|null> $environment
     */
    private static function boolFromEnvironment(array $environment, string $key, bool $default): bool
    {
        $value = self::value($environment, $key, $default);

        if (is_bool($value)) {
            return $value;
        }

        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
    }

    /**
     * @param array<string, scalar|null> $environment
     */
    private static function intFromEnvironment(array $environment, string $key, int $default): int
    {
        $value = self::value($environment, $key, $default);

        if (is_int($value)) {
            return $value;
        }

        return is_numeric((string) $value) ? (int) $value : $default;
    }

    /**
     * @param array<string, scalar|null> $environment
     */
    private static function value(array $environment, string $key, mixed $default): mixed
    {
        return array_key_exists($key, $environment) ? $environment[$key] : $default;
    }
}
