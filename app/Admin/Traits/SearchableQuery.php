<?php

namespace App\Admin\Traits;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait SearchableQuery
{
    public function applySearchAndPaginate(
        Builder $query,
        Request $request,
        array $searchable = [],
        string $defaultSort = 'created_at',
        string $defaultDir = 'desc',
        int $perPage = 15,
        array $sortable = [],
    ): LengthAwarePaginator {
        // Whitelist sortable columns to prevent SQL errors / info leaks from
        // arbitrary `sort` input. Defaults to the searchable columns plus id/timestamps.
        $allowedSorts = array_unique(array_merge(
            $sortable ?: $searchable,
            ['id', 'created_at', 'updated_at', $defaultSort],
        ));

        $sort = in_array($request->sort, $allowedSorts, true) ? $request->sort : null;
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        // Cap page size to avoid unbounded/expensive queries.
        $requestedPerPage = (int) ($request->per_page ?? $perPage);
        $perPage = max(1, min($requestedPerPage, 100));

        return $query
            ->when($request->search && count($searchable) > 0, function ($q) use ($request, $searchable) {
                $q->where(function ($q) use ($request, $searchable) {
                    foreach ($searchable as $column) {
                        $q->orWhere($column, 'like', "%{$request->search}%");
                    }
                });
            })
            ->when(
                $sort,
                fn ($q) => $q->orderBy($sort, $direction),
                fn ($q) => $q->orderBy($defaultSort, $defaultDir),
            )
            ->paginate($perPage)
            ->withQueryString();
    }
}
