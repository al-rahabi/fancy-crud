<?php

namespace FancyCrud\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * The same meta block CrudController::index() builds, for hand-written
 * controllers that paginate directly.
 */
trait PaginationMetaTrait
{
    /**
     * @return array{current_page: int, total: int, per_page: int, last_page: int, pages_count: int}
     */
    protected function paginationMeta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(),
            'total' => $page->total(),
            'per_page' => $page->perPage(),
            'last_page' => $page->lastPage(),
            'pages_count' => $page->lastPage(),
        ];
    }
}
