<?php

declare(strict_types=1);

namespace Doogle\Tests\Integration\Repository;

use Doogle\Repository\RankingSettingsRepository;
use Doogle\Search\RankingSettings;
use PDO;
use PHPUnit\Framework\TestCase;

final class RankingSettingsRepositoryTest extends TestCase
{
    private PDO $pdo;
    private RankingSettingsRepository $repository;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE ranking_settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value VARCHAR(100) NOT NULL,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )'
        );
        $this->repository = new RankingSettingsRepository($this->pdo);
    }

    public function testLoadReturnsDefaultsWhenNoOverridesExist(): void
    {
        $settings = $this->repository->load();

        self::assertSame(240, $settings->weight('sites', 'title_exact'));
    }

    public function testSaveWeightsPersistsSanitizedOverrides(): void
    {
        $this->repository->saveWeights('sites', [
            'title_exact' => 300,
            'description_partial' => 2000,
            'unknown' => 900,
        ]);

        $settings = $this->repository->load();

        self::assertSame(300, $settings->weight('sites', 'title_exact'));
        self::assertSame(1000, $settings->weight('sites', 'description_partial'));
        self::assertSame(0, $settings->weight('sites', 'unknown'));
    }

    public function testSaveWeightsCanUpdateExistingValues(): void
    {
        $this->repository->saveWeights('sites', ['title_exact' => 300]);
        $this->repository->saveWeights('sites', ['title_exact' => 120]);

        $settings = $this->repository->load();

        self::assertSame(120, $settings->weight('sites', 'title_exact'));
        self::assertSame(
            1,
            (int) $this->pdo
                ->query("SELECT COUNT(*) FROM ranking_settings WHERE setting_key = 'weight.sites.title_exact'")
                ->fetchColumn()
        );
    }

    public function testSettingKeyIsStable(): void
    {
        self::assertSame(
            'weight.videos.title_exact',
            RankingSettings::settingKey('videos', 'title_exact')
        );
    }
}
