<?php

namespace App\Admin\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

trait HasRelationManagers
{
    /**
     * Paginate a relation with search, sort, and pagination support.
     *
     * @param  Model   $model       The parent model
     * @param  string  $relation    The relation name
     * @param  Request $request     The current request
     * @param  array   $searchable  Columns to search
     * @param  int     $perPage     Items per page
     * @param  string  $prefix      Query parameter prefix (for multiple relation managers on one page)
     */
    protected function paginateRelation(
        Model $model,
        string $relation,
        Request $request,
        array $searchable = [],
        int $perPage = 10,
        string $prefix = ''
    ): LengthAwarePaginator {
        $query = $model->{$relation}();

        // Search
        $searchKey = $prefix ? "{$prefix}_search" : 'search';
        if ($search = $request->get($searchKey)) {
            $query->where(function ($q) use ($search, $searchable) {
                foreach ($searchable as $column) {
                    $q->orWhere($column, 'like', "%{$search}%");
                }
            });
        }

        // Sort
        $sortKey = $prefix ? "{$prefix}_sort" : 'sort';
        $directionKey = $prefix ? "{$prefix}_direction" : 'direction';
        $sort = $request->get($sortKey, 'id');
        $direction = $request->get($directionKey, 'desc');
        $query->orderBy($sort, $direction);

        // Paginate
        $pageKey = $prefix ? "{$prefix}_page" : 'page';
        return $query->paginate($perPage, ['*'], $pageKey);
    }
}
