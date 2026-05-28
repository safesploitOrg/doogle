<?php

declare(strict_types=1);

namespace Doogle\Search;

final readonly class SearchPage
{
    /**
     * @param list<SearchResult> $results
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
