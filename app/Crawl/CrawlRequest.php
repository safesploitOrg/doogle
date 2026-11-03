<?php

declare(strict_types=1);

namespace Doogle\Crawl;

use Doogle\Security\CrawlerSecurityPolicy;

final readonly class CrawlRequest
{
    public function __construct(
        public string $startUrl,
        public int $maxDepth,
        public int $maxPages,
        public int $timeoutSeconds,
        public int $maxResponseBytes,
    ) {
    }

    public static function fromPolicy(string $startUrl, CrawlerSecurityPolicy $policy): self
    {
        return new self(
            startUrl: trim($startUrl),
            maxDepth: $policy->maxDepth(),
            maxPages: $policy->maxPages(),
            timeoutSeconds: $policy->timeoutSeconds(),
            maxResponseBytes: $policy->maxResponseBytes(),
        );
    }
}
