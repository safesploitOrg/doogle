<?php

declare(strict_types=1);

namespace Doogle\Repository;

use Doogle\Search\RankingExpression;
use Doogle\Search\RankingSettings;
use PDO;
use PDOStatement;

final class VideoRepository implements VideoSearchRepository
{
    private const VIDEO_FULL_TEXT_COLUMNS = 'title, description, videoUrl, siteUrl';
    private readonly RankingSettings $rankingSettings;

    public function __construct(private readonly PDO $pdo, ?RankingSettings $rankingSettings = null)
    {
        $this->rankingSettings = $rankingSettings ?? new RankingSettings();
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
        $weights = $this->rankingSettings->weightsFor('videos');
        $scores = [
            RankingExpression::weightedEquals('title', ':rankTitleExactTerm', $weights['title_exact']),
            RankingExpression::weightedLike('title', ':rankTitleTerm', $weights['title_partial']),
            RankingExpression::weightedLike('description', ':rankDescriptionTerm', $weights['description_partial']),
            RankingExpression::weightedLike('videoUrl', ':rankVideoUrlTerm', $weights['video_url_partial']),
            RankingExpression::weightedLike('siteUrl', ':rankSiteUrlTerm', $weights['site_url_partial']),
            RankingExpression::httpsUrl('videoUrl', $weights['https_video_url']),
            RankingExpression::nonEmpty('thumbnailUrl', $weights['thumbnail_present']),
            RankingExpression::nonEmpty('title', $weights['title_present']),
            RankingExpression::nonEmpty('description', $weights['description_present']),
            RankingExpression::boundedClickBoost(),
        ];

        if ($this->isMysql()) {
            array_unshift(
                $scores,
                RankingExpression::boundedMysqlFullTextBoost(
                    self::VIDEO_FULL_TEXT_COLUMNS,
                    ':rankFullTextTerm',
                    $this->rankingSettings->fullTextWeight(),
                    $this->rankingSettings->fullTextCap()
                )
            );
        }

        return RankingExpression::sum($scores);
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
        $statement->bindValue(':rankTitleExactTerm', $term);
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
