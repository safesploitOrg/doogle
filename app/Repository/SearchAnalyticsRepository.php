<?php

declare(strict_types=1);

namespace Doogle\Repository;

use Doogle\Search\SearchAnalyticsEvent;
use Doogle\Search\SearchAnalyticsTerm;
use Doogle\Search\SearchAnalyticsTypeSummary;
use PDO;

final class SearchAnalyticsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(
        string $term,
        string $type,
        int $resultCount,
        int $page,
        string $ipHash,
        string $userAgentHash,
    ): bool {
        $statement = $this->pdo->prepare(
            'INSERT INTO search_queries (term, type, result_count, page, ip_hash, user_agent_hash)
             VALUES (:term, :type, :result_count, :page, :ip_hash, :user_agent_hash)'
        );

        return $statement->execute([
            ':term' => substr($term, 0, 255),
            ':type' => $type,
            ':result_count' => max(0, $resultCount),
            ':page' => max(1, $page),
            ':ip_hash' => $ipHash,
            ':user_agent_hash' => $userAgentHash,
        ]);
    }

    /**
     * @return list<SearchAnalyticsEvent>
     */
    public function recent(int $limit = 25): array
    {
        $statement = $this->pdo->prepare(
            'SELECT id, term, type, result_count, page, ip_hash, user_agent_hash, created_at
             FROM search_queries
             ORDER BY id DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): SearchAnalyticsEvent => SearchAnalyticsEvent::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return list<SearchAnalyticsTerm>
     */
    public function topTerms(int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            'SELECT term,
                    type,
                    COUNT(*) AS searches,
                    AVG(result_count) AS average_result_count,
                    MAX(created_at) AS last_searched_at
             FROM search_queries
             GROUP BY term, type
             ORDER BY searches DESC, last_searched_at DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): SearchAnalyticsTerm => SearchAnalyticsTerm::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return list<SearchAnalyticsTerm>
     */
    public function zeroResultTerms(int $limit = 10): array
    {
        $statement = $this->pdo->prepare(
            'SELECT term,
                    type,
                    COUNT(*) AS searches,
                    AVG(result_count) AS average_result_count,
                    MAX(created_at) AS last_searched_at
             FROM search_queries
             WHERE result_count = 0
             GROUP BY term, type
             ORDER BY searches DESC, last_searched_at DESC
             LIMIT :limit'
        );
        $statement->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $statement->execute();

        return array_map(
            static fn (array $row): SearchAnalyticsTerm => SearchAnalyticsTerm::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return list<SearchAnalyticsTypeSummary>
     */
    public function typeSummary(): array
    {
        $statement = $this->pdo->prepare(
            'SELECT type,
                    COUNT(*) AS searches,
                    SUM(CASE WHEN result_count = 0 THEN 1 ELSE 0 END) AS zero_result_searches,
                    AVG(result_count) AS average_result_count
             FROM search_queries
             GROUP BY type
             ORDER BY searches DESC, type ASC'
        );
        $statement->execute();

        return array_map(
            static fn (array $row): SearchAnalyticsTypeSummary => SearchAnalyticsTypeSummary::fromRow($row),
            $statement->fetchAll(PDO::FETCH_ASSOC)
        );
    }
}
