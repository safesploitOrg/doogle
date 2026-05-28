<?php

declare(strict_types=1);

namespace Doogle\Repository;

use PDO;
use PDOStatement;

final class SiteRepository implements SiteSearchRepository
{
    private const SITE_FULL_TEXT_COLUMNS = 'title, description, keywords, url';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function countBySearchTerm(string $term): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM sites
             WHERE ' . $this->siteWhereSql()
        );

        $this->bindSiteWhereTerms($statement, $term);
        $statement->execute();

        return (int) $statement->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function search(string $term, int $offset, int $limit): array
    {
        $statement = $this->pdo->prepare(
            'SELECT *,
                    ' . $this->siteRankingSql() . ' AS rankingScore
             FROM sites
             WHERE ' . $this->siteWhereSql() . '
             ORDER BY rankingScore DESC, clicks DESC, id DESC
             LIMIT :fromLimit, :pageSize'
        );

        $this->bindSiteWhereTerms($statement, $term);
        $this->bindSiteRankingTerms($statement, $term);
        $statement->bindValue(':fromLimit', max(0, $offset), PDO::PARAM_INT);
        $statement->bindValue(':pageSize', max(0, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementClicks(int|string $id): bool
    {
        $statement = $this->pdo->prepare('UPDATE sites SET clicks = clicks + 1 WHERE id = :id');
        $statement->bindValue(':id', $id);

        return $statement->execute();
    }

    private function siteWhereSql(): string
    {
        $conditions = [
            'title LIKE :whereTitleTerm',
            'url LIKE :whereUrlTerm',
            'keywords LIKE :whereKeywordsTerm',
            'description LIKE :whereDescriptionTerm',
        ];

        if ($this->isMysql()) {
            array_unshift(
                $conditions,
                'MATCH(' . self::SITE_FULL_TEXT_COLUMNS . ') '
                    . 'AGAINST (:whereFullTextTerm IN NATURAL LANGUAGE MODE)'
            );
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function siteRankingSql(): string
    {
        $scores = [
            'CASE WHEN title LIKE :rankTitleTerm THEN 100 ELSE 0 END',
            'CASE WHEN keywords LIKE :rankKeywordsTerm THEN 60 ELSE 0 END',
            'CASE WHEN description LIKE :rankDescriptionTerm THEN 30 ELSE 0 END',
            'CASE WHEN url LIKE :rankUrlTerm THEN 20 ELSE 0 END',
            '(CASE WHEN clicks > 100 THEN 100 ELSE clicks END * 0.1)',
        ];

        if ($this->isMysql()) {
            array_unshift(
                $scores,
                '(MATCH(' . self::SITE_FULL_TEXT_COLUMNS . ') '
                    . 'AGAINST (:rankFullTextTerm IN NATURAL LANGUAGE MODE) * 50)'
            );
        }

        return '(' . implode(' + ', $scores) . ')';
    }

    private function bindSiteWhereTerms(PDOStatement $statement, string $term): void
    {
        if ($this->isMysql()) {
            $statement->bindValue(':whereFullTextTerm', $term);
        }

        $likeTerm = '%' . $term . '%';
        $statement->bindValue(':whereTitleTerm', $likeTerm);
        $statement->bindValue(':whereUrlTerm', $likeTerm);
        $statement->bindValue(':whereKeywordsTerm', $likeTerm);
        $statement->bindValue(':whereDescriptionTerm', $likeTerm);
    }

    private function bindSiteRankingTerms(PDOStatement $statement, string $term): void
    {
        if ($this->isMysql()) {
            $statement->bindValue(':rankFullTextTerm', $term);
        }

        $likeTerm = '%' . $term . '%';
        $statement->bindValue(':rankTitleTerm', $likeTerm);
        $statement->bindValue(':rankKeywordsTerm', $likeTerm);
        $statement->bindValue(':rankDescriptionTerm', $likeTerm);
        $statement->bindValue(':rankUrlTerm', $likeTerm);
    }

    private function isMysql(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
}
