<?php

namespace App\Admin\Report;

use App\Admin\QueryBuilder\FieldSet;
use App\Admin\Report\Contracts\ReportDocumentRenderer;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Declarative description of one report. Source model, dimensions, measures and
 * scope are ALWAYS controller-side literals; the client sends names which are
 * intersected against the declared sets, exactly as ExportDefinition does.
 */
final class ReportDefinition
{
    public const MAX_GROUPS = 200;
    public const HARD_MAX_GROUPS = 2000;
    public const MAX_MEASURES = 6;

    /** @var class-string<Model>|null */
    private ?string $model = null;

    private ?string $titleKey = null;

    private string $permission = 'reports.view';

    private Closure $scope;

    private string $dateColumn = 'created_at';

    /** @var array<string,Dimension> */
    private array $dimensions = [];

    /** @var array<string,Measure> */
    private array $measures = [];

    private ?FieldSet $fieldSet = null;

    private ?string $defaultDimension = null;

    /** @var string[] */
    private array $defaultMeasures = [];

    private PeriodPreset $defaultPreset = PeriodPreset::Last30Days;

    /** @var ComparisonPeriod[] */
    private array $comparisons = [ComparisonPeriod::Previous];

    private ?string $drillRoute = null;

    private string $drillQueryParam = 'query';

    private array $drillRouteParams = [];

    private int $maxGroups = self::MAX_GROUPS;

    private int $cacheSeconds = 0;

    /** @var string[] */
    private array $formats = ['csv', 'xlsx'];

    private bool $schedulable = false;

    private string $defaultChart = 'bar';

