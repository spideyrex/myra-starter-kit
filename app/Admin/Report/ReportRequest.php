<?php

namespace App\Admin\Report;

use App\Admin\QueryBuilder\RuleTree;
use Illuminate\Contracts\Auth\Authenticatable;
use JsonException;

/**
 * A parsed, fully validated request — the only client-input surface a report
 * has. Mirrors RuleTree::parse(): every rejection is a 422 and no partial or
 * degraded request is ever returned.
 *
 * Names the client sends are intersected against declared sets; undeclared
 * names are DROPPED rather than rejected, so there is no field-enumeration
 * oracle. Malformed or over-cap input is a hard 422.
 */
final class ReportRequest
{
    public const MAX_BYTES = 16384;
    public const MAX_CROSS_FILTERS = 8;

    public const CHART_TYPES = [
        'bar', 'stackedBar', 'horizontalBar', 'line', 'area', 'stackedArea',
        'pie', 'doughnut', 'radar', 'scatter', 'polarArea',
        'heatmap', 'funnel', 'gauge', 'sparkline', 'table',
    ];

    /** @param  Measure[]  $measures */
    private function __construct(
        private readonly Period $period,
        private readonly ComparisonPeriod $comparison,
        private readonly ?Period $comparisonPeriod,
        private readonly Dimension $dimension,
        private readonly ?Bucket $bucket,
        private readonly array $measures,
        private readonly RuleTree $filters,
        private readonly int $limit,
        private readonly string $chart,
        private readonly string $mode,
        /** The report's OWN tree, without the cross-filters merged in. */
        private readonly ?array $queryTree,
        private readonly array $rawCross,
    ) {}

    /** @throws ReportException */
    public static function parse(mixed $raw, ReportDefinition $def, ?Authenticatable $user): self
    {
        $raw = self::decode($raw);

        // 3. Period.
        $period = self::resolvePeriod($raw['period'] ?? null, $def);

        // 4. Comparison — an unavailable mode is dropped, not an error.
        $comparison = ComparisonPeriod::tryFrom((string) ($raw['compare'] ?? 'none')) ?? ComparisonPeriod::None;
        if (! in_array($comparison, $def->allowedComparisons(), true)) {
            $comparison = ComparisonPeriod::None;
        }

        // 5. Dimension — unknown or invisible falls back to the default.
        $dimension = $def->dimension((string) ($raw['dimension'] ?? ''));
        if ($dimension === null || ! $dimension->visibleTo($user)) {
            $dimension = $def->dimension($def->defaultDimensionKey());
        }
        if ($dimension === null) {
            throw ReportException::make('reports.errors.malformed');
        }

        // 6. Bucket, then the pre-flight group cap.
        $bucket = $dimension->effectiveBucket(
            Bucket::tryFrom((string) ($raw['bucket'] ?? '')),
            $period,
        );

        if ($bucket !== null && $bucket->approxCountForDays($period->days()) > $def->maxGroupCount()) {
            throw ReportException::make('reports.errors.tooManyBuckets');
        }

        // 7. Measures — intersect, preserve DECLARED order, truncate.
        $measures = self::resolveMeasures($raw['measures'] ?? null, $def, $user);

        // 8. Limit.
        $limit = min(
            max(1, (int) ($raw['limit'] ?? $dimension->limitValue())),
            $dimension->limitValue(),
            Dimension::MAX_LIMIT,
        );

        // 9 + 10. Report filters and cross-filters, merged into ONE validated tree.
        $set = $def->fieldSet();
        $trees = [];

        $queryTree = null;
        if (is_array($raw['query'] ?? null)) {
            $queryTree = RuleTree::parse($raw['query'], $set, $user)->toArray();
            $trees[] = $queryTree;
        }

        $rawCross = is_array($raw['cross'] ?? null) ? $raw['cross'] : [];
        if (count($rawCross) > self::MAX_CROSS_FILTERS) {
            throw ReportException::make('reports.errors.tooManyCrossFilters');
        }

        $cleanCross = [];
        foreach ($rawCross as $source => $group) {
            // Re-validation is not redundant: the server minted these fragments,
            // but the client is the transport, and the transport has no authority.
            $parsed = RuleTree::parse($group, $set, $user)->toArray();
            $cleanCross[(string) $source] = $parsed;
            $trees[] = $parsed;
        }

        $filters = RuleTree::parse(self::mergeTrees($trees), $set, $user);

        // 11. Chart — presentation only, never reaches SQL.
        $chart = (string) ($raw['chart'] ?? $def->defaultChartType());
        if (! in_array($chart, self::CHART_TYPES, true)) {
            $chart = $def->defaultChartType();
        }

        $mode = ($raw['mode'] ?? 'series') === 'stat' ? 'stat' : 'series';

        return new self(
            $period,
            $comparison,
            $period->comparison($comparison),
            $dimension,
            $bucket,
            $measures,
            $filters,
            $limit,
            $chart,
            $mode,
            $queryTree,
            $cleanCross,
        );
    }

