<?php

namespace App\Admin\Traits;

use App\Admin\Query\SortIndexGuard;
use App\Support\Sql;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Cursor sibling of SearchableQuery::applySearchAndPaginate. No OFFSET, no
 * COUNT(*) - the two ways a length-aware page 5000 gets expensive.
 *
 * It is a SIBLING, never a replacement: saved views, deep links and meta.links
 * all depend on the length-aware path.
 */
trait CursorPaginatedQuery
{
    public function applySearchAndCursorPaginate(
        Builder $query,
        Request $request,
        array $searchable = [],
        string $defaultSort = 'created_at',
        string $defaultDir = 'desc',
        int $perPage = 50,
        array $sortable = [],
        string $cursorName = 'cursor',
    ): CursorPaginator {
        $allowedSorts = array_unique(array_merge(
            $sortable ?: $searchable,
            ['id', 'created_at', 'updated_at', $defaultSort],
        ));

        $sort = in_array($request->sort, $allowedSorts, true) ? $request->sort : null;
        $direction = $request->direction === 'asc' ? 'asc' : 'desc';

        $requestedPerPage = (int) ($request->per_page ?? $perPage);
        $perPage = max(1, min($requestedPerPage, 100));

        SortIndexGuard::assert($query->getModel(), $sort, $sortable);

        return $query
            ->when($request->search && count($searchable) > 0, function ($q) use ($request, $searchable) {
                $q->where(function ($q) use ($request, $searchable) {
                    foreach ($searchable as $column) {
                        Sql::orWhereLike($q, $column, (string) $request->search);
                    }
                });
            })
            ->when(
                $sort,
                fn ($q) => $q->orderBy($sort, $direction),
                fn ($q) => $q->orderBy($defaultSort, $defaultDir),
            )
            // MANDATORY on the cursor path: without a unique tiebreak, a cursor over
            // a non-unique sort column silently skips or repeats rows.
            ->orderBy($query->getModel()->getQualifiedKeyName(), $sort ? $direction : $defaultDir)
            ->cursorPaginate($perPage, ['*'], $cursorName)
            ->withQueryString();
    }
}
