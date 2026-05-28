<?php

declare(strict_types=1);

namespace Doogle\Crawl;

final class UrlNormalizer
{
    public function normalize(string $source, string $baseUrl): string
    {
        $baseParts = parse_url($baseUrl);

        if (!is_array($baseParts)) {
            return $source;
        }

        $scheme = (string) ($baseParts['scheme'] ?? '');
        $host = (string) ($baseParts['host'] ?? '');
        $path = (string) ($baseParts['path'] ?? '');

        if (substr($source, 0, 2) === '//') {
            return $scheme . ':' . $source;
        }

        if (substr($source, 0, 1) === '/') {
            return $scheme . '://' . $host . $source;
        }

        if (substr($source, 0, 2) === './') {
            return $scheme . '://' . $host . dirname($path) . substr($source, 1);
        }

        if (substr($source, 0, 3) === '../') {
            return $scheme . '://' . $host . '/' . $source;
        }

        if (substr($source, 0, 5) !== 'https' && substr($source, 0, 4) !== 'http') {
            return $scheme . '://' . $host . '/' . $source;
        }

        return $source;
    }
}
