<?php

declare(strict_types=1);

namespace Doogle\Repository;

use Doogle\Search\RankingSettings;
use PDO;

final class RankingSettingsRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function load(): RankingSettings
    {
        $statement = $this->pdo->query(
            'SELECT setting_key, setting_value
             FROM ranking_settings'
        );

        if ($statement === false) {
            return new RankingSettings();
        }

        $overrides = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $key = (string) ($row['setting_key'] ?? '');

            if ($key === '') {
                continue;
            }

            $overrides[$key] = (int) ($row['setting_value'] ?? 0);
        }

        return new RankingSettings($overrides);
    }

    /**
     * @param array<string, int> $weights
     */
    public function saveWeights(string $type, array $weights): void
    {
        $settings = new RankingSettings();
        $type = in_array($type, $settings->supportedTypes(), true) ? $type : 'sites';
        $sanitizedWeights = $settings->sanitizeWeights($type, $weights);

        foreach ($sanitizedWeights as $key => $value) {
            $this->upsertSetting(RankingSettings::settingKey($type, $key), (string) $value);
        }
    }

    private function upsertSetting(string $key, string $value): void
    {
        if ($this->isMysql()) {
            $statement = $this->pdo->prepare(
                'INSERT INTO ranking_settings (setting_key, setting_value)
                 VALUES (:setting_key, :setting_value)
                 ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP'
            );
        } else {
            $statement = $this->pdo->prepare(
                'INSERT OR REPLACE INTO ranking_settings (setting_key, setting_value, updated_at)
                 VALUES (:setting_key, :setting_value, CURRENT_TIMESTAMP)'
            );
        }

        $statement->execute([
            ':setting_key' => $key,
            ':setting_value' => $value,
        ]);
    }

    private function isMysql(): bool
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql';
    }
}
