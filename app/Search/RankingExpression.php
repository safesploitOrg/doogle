<?php

declare(strict_types=1);

namespace Doogle\Search;

final class RankingExpression
{
    public const CLICK_BOOST_CAP = 100;
    public const CLICK_BOOST_WEIGHT = 0.1;

    public static function sum(array $expressions): string
    {
        return '(' . implode(' + ', $expressions) . ')';
    }

    public static function weightedEquals(string $column, string $parameter, int $weight): string
    {
        return "CASE WHEN LOWER({$column}) = LOWER({$parameter}) THEN {$weight} ELSE 0 END";
    }

    public static function weightedLike(string $column, string $parameter, int $weight): string
    {
        return "CASE WHEN {$column} LIKE {$parameter} THEN {$weight} ELSE 0 END";
    }

    public static function nonEmpty(string $column, int $weight): string
    {
        return "CASE WHEN {$column} <> '' THEN {$weight} ELSE 0 END";
    }

    public static function httpsUrl(string $column, int $weight): string
    {
        return "CASE WHEN {$column} LIKE 'https://%' THEN {$weight} ELSE 0 END";
    }

    public static function boundedClickBoost(string $column = 'clicks'): string
    {
        return '(CASE WHEN ' . $column . ' > ' . self::CLICK_BOOST_CAP
            . ' THEN ' . self::CLICK_BOOST_CAP . ' ELSE ' . $column . ' END * '
            . self::CLICK_BOOST_WEIGHT . ')';
    }

    public static function boundedMysqlFullTextBoost(
        string $columns,
        string $parameter,
        int $weight,
        int $cap,
    ): string {
        $score = 'MATCH(' . $columns . ') AGAINST (' . $parameter . ' IN NATURAL LANGUAGE MODE) * ' . $weight;

        return 'LEAST((' . $score . '), ' . $cap . ')';
    }
}
