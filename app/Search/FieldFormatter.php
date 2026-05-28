<?php

declare(strict_types=1);

namespace Doogle\Search;

final class FieldFormatter
{
    public function trim(string $value, int $characterLimit): string
    {
        $limit = max(0, $characterLimit);
        $dots = strlen($value) > $limit ? '...' : '';

        return substr($value, 0, $limit) . $dots;
    }
}
