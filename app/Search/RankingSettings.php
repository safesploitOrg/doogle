<?php

declare(strict_types=1);

namespace Doogle\Search;

final class RankingSettings
{
    private const TYPE_SITES = 'sites';
    private const TYPE_IMAGES = 'images';
    private const TYPE_VIDEOS = 'videos';

    /**
     * @var array<string, array<string, array{label: string, value: int}>>
     */
    private const WEIGHTS = [
        self::TYPE_SITES => [
            'title_exact' => ['label' => 'title exact match', 'value' => 240],
            'title_partial' => ['label' => 'title partial match', 'value' => 100],
            'keywords_partial' => ['label' => 'keywords partial match', 'value' => 60],
            'description_partial' => ['label' => 'description partial match', 'value' => 35],
            'url_partial' => ['label' => 'URL partial match', 'value' => 25],
            'https_url' => ['label' => 'HTTPS URL', 'value' => 5],
            'title_present' => ['label' => 'title present', 'value' => 5],
            'description_present' => ['label' => 'description present', 'value' => 3],
        ],
        self::TYPE_IMAGES => [
            'title_exact' => ['label' => 'title exact match', 'value' => 220],
            'alt_exact' => ['label' => 'alt exact match', 'value' => 180],
            'title_partial' => ['label' => 'title partial match', 'value' => 100],
            'alt_partial' => ['label' => 'alt partial match', 'value' => 75],
            'image_url_partial' => ['label' => 'image URL partial match', 'value' => 25],
            'https_image_url' => ['label' => 'HTTPS image URL', 'value' => 3],
            'alt_present' => ['label' => 'alt present', 'value' => 5],
            'title_present' => ['label' => 'title present', 'value' => 3],
        ],
        self::TYPE_VIDEOS => [
            'title_exact' => ['label' => 'title exact match', 'value' => 230],
            'title_partial' => ['label' => 'title partial match', 'value' => 100],
            'description_partial' => ['label' => 'description partial match', 'value' => 55],
            'video_url_partial' => ['label' => 'video URL partial match', 'value' => 25],
            'site_url_partial' => ['label' => 'site URL partial match', 'value' => 15],
            'https_video_url' => ['label' => 'HTTPS video URL', 'value' => 3],
            'thumbnail_present' => ['label' => 'thumbnail present', 'value' => 5],
            'title_present' => ['label' => 'title present', 'value' => 5],
            'description_present' => ['label' => 'description present', 'value' => 3],
        ],
    ];

    /**
     * @param array<string, int> $overrides
     */
    public function __construct(private readonly array $overrides = [])
    {
    }

    /**
     * @return array<string, int>
     */
    public function weightsFor(string $type): array
    {
        $weights = [];

        foreach ($this->definitionsFor($type) as $key => $definition) {
            $weights[$key] = $this->weight($type, $key);
        }

        return $weights;
    }

    public function weight(string $type, string $key): int
    {
        $type = $this->normalizeType($type);
        $settingKey = self::settingKey($type, $key);

        if (array_key_exists($settingKey, $this->overrides)) {
            return $this->overrides[$settingKey];
        }

        return self::WEIGHTS[$type][$key]['value'] ?? 0;
    }

    /**
     * @return list<RankingWeight>
     */
    public function weightDefinitionsFor(string $type): array
    {
        $type = $this->normalizeType($type);
        $weights = [];

        foreach (self::WEIGHTS[$type] as $key => $definition) {
            $weights[] = new RankingWeight(
                type: $type,
                key: $key,
                label: $definition['label'],
                value: $this->weight($type, $key),
                defaultValue: $definition['value'],
            );
        }

        return $weights;
    }

    /**
     * @return list<string>
     */
    public function supportedTypes(): array
    {
        return [self::TYPE_SITES, self::TYPE_IMAGES, self::TYPE_VIDEOS];
    }

    public function hasWeight(string $type, string $key): bool
    {
        $type = $this->normalizeType($type);

        return array_key_exists($key, self::WEIGHTS[$type]);
    }

    /**
     * @param array<string, int> $weights
     *
     * @return array<string, int>
     */
    public function sanitizeWeights(string $type, array $weights): array
    {
        $type = $this->normalizeType($type);
        $sanitized = [];

        foreach (self::WEIGHTS[$type] as $key => $definition) {
            $value = $weights[$key] ?? $definition['value'];
            $sanitized[$key] = max(0, min(1000, $value));
        }

        return $sanitized;
    }

    public static function settingKey(string $type, string $key): string
    {
        return 'weight.' . $type . '.' . $key;
    }

    public function clickBoostCap(): int
    {
        return RankingExpression::CLICK_BOOST_CAP;
    }

    public function clickBoostWeight(): float
    {
        return RankingExpression::CLICK_BOOST_WEIGHT;
    }

    public function clickBoostMaximum(): float
    {
        return $this->clickBoostCap() * $this->clickBoostWeight();
    }

    public function fullTextWeight(): int
    {
        return 50;
    }

    public function fullTextCap(): int
    {
        return 120;
    }

    /**
     * @return array<string, array{label: string, value: int}>
     */
    private function definitionsFor(string $type): array
    {
        return self::WEIGHTS[$this->normalizeType($type)];
    }

    private function normalizeType(string $type): string
    {
        if (array_key_exists($type, self::WEIGHTS)) {
            return $type;
        }

        return self::TYPE_SITES;
    }
}
