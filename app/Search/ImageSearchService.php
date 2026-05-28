<?php

declare(strict_types=1);

namespace Doogle\Search;

use Doogle\Repository\ImageSearchRepository;

final class ImageSearchService
{
    public function __construct(
        private readonly ImageSearchRepository $images,
        private readonly Paginator $paginator,
    ) {
    }

    public function count(string $term): int
    {
        return $this->images->countBySearchTerm($term);
    }

    public function search(string $term, int $page, int $pageSize): ImageSearchPage
    {
        $normalisedPage = $this->paginator->normalizePage($page);
        $offset = $this->paginator->offset($normalisedPage, $pageSize);
        $results = array_map(
            static fn (array $row): ImageResult => ImageResult::fromRow($row),
            $this->images->search($term, $offset, $pageSize)
        );

        return new ImageSearchPage(
            results: $results,
            total: $this->images->countBySearchTerm($term),
            page: $normalisedPage,
            pageSize: $pageSize,
        );
    }
}
