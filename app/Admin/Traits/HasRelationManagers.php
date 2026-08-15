<?php

namespace App\Admin\Traits;

use App\Support\Sql;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait HasRelationManagers
{
    /**
     * Paginate a relation with search, sort, and pagination support.
     *
     * @param  Model   $model        The parent model
     * @param  string  $relation     The relation name
     * @param  Request $request      The current request
     * @param  array   $searchable   Columns to search
     * @param  int     $perPage      Items per page
     * @param  string  $prefix       Query parameter prefix (for multiple relation managers on one page)
     * @param  array   $sortable     Columns the client may sort by; defaults to $searchable
     * @param  string  $defaultSort  Fallback column for an unlisted sort
     */
    protected function paginateRelation(
        Model $model,
        string $relation,
        Request $request,
        array $searchable = [],
        int $perPage = 10,
        string $prefix = '',
        array $sortable = [],
        string $defaultSort = 'id',
    ): LengthAwarePaginator {
        $query = $model->{$relation}();

        // Search — every pattern goes through Sql so a user's % or _ cannot
        // widen the match or force a full scan.
        $searchKey = $prefix ? "{$prefix}_search" : 'search';
        if ($search = $request->get($searchKey)) {
            $query->where(function ($q) use ($search, $searchable) {
                foreach ($searchable as $column) {
                    Sql::orWhereLike($q, $column, (string) $search);
                }
            });
        }

        // Sort — whitelisted; an unlisted column silently falls back rather than
        // reaching orderBy() as raw client input.
        $sortKey = $prefix ? "{$prefix}_sort" : 'sort';
        $directionKey = $prefix ? "{$prefix}_direction" : 'direction';

        $allowed = array_values(array_unique(array_merge(
            $sortable ?: $searchable,
            ['id', 'created_at', 'updated_at', $defaultSort],
        )));

        $requested = $request->get($sortKey);
        $sort = (is_string($requested) && in_array($requested, $allowed, true)) ? $requested : $defaultSort;
        $direction = $request->get($directionKey) === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sort, $direction);

        // Paginate
        $pageKey = $prefix ? "{$prefix}_page" : 'page';

        return $query->paginate($perPage, ['*'], $pageKey);
    }
}