    public function period(): Period
    {
        return $this->period;
    }

    public function comparisonPeriod(): ?Period
    {
        return $this->comparisonPeriod;
    }

    public function comparison(): ComparisonPeriod
    {
        return $this->comparison;
    }

    public function dimension(): Dimension
    {
        return $this->dimension;
    }

    public function bucket(): ?Bucket
    {
        return $this->bucket;
    }

    /** @return Measure[] first element is the primary measure (drives top-N ordering) */
    public function measures(): array
    {
        return $this->measures;
    }

    public function filters(): RuleTree
    {
        return $this->filters;
    }

    public function limit(): int
    {
        return $this->limit;
    }

    public function chartType(): string
    {
        return $this->chart;
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /** Canonical form: cache key, saved-view payload and schedule state are all this. */
    public function toArray(): array
    {
        return [
            'period' => $this->period->toArray(),
            'compare' => $this->comparison->value,
            'dimension' => $this->dimension->key,
            'bucket' => $this->bucket?->value,
            'measures' => array_map(static fn (Measure $m) => $m->key, $this->measures),
            'limit' => $this->limit,
            'chart' => $this->chart,
            'mode' => $this->mode,
            // The report's own tree only — `cross` is replayed separately, so
            // emitting the merged tree here would double-apply every chip.
            'query' => $this->queryTree,
            'cross' => $this->rawCross,
        ];
    }

    /** Stable hash: sorted keys + the actor's scope identity. */
    public function fingerprint(?Authenticatable $user): string
    {
        $payload = $this->toArray();
        self::ksortDeep($payload);

        return hash('xxh128', json_encode([
            'actor' => $user?->getAuthIdentifier(),
            'state' => $payload,
        ]));
    }

    private static function decode(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            if (strlen($raw) > self::MAX_BYTES) {
                throw ReportException::make('reports.errors.malformed');
            }

            try {
                $raw = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw ReportException::make('reports.errors.malformed');
            }
        }

        if (! is_array($raw)) {
            throw ReportException::make('reports.errors.malformed');
        }

        $encoded = json_encode($raw);
        if ($encoded === false || strlen($encoded) > self::MAX_BYTES) {
            throw ReportException::make('reports.errors.malformed');
        }

        return $raw;
    }

    private static function resolvePeriod(mixed $raw, ReportDefinition $def): Period
    {
        if (! is_array($raw)) {
            return Period::preset($def->defaultPeriodPreset());
        }

        $tz = is_string($raw['tz'] ?? null) ? $raw['tz'] : 'UTC';
        $preset = PeriodPreset::tryFrom((string) ($raw['preset'] ?? '')) ?? $def->defaultPeriodPreset();

        if ($preset !== PeriodPreset::Custom) {
            return Period::preset($preset, $tz);
        }

        $from = $raw['from'] ?? null;
        $to = $raw['to'] ?? null;

        if (! is_string($from) || ! is_string($to) || $from === '' || $to === '') {
            throw ReportException::make('reports.errors.badPeriod');
        }

        return Period::between($from, $to, $tz);
    }

    /** @return Measure[] */
    private static function resolveMeasures(mixed $raw, ReportDefinition $def, ?Authenticatable $user): array
    {
        $visible = array_filter($def->allMeasures(), static fn (Measure $m) => $m->visibleTo($user));

        $wanted = is_array($raw)
            ? array_map('strval', array_filter($raw, 'is_scalar'))
            : [];

        $picked = array_values(array_filter(
            $visible,
            static fn (Measure $m) => in_array($m->key, $wanted, true),
        ));

        if ($picked === []) {
            $picked = array_values(array_filter(
                $visible,
                static fn (Measure $m) => in_array($m->key, $def->defaultMeasureKeys(), true),
            ));
        }

        if ($picked === []) {
            $picked = array_values($visible);
        }

        if ($picked === []) {
            throw ReportException::make('reports.errors.noMeasures');
        }

        return array_slice($picked, 0, ReportDefinition::MAX_MEASURES);
    }

    /** AND-merges validated trees without gratuitously deepening the tree. */
    private static function mergeTrees(array $trees): array
    {
        $merged = ['conjunction' => 'and', 'rules' => [], 'groups' => []];

        foreach ($trees as $tree) {
            if (! is_array($tree)) {
                continue;
            }

            $rules = $tree['rules'] ?? [];
            $groups = $tree['groups'] ?? [];

            if ($rules === [] && $groups === []) {
                continue;
            }

            if (($tree['conjunction'] ?? 'and') === 'and') {
                $merged['rules'] = array_merge($merged['rules'], $rules);
                $merged['groups'] = array_merge($merged['groups'], $groups);

                continue;
            }

            $merged['groups'][] = $tree;
        }

        return $merged;
    }

    private static function ksortDeep(array &$array): void
    {
        ksort($array);

        foreach ($array as &$value) {
            if (is_array($value)) {
                self::ksortDeep($value);
            }
        }
    }
}
