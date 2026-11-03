<?php

declare(strict_types=1);

namespace Doogle\Security;

final readonly class RateLimitDecision
{
    public function __construct(
        public bool $allowed,
        public int $remainingAttempts,
        public int $retryAfterSeconds,
    ) {
    }
}
