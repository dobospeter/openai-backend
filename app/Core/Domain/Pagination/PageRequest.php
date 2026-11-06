<?php

declare(strict_types=1);

namespace App\Core\Domain\Pagination;

use App\Core\Domain\Exception\InvalidValue;

final class PageRequest
{
    public const DEFAULT_PER_PAGE = 15;
    public const MAX_PER_PAGE = 100;

    public function __construct(
        public readonly int $page = 1,
        public readonly int $perPage = self::DEFAULT_PER_PAGE
    ) {
        if ($page < 1) {
            throw InvalidValue::because('The page must be at least 1.');
        }

        if ($perPage < 1 || $perPage > self::MAX_PER_PAGE) {
            throw InvalidValue::because(sprintf('The page size must be between 1 and %d.', self::MAX_PER_PAGE));
        }
    }

    public function offset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
