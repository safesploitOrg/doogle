<?php

declare(strict_types=1);

namespace Doogle\Search;

final class Paginator
{
    public function normalizePage(int $page): int
    {
        return max(1, $page);
    }

    public function offset(int $page, int $pageSize): int
    {
        if ($pageSize < 1) {
            return 0;
        }

        return ($this->normalizePage($page) - 1) * $pageSize;
    }
}
