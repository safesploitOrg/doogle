<?php

declare(strict_types=1);

namespace Doogle\Crawl;

final readonly class CrawlResult
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        public bool $successful,
        public int $pagesDiscovered,
        public int $pagesIndexed,
        public int $imagesIndexed,
        public int $urlsRejected,
        public array $errors = [],
        public string $output = '',
        public int $videosIndexed = 0,
    ) {
    }

    public static function rejected(string $reason): self
    {
        return new self(
            successful: false,
            pagesDiscovered: 0,
            pagesIndexed: 0,
            imagesIndexed: 0,
            urlsRejected: 1,
            errors: [$reason],
        );
    }

    public static function failed(string $reason, string $output = ''): self
    {
        return new self(
            successful: false,
            pagesDiscovered: 0,
            pagesIndexed: 0,
            imagesIndexed: 0,
            urlsRejected: 0,
            errors: [$reason],
            output: $output,
        );
    }
}
