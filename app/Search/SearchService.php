<?php

declare(strict_types=1);

namespace Doogle\Search;

use Doogle\Repository\SiteSearchRepository;

final class SearchService
{
    public function __construct(
        private readonly SiteSearchRepository $sites,
        private readonly Paginator $paginator,
    ) {
    }

    public function count(string $term): int
    {
        return $this->sites->countBySearchTerm($term);
    }

    public function search(string $term, int $page, int $pageSize): SearchPage
    {
        $normalisedPage = $this->paginator->normalizePage($page);
        $offset = $this->paginator->offset($normalisedPage, $pageSize);
        $results = array_map(
            static fn (array $row): SearchResult => SearchResult::fromRow($row),
            $this->sites->search($term, $offset, $pageSize)
        );

        return new SearchPage(
            results: $results,
            total: $this->sites->countBySearchTerm($term),
            page: $normalisedPage,
            pageSize: $pageSize,
        );
    }
}
