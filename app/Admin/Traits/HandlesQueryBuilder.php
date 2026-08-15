<?php

namespace App\Admin\Traits;

use App\Admin\QueryBuilder\FieldSet;
use App\Admin\QueryBuilder\QueryBuilderException;
use App\Admin\QueryBuilder\RuleCompiler;
use App\Admin\QueryBuilder\RuleTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use JsonException;

trait HandlesQueryBuilder
{
    /**
     * $set is always a controller-side literal — the same convention as
     * $searchable in SearchableQuery and $spec in summarise().
     */
    protected function applyQueryBuilder(Builder $q, Request $r, string $param, FieldSet $set): Builder
    {
        $raw = $r->input($param);

        if (blank($raw)) {
            return $q;
        }

        if (is_string($raw)) {
            try {
                $raw = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw QueryBuilderException::make('filters.errors.malformed');
            }
        }

        return (new RuleCompiler($set))->apply($q, RuleTree::parse($raw, $set, $r->user()));
    }
}
