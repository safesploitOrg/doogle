<?php

declare(strict_types=1);

namespace Doogle\Repository;

use Doogle\Search\RankingExpression;
use Doogle\Search\RankingSettings;
use PDO;
use PDOStatement;

final class ImageRepository implements ImageSearchRepository
{
    private const IMAGE_FULL_TEXT_COLUMNS = 'title, alt, imageUrl';
    private readonly RankingSettings $rankingSettings;

    public function __construct(private readonly PDO $pdo, ?RankingSettings $rankingSettings = null)
    {
        $this->rankingSettings = $rankingSettings ?? new RankingSettings();
    }

    public function countBySearchTerm(string $term): int
    {
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS total
             FROM images
             WHERE ' . $this->imageWhereSql() . '
               AND broken = 0'
        );

        $this->bindImageWhereTerms($statement, $term);
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
                    ' . $this->imageRankingSql() . ' AS rankingScore
             FROM images
             WHERE ' . $this->imageWhereSql() . '
               AND broken = 0
             ORDER BY rankingScore DESC, clicks DESC, id DESC
             LIMIT :fromLimit, :pageSize'
        );

        $this->bindImageWhereTerms($statement, $term);
        $this->bindImageRankingTerms($statement, $term);
        $statement->bindValue(':fromLimit', max(0, $offset), PDO::PARAM_INT);
        $statement->bindValue(':pageSize', max(0, $limit), PDO::PARAM_INT);
        $statement->execute();

        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function incrementClicksByUrl(string $imageUrl): bool
    {
        $statement = $this->pdo->prepare('UPDATE images SET clicks = clicks + 1 WHERE imageUrl = :imageUrl');
        $statement->bindValue(':imageUrl', $imageUrl);

        return $statement->execute();
    }

    public function markBroken(string $imageUrl): bool
    {
        $statement = $this->pdo->prepare('UPDATE images SET broken = 1 WHERE imageUrl = :src');
        $statement->bindValue(':src', $imageUrl);

        return $statement->execute();
    }

    private function imageWhereSql(): string
    {
        $conditions = [
            'title LIKE :whereTitleTerm',
            'alt LIKE :whereAltTerm',
            'imageUrl LIKE :whereImageUrlTerm',
        ];

        if ($this->isMysql()) {
            array_unshift(
                $conditions,
                'MATCH(' . self::IMAGE_FULL_TEXT_COLUMNS . ') '
                    . 'AGAINST (:whereFullTextTerm IN NATURAL LANGUAGE MODE)'
            );
        }

        return '(' . implode(' OR ', $conditions) . ')';
    }

    private function imageRankingSql(): string
    {
        $weights = $this->rankingSettings->weightsFor('images');
        $scores = [
            RankingExpression::weightedEquals('title', ':rankTitleExactTerm', $weights['title_exact']),
            RankingExpression::weightedEquals('alt', ':rankAltExactTerm', $weights['alt_exact']),
            RankingExpression::weightedLike('title', ':rankTitleTerm', $weights['title_partial']),
            RankingExpression::weightedLike('alt', ':rankAltTerm', $weights['alt_partial']),
            RankingExpression::weightedLike('imageUrl', ':rankImageUrlTerm', $weights['image_url_partial']),
            RankingExpression::httpsUrl('imageUrl', $weights['https_image_url']),
            RankingExpression::nonEmpty('alt', $weights['alt_present']),
            RankingExpression::nonEmpty('title', $weights['title_present']),
            RankingExpression::boundedClickBoost(),
        ];

        if ($this->isMysql()) {
            array_unshift(
                $scores,
                RankingExpression::boundedMysqlFullTextBoost(
                    self::IMAGE_FULL_TEXT_COLUMNS,
                    ':rankFullTextTerm',
                    $this->rankingSettings->fullTextWeight(),
                    $this->rankingSettings->fullTextCap()
                )
            );
        }

        return RankingExpression::sum($scores);
    }

    private function bindImageWhereTerms(PDOStatement $statement, string $term): void
    {
        if ($this->isMysql()) {
            $statement->bindValue(':whereFullTextTerm', $term);
        }

        $likeTerm = '%' . $term . '%';
        $statement->bindValue(':whereTitleTerm', $likeTerm);
        $statement->bindValue(':whereAltTerm', $likeTerm);
        $statement->bindValue(':whereImageUrlTerm', $likeTerm);
    }

    private function bindImageRankingTerms(PDOStatement $statement, string $term): void
    {
        if ($this->isMysql()) {
            $statement->bindValue(':rankFullTextTerm', $term);
        }

        $likeTerm = '%' . $term . '%';
        $statement->bindValue(':rankTitleExactTerm', $term);
        $statement->bindValue(':rankAltExactTerm', $term);
        $statement->bindValue(':rankTitleTerm', $likeTerm);
        $statement->bindValue(':rankAltTerm', $likeTerm);
        $statement->bindValue(':rankImageUrlTerm', $likeTerm);
    }

    private function isMysql(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
}
