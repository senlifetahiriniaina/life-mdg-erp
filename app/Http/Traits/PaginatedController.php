<?php

declare(strict_types=1);

namespace App\Http\Traits;

use Illuminate\Pagination\Paginator;

trait PaginatedController
{
    protected function paginate($query, $perPage = 50)
    {
        $perPage = (int) request()->query('per_page', $perPage);
        $perPage = min($perPage, 200); // Max 200 items per page (increased from 100)

        return $query->paginate($perPage);
    }

    protected function paginateCollection($collection, $perPage = 50)
    {
        $perPage = (int) request()->query('per_page', $perPage);
        $page = Paginator::resolveCurrentPage();
        $total = $collection->count();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $collection->slice(($page - 1) * $perPage, $perPage)->values(),
            $total,
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()]
        );
    }
}
