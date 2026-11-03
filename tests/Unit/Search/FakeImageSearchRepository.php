<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Search;

use Doogle\Repository\ImageSearchRepository;

final class FakeImageSearchRepository implements ImageSearchRepository
{
    /**
     * @param list<array<string, mixed>> $rows
     */
    public function __construct(
        private readonly int $count = 0,
        private readonly array $rows = [],
    ) {
    }

    /** @var list<string> */
    public array $countTerms = [];

    /** @var list<array{0: string, 1: int, 2: int}> */
    public array $searchCalls = [];

    public function countBySearchTerm(string $term): int
    {
        $this->countTerms[] = $term;

        return $this->count;
    }

    public function search(string $term, int $offset, int $limit): array
    {
        $this->searchCalls[] = [$term, $offset, $limit];

        return $this->rows;
    }
}
