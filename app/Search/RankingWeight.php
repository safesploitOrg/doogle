<?php

declare(strict_types=1);

namespace Doogle\Search;

final class RankingWeight
{
    public function __construct(
        public readonly string $type,
        public readonly string $key,
        public readonly string $label,
        public readonly int $value,
        public readonly int $defaultValue,
    ) {
    }

    public function settingKey(): string
    {
        return RankingSettings::settingKey($this->type, $this->key);
    }

    public function isDefault(): bool
    {
        return $this->value === $this->defaultValue;
    }
}
