<?php

declare(strict_types=1);

namespace Doogle\Repository;

interface VideoSearchRepository
{
    public function countBySearchTerm(string $term): int;

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $offset, int $limit): array;
}
