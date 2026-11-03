<?php

declare(strict_types=1);

namespace Doogle\Repository;

use Doogle\Search\RankingExpression;
use Doogle\Search\RankingSettings;
use PDO;
use PDOStatement;

final class SiteRepository implements SiteSearchRepository
{
    private const SITE_FULL_TEXT_COLUMNS = 'title, description, keywords, url';
    private readonly RankingSettings $rankingSettings;

    public function __construct(private readonly PDO $pdo, ?RankingSettings $rankingSettings = null)
    {
        $this->rankingSettings = $rankingSettings ?? new RankingSettings();
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
        $weights = $this->rankingSettings->weightsFor('sites');
        $scores = [
            RankingExpression::weightedEquals('title', ':rankTitleExactTerm', $weights['title_exact']),
            RankingExpression::weightedLike('title', ':rankTitleTerm', $weights['title_partial']),
            RankingExpression::weightedLike('keywords', ':rankKeywordsTerm', $weights['keywords_partial']),
            RankingExpression::weightedLike('description', ':rankDescriptionTerm', $weights['description_partial']),
            RankingExpression::weightedLike('url', ':rankUrlTerm', $weights['url_partial']),
            RankingExpression::httpsUrl('url', $weights['https_url']),
            RankingExpression::nonEmpty('title', $weights['title_present']),
            RankingExpression::nonEmpty('description', $weights['description_present']),
            RankingExpression::boundedClickBoost(),
        ];

        if ($this->isMysql()) {
            array_unshift(
                $scores,
                RankingExpression::boundedMysqlFullTextBoost(
                    self::SITE_FULL_TEXT_COLUMNS,
                    ':rankFullTextTerm',
                    $this->rankingSettings->fullTextWeight(),
                    $this->rankingSettings->fullTextCap()
                )
            );
        }

        return RankingExpression::sum($scores);
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
        $statement->bindValue(':rankTitleExactTerm', $term);
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
