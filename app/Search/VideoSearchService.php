<?php

declare(strict_types=1);

namespace Doogle\Search;

use Doogle\Repository\VideoSearchRepository;

final class VideoSearchService
{
    public function __construct(
        private readonly VideoSearchRepository $videos,
        private readonly Paginator $paginator,
    ) {
    }

    public function count(string $term): int
    {
        return $this->videos->countBySearchTerm($term);
    }

    public function search(string $term, int $page, int $pageSize): VideoSearchPage
    {
        $normalisedPage = $this->paginator->normalizePage($page);
        $offset = $this->paginator->offset($normalisedPage, $pageSize);
        $results = array_map(
            static fn (array $row): VideoResult => VideoResult::fromRow($row),
            $this->videos->search($term, $offset, $pageSize)
        );

        return new VideoSearchPage(
            results: $results,
            total: $this->videos->countBySearchTerm($term),
            page: $normalisedPage,
            pageSize: $pageSize,
        );
    }
}
