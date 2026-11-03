<?php

declare(strict_types=1);

namespace Doogle\Search;

final readonly class ImageSearchPage
{
    /**
     * @param list<ImageResult> $results
     */
    public function __construct(
        public array $results,
        public int $total,
        public int $page,
        public int $pageSize,
    ) {
    }

    public static function empty(int $page, int $pageSize): self
    {
        return new self([], 0, $page, $pageSize);
    }
}
