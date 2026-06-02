<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Search;

use Doogle\Search\RankingExpression;
use PHPUnit\Framework\TestCase;

final class RankingExpressionTest extends TestCase
{
    public function testBuildsWeightedEqualsExpression(): void
    {
        self::assertSame(
            'CASE WHEN LOWER(title) = LOWER(:term) THEN 240 ELSE 0 END',
            RankingExpression::weightedEquals('title', ':term', 240)
        );
    }

    public function testBuildsBoundedClickBoostExpression(): void
    {
        self::assertSame(
            '(CASE WHEN clicks > 100 THEN 100 ELSE clicks END * 0.1)',
            RankingExpression::boundedClickBoost()
        );
    }

    public function testBuildsCappedMysqlFullTextBoost(): void
    {
        self::assertSame(
            'LEAST((MATCH(title, description) AGAINST (:term IN NATURAL LANGUAGE MODE) * 50), 120)',
            RankingExpression::boundedMysqlFullTextBoost('title, description', ':term', 50, 120)
        );
    }

    public function testCombinesScoreExpressions(): void
    {
        self::assertSame('(a + b + c)', RankingExpression::sum(['a', 'b', 'c']));
    }
}
