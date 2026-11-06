<?php

declare(strict_types=1);

namespace App\Core\Http;

use App\Core\Domain\Pagination\Page;

final class PaginationMeta
{
    /**
     * Extra top level members for a resource collection response.
     *
     * @param Page<mixed> $page
     * @return array{meta: array<string, int>}
     */
    public static function of(Page $page): array
    {
        return [
            'meta' => [
                'page' => $page->request->page,
                'per_page' => $page->request->perPage,
                'total' => $page->total,
                'total_pages' => $page->totalPages(),
            ],
        ];
    }
}
