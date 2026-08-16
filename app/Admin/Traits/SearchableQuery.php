<?php

namespace App\Admin\Traits;

use App\Admin\Query\SortIndexGuard;
use App\Support\Sql;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait SearchableQuery
{
    /** Upper bound on the requested page number. */
    public const MAX_PAGE = 10000;

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

        // >>> MYRA v2.4 [D] — dev-only, off by default, never throws in production.
        SortIndexGuard::assert($query->getModel(), $sort, $sortable);
        // <<< MYRA v2.4 [D]

        // Cap page size to avoid unbounded/expensive queries.
        $requestedPerPage = (int) ($request->per_page ?? $perPage);
        $perPage = max(1, min($requestedPerPage, 100));

        // Cap the page number so `?page=500000` cannot issue a multi-million-row OFFSET.
        $page = max(1, min((int) ($request->page ?: 1), self::MAX_PAGE));

        return $query
            ->when($request->search && count($searchable) > 0, function ($q) use ($request, $searchable) {
                $q->where(function ($q) use ($request, $searchable) {
                    foreach ($searchable as $column) {
                        // Escape % _ \ so a user's wildcard cannot widen the match
                        // or force a full scan.
                        Sql::orWhereLike($q, $column, (string) $request->search);
                    }
                });
            })
            ->when(
                $sort,
                fn ($q) => $q->orderBy($sort, $direction),
                fn ($q) => $q->orderBy($defaultSort, $defaultDir),
            )
            // >>> MYRA v2.4 [D] — OFF by default: enabling it changes the SQL of every
            // existing admin table (same row set, different page for a tied row).
            ->when(
                config('myra.performance.stable_sort', false),
                fn ($q) => $q->orderBy($query->getModel()->getQualifiedKeyName(), $direction),
            )
            // <<< MYRA v2.4 [D]
            ->paginate($perPage, ['*'], 'page', $page)
            ->withQueryString();
    }

    /**
     * Whole-dataset summaries for a table footer (column => summary type).
     * The spec is always a server-side literal, never derived from the request.
     *
     * @param  array<string,string>  $spec  e.g. ['price' => 'sum', 'stock' => 'min']
     * @return array<string,mixed>
     */
    protected function summarise(Builder $query, array $spec): array
    {
        $out = [];

        foreach ($spec as $column => $type) {
            $q = clone $query;
            $out[$column] = match ($type) {
                'sum' => (float) $q->sum($column),
                'average' => (float) $q->avg($column),
                'count' => (int) $q->count($column),
                'min' => $q->min($column),
                'max' => $q->max($column),
                'range' => trim(($q->min($column) ?? '—') . ' – ' . ((clone $query)->max($column) ?? '—')),
                default => null,
            };
        }

        return $out;
    }
}
