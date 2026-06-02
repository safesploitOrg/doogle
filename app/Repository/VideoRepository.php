<?php

declare(strict_types=1);

namespace Doogle\Repository;

use PDO;
use PDOStatement;

final class VideoRepository implements VideoSearchRepository
{
    private const VIDEO_FULL_TEXT_COLUMNS = 'title, description, videoUrl, siteUrl';

    public function __construct(private readonly PDO $pdo)
    {
    }

    public function countBySearchTerm(string $term): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM videos
             WHERE ' . $this->videoWhereSql()
        );

        $this->bindVideoWhereTerms($statement, $term);
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
                    ' . $this->videoRankingSql() . ' AS rankingScore
             FROM videos
             WHERE ' . $this->videoWhereSql() . '
             ORDER BY rankingScore DESC, clicks DESC, id DESC
             LIMIT :fromLimit, :pageSize'
        );

        $this->bindVideoWhereTerms($statement, $term);
        $this->bindVideoRankingTerms($statement, $term);
        $statement->bindValue(':fromLimit', max(0, $offset), PDO::PARAM_INT);
        $statement->bindValue(':pageSize', max(0, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementClicksByUrl(string $videoUrl): bool
    {
        $statement = $this->pdo->prepare('UPDATE videos SET clicks = clicks + 1 WHERE videoUrl = :videoUrl');
        $statement->bindValue(':videoUrl', $videoUrl);

        return $statement->execute();
    }

    private function videoWhereSql(): string
    {
        $conditions = [
            'title LIKE :whereTitleTerm',
            'description LIKE :whereDescriptionTerm',
            'videoUrl LIKE :whereVideoUrlTerm',
            'siteUrl LIKE :whereSiteUrlTerm',
        ];

        if ($this->isMysql()) {
            array_unshift(
                $conditions,
                'MATCH(' . self::VIDEO_FULL_TEXT_COLUMNS . ') '
                    . 'AGAINST (:whereFullTextTerm IN NATURAL LANGUAGE MODE)'
            );
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function videoRankingSql(): string
    {
        $scores = [
            'CASE WHEN title LIKE :rankTitleTerm THEN 100 ELSE 0 END',
            'CASE WHEN description LIKE :rankDescriptionTerm THEN 60 ELSE 0 END',
            'CASE WHEN videoUrl LIKE :rankVideoUrlTerm THEN 30 ELSE 0 END',
            'CASE WHEN siteUrl LIKE :rankSiteUrlTerm THEN 10 ELSE 0 END',
            '(CASE WHEN clicks > 100 THEN 100 ELSE clicks END * 0.1)',
        ];

        if ($this->isMysql()) {
            array_unshift(
                $scores,
                '(MATCH(' . self::VIDEO_FULL_TEXT_COLUMNS . ') '
                    . 'AGAINST (:rankFullTextTerm IN NATURAL LANGUAGE MODE) * 50)'
            );
        }

        return '(' . implode(' + ', $scores) . ')';
    }

    private function bindVideoWhereTerms(PDOStatement $statement, string $term): void
    {
        if ($this->isMysql()) {
            $statement->bindValue(':whereFullTextTerm', $term);
        }

        $likeTerm = '%' . $term . '%';
        $statement->bindValue(':whereTitleTerm', $likeTerm);
        $statement->bindValue(':whereDescriptionTerm', $likeTerm);
        $statement->bindValue(':whereVideoUrlTerm', $likeTerm);
        $statement->bindValue(':whereSiteUrlTerm', $likeTerm);
    }

    private function bindVideoRankingTerms(PDOStatement $statement, string $term): void
    {
        if ($this->isMysql()) {
            $statement->bindValue(':rankFullTextTerm', $term);
        }

        $likeTerm = '%' . $term . '%';
        $statement->bindValue(':rankTitleTerm', $likeTerm);
        $statement->bindValue(':rankDescriptionTerm', $likeTerm);
        $statement->bindValue(':rankVideoUrlTerm', $likeTerm);
        $statement->bindValue(':rankSiteUrlTerm', $likeTerm);
    }

    private function isMysql(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
}
