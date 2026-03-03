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
    ): LengthAwarePaginator {
        return $query
            ->when($request->search && count($searchable) > 0, function ($q) use ($request, $searchable) {
                $q->where(function ($q) use ($request, $searchable) {
                    foreach ($searchable as $column) {
                        $q->orWhere($column, 'like', "%{$request->search}%");
                    }
                });
            })
            ->when(
                $request->sort,
                fn ($q, $sort) => $q->orderBy($sort, $request->direction === 'asc' ? 'asc' : 'desc'),
                fn ($q) => $q->orderBy($defaultSort, $defaultDir),
            )
            ->paginate($request->per_page ?? $perPage)
            ->withQueryString();
    }
}