    private function __construct(private readonly string $key)
    {
        $this->scope = static fn (Builder $q, ?Authenticatable $actor) => $q;
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    /** @param  class-string<Model>  $class */
    public function model(string $class): self
    {
        $this->model = $class;

        return $this;
    }

    public function titleKey(string $key): self
    {
        $this->titleKey = $key;

        return $this;
    }

    public function permission(string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    /** Ownership/tenant scope. Applied FIRST, before any client input, always. */
    public function scope(Closure $fn): self
    {
        $this->scope = $fn;

        return $this;
    }

    public function dateColumn(string $column = 'created_at'): self
    {
        if (preg_match('/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/i', $column) !== 1) {
            throw new LogicException("Invalid date column [{$column}].");
        }

        $this->dateColumn = $column;

        return $this;
    }

    /** @param  Dimension[]  $d */
    public function dimensions(array $d): self
    {
        foreach ($d as $dimension) {
            $this->dimensions[$dimension->key] = $dimension;
        }

        return $this;
    }

    /** @param  Measure[]  $m */
    public function measures(array $m): self
    {
        foreach ($m as $measure) {
            $this->measures[$measure->key] = $measure;
        }

        return $this;
    }

    /** The SAME FieldSet the drill-through table uses — one whitelist, two consumers. */
    public function filters(FieldSet $set): self
    {
        $this->fieldSet = $set;

        return $this;
    }

    /** @param  string[]  $measures */
    public function defaults(string $dimension, array $measures, PeriodPreset $p = PeriodPreset::Last30Days): self
    {
        $this->defaultDimension = $dimension;
        $this->defaultMeasures = array_values($measures);
        $this->defaultPreset = $p;

        return $this;
    }

    /** @param  ComparisonPeriod[]  $modes */
    public function comparisons(array $modes): self
    {
        $this->comparisons = array_values($modes);

        return $this;
    }

    /** Route a drill-through lands on — a real DataTable index route. */
    public function drillTo(string $routeName, string $queryParam = 'query', array $routeParams = []): self
    {
        $this->drillRoute = $routeName;
        $this->drillQueryParam = $queryParam;
        $this->drillRouteParams = $routeParams;

        return $this;
    }

    public function maxGroups(int $n = self::MAX_GROUPS): self
    {
        $this->maxGroups = max(1, min($n, self::HARD_MAX_GROUPS));

        return $this;
    }

    public function cacheFor(int $seconds = 0): self
    {
        $this->cacheSeconds = max(0, $seconds);

        return $this;
    }

    /** @param  string[]  $f */
    public function formats(array $f = ['csv', 'xlsx']): self
    {
        $this->formats = array_values(array_intersect($f, self::supportedFormats())) ?: ['csv'];

        return $this;
    }

    public function chart(string $type): self
    {
        $this->defaultChart = $type;

        return $this;
    }

    public function schedulable(bool $v = true): self
    {
        $this->schedulable = $v;

        return $this;
    }

    /** @throws LogicException — called by ReportRegistry::resolve() */
    public function assertConfigured(): void
    {
        if ($this->model === null || ! class_exists($this->model)) {
            throw new LogicException("Report [{$this->key}] has no model.");
        }

        if ($this->dimensions === [] || $this->measures === []) {
            throw new LogicException("Report [{$this->key}] needs at least one dimension and one measure.");
        }

        foreach ($this->dimensions as $dimension) {
            if (! $dimension->isDate() && ! $dimension->hasLimit()) {
                throw new LogicException(
                    "Report [{$this->key}] dimension [{$dimension->key}] must declare limit(): an unbounded categorical GROUP BY is a mis-declaration.",
                );
            }
        }

        if ($this->defaultDimension === null || ! isset($this->dimensions[$this->defaultDimension])) {
            throw new LogicException("Report [{$this->key}] defaults() names an unknown dimension.");
        }

        foreach ($this->defaultMeasures as $name) {
            if (! isset($this->measures[$name])) {
                throw new LogicException("Report [{$this->key}] defaults() names an unknown measure [{$name}].");
            }
        }

        if ($this->defaultMeasures === []) {
            throw new LogicException("Report [{$this->key}] defaults() names no measures.");
        }

        $drillable = array_filter($this->dimensions, static fn (Dimension $d) => $d->drillField() !== null);

        if ($drillable !== [] && ($this->drillRoute === null || ! app('router')->has($this->drillRoute))) {
            throw new LogicException("Report [{$this->key}] declares drillsInto() but drillTo() names no existing route.");
        }

        if ($this->maxGroups > self::HARD_MAX_GROUPS) {
            throw new LogicException("Report [{$this->key}] exceeds the hard group cap.");
        }
    }

    public function key(): string
    {
        return $this->key;
    }

    /** @return class-string<Model> */
    public function modelClass(): string
    {
        return (string) $this->model;
    }

    public function getTitleKey(): string
    {
        return $this->titleKey ?? ('reports.' . $this->key . '.title');
    }

    public function permissionAbility(): string
    {
        return $this->permission;
    }

    public function scopeFn(): Closure
    {
        return $this->scope;
    }

    public function dateColumnName(): string
    {
        return $this->dateColumn;
    }

    public function dimension(string $key): ?Dimension
    {
        return $this->dimensions[$key] ?? null;
    }

    /** @return array<string,Dimension> */
    public function allDimensions(): array
    {
        return $this->dimensions;
    }

    public function measure(string $key): ?Measure
    {
        return $this->measures[$key] ?? null;
    }

    /** @return array<string,Measure> */
    public function allMeasures(): array
    {
        return $this->measures;
    }

    public function fieldSet(): FieldSet
    {
        return $this->fieldSet ??= FieldSet::make([]);
    }

    public function defaultDimensionKey(): string
    {
        return (string) $this->defaultDimension;
    }

    /** @return string[] */
    public function defaultMeasureKeys(): array
    {
        return $this->defaultMeasures;
    }

    public function defaultPeriodPreset(): PeriodPreset
    {
        return $this->defaultPreset;
    }

    /** @return ComparisonPeriod[] */
    public function allowedComparisons(): array
    {
        return $this->comparisons;
    }

    public function drillRoute(): ?string
    {
        return $this->drillRoute;
    }

    public function drillParam(): string
    {
        return $this->drillQueryParam;
    }

    public function drillRouteParams(): array
    {
        return $this->drillRouteParams;
    }

    public function maxGroupCount(): int
    {
        return $this->maxGroups;
    }

    public function cacheSeconds(): int
    {
        return $this->cacheSeconds;
    }

    /** @return string[] */
    public function allowedFormats(): array
    {
        return $this->formats;
    }

    public function isSchedulable(): bool
    {
        return $this->schedulable;
    }

    public function defaultChartType(): string
    {
        return $this->defaultChart;
    }

    /** @return array<string,mixed> permission-filtered */
    public function toClientSchema(?Authenticatable $user): array
    {
        $dimensions = array_values(array_map(
            static fn (Dimension $d) => $d->toClientSchema(),
            array_filter($this->dimensions, static fn (Dimension $d) => $d->visibleTo($user)),
        ));

        $measures = array_values(array_map(
            static fn (Measure $m) => $m->toClientSchema(),
            array_filter($this->measures, static fn (Measure $m) => $m->visibleTo($user)),
        ));

        return [
            'key' => $this->key,
            'titleKey' => $this->getTitleKey(),
            'dimensions' => $dimensions,
            'measures' => $measures,
            'comparisons' => array_map(static fn (ComparisonPeriod $c) => $c->value, $this->comparisons),
            'periods' => array_map(static fn (PeriodPreset $p) => $p->value, PeriodPreset::cases()),
            'defaults' => [
                'dimension' => $this->defaultDimension,
                'measures' => $this->defaultMeasures,
                'period' => $this->defaultPreset->value,
                'chart' => $this->defaultChart,
            ],
            'fields' => $this->fieldSet()->toClientSchema($user),
            'formats' => $this->formats,
            'maxGroups' => $this->maxGroups,
            'maxMeasures' => self::MAX_MEASURES,
            'schedulable' => $this->schedulable,
            'drillable' => $this->drillRoute !== null,
        ];
    }

    /** @return string[] */
    private static function supportedFormats(): array
    {
        $formats = ['csv', 'xlsx'];

        if (app()->bound(ReportDocumentRenderer::class)) {
            /** @var class-string<ReportDocumentRenderer> $renderer */
            $renderer = get_class(app(ReportDocumentRenderer::class));
            $formats = array_merge($formats, $renderer::formats());
        }

        return $formats;
    }
}
