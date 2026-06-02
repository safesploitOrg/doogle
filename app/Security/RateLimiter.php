<?php

declare(strict_types=1);

namespace Doogle\Security;

use Closure;

final class RateLimiter
{
    /** @var Closure(): int */
    private Closure $clock;

    public function __construct(
        private readonly string $storageDirectory,
        private readonly int $maxAttempts,
        private readonly int $windowSeconds,
        ?callable $clock = null,
    ) {
        $this->clock = Closure::fromCallable($clock ?? static fn (): int => time());
    }

    public static function fromEnvironment(string $scope): self
    {
        $upperScope = strtoupper($scope);
        $defaultMaxAttempts = $scope === 'crawl' ? 5 : 10;

        return new self(
            storageDirectory: (string) (getenv('DOOGLE_RATE_LIMIT_DIR') ?: sys_get_temp_dir() . '/doogle-rate-limits'),
            maxAttempts: self::positiveInt(
                getenv("DOOGLE_{$upperScope}_RATE_LIMIT_ATTEMPTS"),
                $defaultMaxAttempts
            ),
            windowSeconds: self::positiveInt(getenv("DOOGLE_{$upperScope}_RATE_LIMIT_WINDOW"), 60),
        );
    }

    public function attempt(string $scope, string $identity): RateLimitDecision
    {
        $now = ($this->clock)();
        $attempts = $this->readAttempts($scope, $identity, $now);

        if (count($attempts) >= $this->maxAttempts) {
            $oldestAttempt = min($attempts);

            return new RateLimitDecision(
                allowed: false,
                remainingAttempts: 0,
                retryAfterSeconds: max(1, ($oldestAttempt + $this->windowSeconds) - $now),
            );
        }

        $attempts[] = $now;
        $this->writeAttempts($scope, $identity, $attempts);

        return new RateLimitDecision(
            allowed: true,
            remainingAttempts: max(0, $this->maxAttempts - count($attempts)),
            retryAfterSeconds: 0,
        );
    }

    /**
     * @return list<int>
     */
    private function readAttempts(string $scope, string $identity, int $now): array
    {
        $path = $this->path($scope, $identity);

        if (!is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);
        $decoded = is_string($contents) ? json_decode($contents, true) : null;

        if (!is_array($decoded)) {
            return [];
        }

        $windowStart = $now - $this->windowSeconds;
        $attempts = [];

        foreach ($decoded as $timestamp) {
            if (!is_int($timestamp)) {
                continue;
            }

            if ($timestamp > $windowStart) {
                $attempts[] = $timestamp;
            }
        }

        return $attempts;
    }

    /**
     * @param list<int> $attempts
     */
    private function writeAttempts(string $scope, string $identity, array $attempts): void
    {
        if (!is_dir($this->storageDirectory)) {
            mkdir($this->storageDirectory, 0700, true);
        }

        file_put_contents($this->path($scope, $identity), json_encode($attempts), LOCK_EX);
    }

    private function path(string $scope, string $identity): string
    {
        $key = hash('sha256', $scope . '|' . $identity);

        return rtrim($this->storageDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $key . '.json';
    }

    private static function positiveInt(mixed $value, int $default): int
    {
        if (!is_numeric($value)) {
            return $default;
        }

        $integer = (int) $value;

        return $integer > 0 ? $integer : $default;
    }
}
