<?php

declare(strict_types=1);

namespace Doogle\Crawl;

use Doogle\Security\CrawlerSecurityPolicy;
use Doogle\Security\PrivateNetworkBlocker;

final class UrlValidator
{
    private CrawlerSecurityPolicy $policy;
    private PrivateNetworkBlocker $privateNetworkBlocker;

    public function __construct(
        ?CrawlerSecurityPolicy $policy = null,
        ?PrivateNetworkBlocker $privateNetworkBlocker = null,
    ) {
        $this->policy = $policy ?? CrawlerSecurityPolicy::fromEnvironment();
        $this->privateNetworkBlocker = $privateNetworkBlocker ?? new PrivateNetworkBlocker();
    }

    public function isAllowed(string $url, int $depth = 0): bool
    {
        return $this->rejectionReason($url, $depth) === null;
    }

    public function rejectionReason(string $url, int $depth = 0): ?string
    {
        $url = trim($url);

        if ($url === '') {
            return 'empty URL';
        }

        if (!$this->policy->allowsDepth($depth)) {
            return 'maximum crawl depth exceeded';
        }

        $parts = parse_url($url);

        if (!is_array($parts) || !isset($parts['scheme'])) {
            return 'URL must include a scheme and host';
        }

        if (!$this->policy->allowsScheme((string) $parts['scheme'])) {
            return 'unsupported URL scheme';
        }

        if (!isset($parts['host'])) {
            return 'URL must include a scheme and host';
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'credentials in URLs are not allowed';
        }

        if (
            !$this->policy->allowsPrivateNetworks()
            && $this->privateNetworkBlocker->isBlockedHost((string) $parts['host'])
        ) {
            return 'private or reserved network host';
        }

        return null;
    }
}
