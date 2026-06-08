<?php

declare(strict_types=1);

namespace Doogle\Tests\Unit\Search;

use Doogle\Search\RankingSettings;
use PHPUnit\Framework\TestCase;

final class RankingSettingsTest extends TestCase
{
    public function testSupportedTypesMatchSearchVerticals(): void
    {
        self::assertSame(['sites', 'images', 'videos'], (new RankingSettings())->supportedTypes());
    }

    public function testClickBoostSettingsMatchRankingExpression(): void
    {
        $settings = new RankingSettings();

        self::assertSame(100, $settings->clickBoostCap());
        self::assertSame(0.1, $settings->clickBoostWeight());
        self::assertSame(10.0, $settings->clickBoostMaximum());
    }

    public function testReturnsPerVerticalWeights(): void
    {
        $settings = new RankingSettings();

        self::assertSame(240, $settings->weightsFor('sites')['title_exact']);
        self::assertSame(180, $settings->weightsFor('images')['alt_exact']);
        self::assertSame(230, $settings->weightsFor('videos')['title_exact']);
    }

    public function testUnknownTypeFallsBackToSiteWeights(): void
    {
        self::assertSame(
            (new RankingSettings())->weightsFor('sites'),
            (new RankingSettings())->weightsFor('unknown')
        );
    }

    public function testOverridesCanReplaceDefaultWeights(): void
    {
        $settings = new RankingSettings([
            RankingSettings::settingKey('sites', 'description_partial') => 500,
        ]);

        self::assertSame(500, $settings->weight('sites', 'description_partial'));
        self::assertSame(240, $settings->weight('sites', 'title_exact'));
    }

    public function testWeightDefinitionsExposeLabelsAndDefaults(): void
    {
        $definition = (new RankingSettings())->weightDefinitionsFor('sites')[0];

        self::assertSame('sites', $definition->type);
        self::assertSame('title_exact', $definition->key);
        self::assertSame('title exact match', $definition->label);
        self::assertSame(240, $definition->value);
        self::assertSame(240, $definition->defaultValue);
        self::assertSame('weight.sites.title_exact', $definition->settingKey());
        self::assertTrue($definition->isDefault());
    }

    public function testSanitizeWeightsClampsValuesAndFillsMissingDefaults(): void
    {
        $weights = (new RankingSettings())->sanitizeWeights('sites', [
            'title_exact' => 2000,
            'title_partial' => -10,
        ]);

        self::assertSame(1000, $weights['title_exact']);
        self::assertSame(0, $weights['title_partial']);
        self::assertSame(60, $weights['keywords_partial']);
    }
}
