<?php

namespace App\Admin\Report;

use App\Admin\QueryBuilder\RuleCompiler;
use App\Admin\Report\Contracts\DrillResolver;
use App\Support\DateBucket;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * Every number a report produces is computed by the database. This class
 * contains no ->get() on an ungrouped query, no ->cursor(), no
 * Collection::groupBy and no array_sum over model rows: the rows the grouped
 * statement returns ARE the buckets, and there are never more than maxGroups
 * of them.
 *
 * QUERY BUDGET — asserted by ReportRunnerTest via DB::listen:
 *   run():   2  (grouped current + scalar totals current)
 *          +2  when a comparison is active
 *          +1  when the dimension is a relation (bounded whereIn label lookup)
 *          => max 5
 *   stat():  1  +1 comparison  +1 spark  => max 3
 */
final class ReportRunner
{
    public const OTHER_KEY = '__other';

    public const SPARK_POINTS = 60;

    public function __construct(private readonly ReportDefinition $definition) {}

    /** @throws ReportException 422 on bucket overflow — never a truncated chart. */
    public function run(ReportRequest $r, ?Authenticatable $actor): ReportResult
    {
        $ttl = $this->definition->cacheSeconds();

        // The definition carries closures, so the OBJECT is never cached — only
        // the computed scalars, keyed on a fingerprint that includes the actor.
        $payload = $ttl > 0
            ? Cache::remember(
                "myra.report.{$this->definition->key()}.{$r->fingerprint($actor)}",
                $ttl,
                fn () => $this->compute($r, $actor),
            )
            : $this->compute($r, $actor);

        return $this->hydrate($r, $payload);
    }

    /** Ungrouped — one row per window, what a StatWidget needs. */
    public function stat(ReportRequest $r, ?Authenticatable $actor): StatResult
    {
        $ctx = $this->context($r, $actor);
        $measures = $r->measures();

        $values = $this->totalsFor($ctx, $r->period(), $measures);
        $previousValues = null;
        $deltas = [];

        if (($comparison = $r->comparisonPeriod()) !== null) {
            $previousValues = $this->totalsFor($ctx, $comparison, $measures);
        }

        foreach ($measures as $m) {
            $deltas[$m->key] = Delta::between(
                $values[$m->key] ?? null,
                $previousValues === null ? null : ($previousValues[$m->key] ?? null),
                $m->invertsTrend(),
                $m->isAdditive(),
            );
        }

        return new StatResult(
            $this->definition,
            $r,
            $values,
            $previousValues,
            $deltas,
            $this->spark($ctx, $r, $measures[0]),
        );
    }

    // -------------------------------------------------------------------------

    /** @return array<string,mixed> */
    private function context(ReportRequest $r, ?Authenticatable $actor): array
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->definition->modelClass();
        $instance = new $modelClass;
        $connection = $instance->getConnection();
        $table = $instance->getTable();

        $base = $modelClass::query()
            // 1. Ownership scope FIRST, before any client input.
            ->tap(fn (Builder $q) => ($this->definition->scopeFn())($q, $actor))
            // 2. Validated rule tree (report filters + cross-filters), one outer where().
            ->tap(fn (Builder $q) => (new RuleCompiler($this->definition->fieldSet()))->apply($q, $r->filters()));

        $dimension = $r->dimension();
        $join = null;
        $override = null;
        $labelLookup = null;

        if ($dimension->isRelation()) {
            [$join, $override, $labelLookup] = $this->relationBinding($instance, $dimension);
        }

        return [
            'model' => $modelClass,
            'base' => $base,
            'driver' => $connection->getDriverName(),
            'grammar' => $connection->getQueryGrammar(),
            'table' => $table,
            'join' => $join,
            'override' => $override,
            'labelLookup' => $labelLookup,
        ];
    }

    private function windowed(array $ctx, Period $p): Builder
    {
        $grammar = $ctx['grammar'];
        $column = $this->definition->dateColumnName();
        $column = str_contains($column, '.') ? $column : $ctx['table'] . '.' . $column;
        $dateCol = $grammar->wrap($column);

        /** @var Builder $query */
        $query = (clone $ctx['base'])
            ->whereRaw("{$dateCol} >= ?", [$p->start])
            ->whereRaw("{$dateCol} < ?", [$p->end]);   // half-open: index range scan

        if ($ctx['join'] !== null) {
            ($ctx['join'])($query);
        }

        return $query;
    }

    /** @param  Measure[]  $measures */
    private function totalsFor(array $ctx, Period $p, array $measures): array
    {
        $grammar = $ctx['grammar'];

        $query = $this->windowed($ctx, $p);

        foreach ($measures as $m) {
            $query->selectRaw($m->selectExpression($grammar, $ctx['table']) . ' as ' . $m->alias());
        }

        $row = $query->toBase()->first();

        $out = [];
        foreach ($measures as $m) {
            $out[$m->key] = self::num($row?->{$m->alias()} ?? null);
        }

        return $out;
    }

    /** @param  Measure[]  $measures */
    private function grouped(array $ctx, ReportRequest $r, Period $p, array $measures, int $cap): array
    {
        $grammar = $ctx['grammar'];
        $dimension = $r->dimension();
        $dimSql = $dimension->selectExpression($ctx['driver'], $grammar, $r->bucket(), $ctx['table'], $ctx['override']);
        $alias = $dimension->alias();

        $query = $this->windowed($ctx, $p)->selectRaw("{$dimSql} as {$alias}");

        foreach ($measures as $m) {
            $query->selectRaw($m->selectExpression($grammar, $ctx['table']) . ' as ' . $m->alias());
        }

        $query->groupByRaw($dimSql)
            ->orderByRaw($dimension->isDate()
                ? "{$dimSql} asc"
                : $measures[0]->selectExpression($grammar, $ctx['table']) . ' desc')
            ->limit($cap + 1);   // +1 detects overflow

        $out = [];

        foreach ($query->toBase()->get() as $row) {
            $key = $row->{$alias};

            if ($key === null) {
                continue;
            }

            $values = [];
            foreach ($measures as $m) {
                $values[$m->key] = self::num($row->{$m->alias()} ?? null);
            }

            $out[] = ['key' => (string) $key, 'values' => $values];
        }

        return $out;
    }

    private function compute(ReportRequest $r, ?Authenticatable $actor): array
    {
        $ctx = $this->context($r, $actor);
        $dimension = $r->dimension();
        $measures = $r->measures();
        $comparison = $r->comparisonPeriod();

        $cap = $dimension->isDate()
            ? $this->definition->maxGroupCount()
            : min($r->limit(), $this->definition->maxGroupCount());

        $current = $this->grouped($ctx, $r, $r->period(), $measures, $cap);
        $totals = $this->totalsFor($ctx, $r->period(), $measures);

        $previous = null;
        $previousTotals = null;

        if ($comparison !== null) {
            $previous = $this->grouped($ctx, $r, $comparison, $measures, $cap);
            $previousTotals = $this->totalsFor($ctx, $comparison, $measures);
        }

        $truncated = count($current) > $cap;

        if ($dimension->isDate() && $truncated) {
            // The period × bucket combination is a mis-declaration. Refuse
            // rather than hand back a chart that silently stops early.
            throw ReportException::make('reports.errors.tooManyBuckets');
        }

        $current = array_slice($current, 0, $cap);
        $previous = $previous === null ? null : array_slice($previous, 0, $cap);

        $rows = $dimension->isDate()
            ? $this->alignByOrdinal($ctx, $r, $current, $previous, $measures)
            : $this->alignByKey($ctx, $r, $current, $previous, $measures, $truncated, $totals);

        $deltas = [];
        foreach ($measures as $m) {
            $deltas[$m->key] = Delta::between(
                $totals[$m->key] ?? null,
                $previousTotals === null ? null : ($previousTotals[$m->key] ?? null),
                $m->invertsTrend(),
                $m->isAdditive(),
            );
        }

        return [
            'rows' => $rows,
            'totals' => $totals,
            'previousTotals' => $previousTotals,
            'deltas' => $deltas,
            'truncated' => $truncated,
            // An exact count would cost a second grouped COUNT over a subquery —
            // precisely the query this design refuses to run.
            'groupCount' => $truncated ? $cap + 1 : count($rows),
        ];
    }

    /** Date dimensions: gap-fill BOTH series, then zip by ORDINAL index. */
    private function alignByOrdinal(array $ctx, ReportRequest $r, array $current, ?array $previous, array $measures): array
    {
        $bucket = $r->bucket() ?? $r->period()->defaultBucket();
        $driver = $ctx['driver'];
        $locale = app()->getLocale();

        $series = DateBucket::series($bucket, $r->period(), $driver);
        $byKey = array_column($current, 'values', 'key');

        $prevSeries = [];
        $prevByKey = [];

        if ($previous !== null && ($comparison = $r->comparisonPeriod()) !== null) {
            $prevSeries = DateBucket::series($bucket, $comparison, $driver);
            $prevByKey = array_column($previous, 'values', 'key');
        }

        $rows = [];

        foreach ($series as $i => $key) {
            $values = $this->fill($byKey[$key] ?? null, $measures);

            $previousValues = null;
            if ($previous !== null) {
                $prevKey = $prevSeries[$i] ?? null;
                $previousValues = $prevKey === null ? null : $this->fill($prevByKey[$prevKey] ?? null, $measures);
            }

            $rows[] = [
                'key' => $key,
                'label' => DateBucket::label($bucket, $key, $locale),
                'values' => $values,
                'previous' => $previousValues,
                'deltas' => $this->deltasFor($values, $previousValues, $measures),
                'isOther' => false,
            ];
        }

        return $rows;
    }

    /** Field/relation dimensions: zip by KEY, then optionally fold the tail. */
    private function alignByKey(
        array $ctx,
        ReportRequest $r,
        array $current,
        ?array $previous,
        array $measures,
        bool $truncated,
        array $totals,
    ): array {
        $dimension = $r->dimension();
        $prevByKey = $previous === null ? [] : array_column($previous, 'values', 'key');

        $labels = [];
        if ($ctx['labelLookup'] !== null) {
            $labels = ($ctx['labelLookup'])(array_column($current, 'key'));
        }

        $rows = [];
        $sums = [];

        foreach ($current as $entry) {
            $key = $entry['key'];
            $values = $entry['values'];

            foreach ($measures as $m) {
                if ($m->isAdditive()) {
                    $sums[$m->key] = ($sums[$m->key] ?? 0) + (float) ($values[$m->key] ?? 0);
                }
            }

            $previousValues = $previous === null ? null : ($prevByKey[$key] ?? null);

            // A key present only in the current window has previous === null for
            // every measure; a key present only in the previous window is simply
            // not in the current top-N and is dropped.
            if ($previous !== null && $previousValues === null) {
                $previousValues = array_fill_keys(array_map(static fn (Measure $m) => $m->key, $measures), null);
            }

            $rows[] = [
                'key' => $key,
                'label' => (string) ($labels[$key] ?? $dimension->labelFor($key)),
                'values' => $values,
                'previous' => $previousValues,
                'deltas' => $this->deltasFor($values, $previousValues, $measures),
                'isOther' => false,
            ];
        }

        if ($truncated && $dimension->foldsOther()) {
            $otherValues = [];

            foreach ($measures as $m) {
                // Non-additive aggregates are NOT derivable from the buckets.
                // The client renders an em dash. Do not fake it.
                $otherValues[$m->key] = $m->isAdditive()
                    ? max(0, (float) ($totals[$m->key] ?? 0) - (float) ($sums[$m->key] ?? 0))
                    : null;
            }

            $rows[] = [
                'key' => self::OTHER_KEY,
                'label' => __('reports.other'),
                'values' => $otherValues,
                'previous' => null,
                'deltas' => array_fill_keys(array_map(static fn (Measure $m) => $m->key, $measures), null),
                'isOther' => true,
            ];
        }

        return $rows;
    }

    /** @param  Measure[]  $measures */
    private function fill(?array $values, array $measures): array
    {
        $out = [];

        foreach ($measures as $m) {
            $out[$m->key] = $values[$m->key] ?? ($m->isAdditive() ? 0 : null);
        }

        return $out;
    }

    /** @param  Measure[]  $measures */
    private function deltasFor(array $values, ?array $previous, array $measures): array
    {
        $out = [];

        foreach ($measures as $m) {
            $out[$m->key] = $previous === null
                ? null
                : Delta::between($values[$m->key] ?? null, $previous[$m->key] ?? null, $m->invertsTrend(), $m->isAdditive());
        }

        return $out;
    }

    private function spark(array $ctx, ReportRequest $r, Measure $primary): array
    {
        $bucket = $r->bucket() ?? $r->period()->defaultBucket();
        $grammar = $ctx['grammar'];
        $column = $this->definition->dateColumnName();
        $column = str_contains($column, '.') ? $column : $ctx['table'] . '.' . $column;
        $dimSql = DateBucket::sql($bucket, $column, $ctx['driver']);

        $rows = $this->windowed($ctx, $r->period())
            ->selectRaw("{$dimSql} as spark_key")
            ->selectRaw($primary->selectExpression($grammar, $ctx['table']) . ' as ' . $primary->alias())
            ->groupByRaw($dimSql)
            ->orderByRaw("{$dimSql} asc")
            ->limit(self::SPARK_POINTS)
            ->toBase()
            ->get();

        return array_values(array_map(
            fn ($row) => self::num($row->{$primary->alias()} ?? null),
            $rows->all(),
        ));
    }

    private function hydrate(ReportRequest $r, array $payload): ReportResult
    {
        // Optional seam: before the delivery bundle merges, nothing is bound
        // and every row's `drill` is null. That is a correct standalone result.
        $resolver = app()->bound(DrillResolver::class) ? app(DrillResolver::class) : null;

        $result = new ReportResult(
            $this->definition,
            $r,
            [],
            $payload['totals'],
            $payload['previousTotals'],
            $payload['deltas'],
            $payload['truncated'],
            $payload['groupCount'],
        );

        $rows = [];

        foreach ($payload['rows'] as $row) {
            $reportRow = new ReportRow(
                $row['key'],
                $row['label'],
                $row['values'],
                $row['previous'],
                $row['deltas'],
                $row['isOther'],
            );

            $rows[] = $resolver === null
                ? $reportRow
                : $reportRow->withDrill($resolver->forRow($this->definition, $r, $reportRow));
        }

        return $result->withRows($rows);
    }

    /**
     * Resolves a relation dimension to [joinFn, groupColumn, labelLookupFn].
     * BelongsToMany joins the pivot and groups by the related key; BelongsTo
     * groups by the foreign key already on the base table.
     */
    private function relationBinding(Model $instance, Dimension $dimension): array
    {
        $name = (string) $dimension->relationName();

        if (! method_exists($instance, $name)) {
            throw new LogicException("Relation [{$name}] does not exist on " . $instance::class . '.');
        }

        $relation = $instance->{$name}();
        $title = $dimension->titleAttribute();

        if ($relation instanceof BelongsToMany) {
            $pivot = $relation->getTable();
            $parentKey = $relation->getQualifiedParentKeyName();
            $foreignPivot = $relation->getQualifiedForeignPivotKeyName();
            $relatedPivot = $relation->getQualifiedRelatedPivotKeyName();
            $morph = $relation instanceof MorphToMany
                ? [$pivot . '.' . $relation->getMorphType(), $relation->getMorphClass()]
                : null;

            $join = function (Builder $q) use ($pivot, $parentKey, $foreignPivot, $morph) {
                $q->join($pivot, $foreignPivot, '=', $parentKey);

                if ($morph !== null) {
                    $q->where($morph[0], $morph[1]);
                }
            };

            $related = $relation->getRelated();
            $keyName = $related->getKeyName();

            return [
                $join,
                $relatedPivot,
                fn (array $ids) => $ids === []
                    ? []
                    : $related->newQuery()->whereKey($ids)->pluck($title, $keyName)->all(),
            ];
        }

        if ($relation instanceof BelongsTo) {
            $related = $relation->getRelated();
            $keyName = $related->getKeyName();

            return [
                null,
                $relation->getQualifiedForeignKeyName(),
                fn (array $ids) => $ids === []
                    ? []
                    : $related->newQuery()->whereKey($ids)->pluck($title, $keyName)->all(),
            ];
        }

        throw new LogicException("Relation dimension [{$dimension->key}] must be a BelongsTo or BelongsToMany.");
    }

    private static function num(mixed $value): float|int|null
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if (! is_numeric($value)) {
            return null;
        }

        return str_contains((string) $value, '.') ? (float) $value : (int) $value;
    }
}
