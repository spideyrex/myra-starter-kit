<?php

namespace App\Admin\Report;

use App\Admin\Tenancy\Tenancy;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** The frozen wire shape the chart and delivery bundles consume. */
final class ReportResult implements Arrayable
{
    // >>> MYRA v2.5 [B] START
    private string $version = '';
    // <<< MYRA v2.5 [B] END


    /** @param  ReportRow[]  $rows */
    public function __construct(
        private readonly ReportDefinition $definition,
        private readonly ReportRequest $request,
        private array $rows,
        private readonly array $totals,
        private readonly ?array $previousTotals,
        private readonly array $deltas,
        private readonly bool $truncated,
        private readonly int $groupCount,
    ) {}

    /** @return ReportRow[] */
    public function rows(): array
    {
        return $this->rows;
    }

    /** @param  ReportRow[]  $rows */
    public function withRows(array $rows): self
    {
        $clone = clone $this;
        $clone->rows = array_values($rows);

        return $clone;
    }

    public function definition(): ReportDefinition
    {
        return $this->definition;
    }

    public function request(): ReportRequest
    {
        return $this->request;
    }

    public function totals(): array
    {
        return $this->totals;
    }

    public function previousTotals(): ?array
    {
        return $this->previousTotals;
    }

    public function deltas(): array
    {
        return $this->deltas;
    }

    public function truncated(): bool
    {
        return $this->truncated;
    }

    public function groupCount(): int
    {
        return $this->groupCount;
    }

    // >>> MYRA v2.5 [B] START
    /**
     * Freshness stamp for the batch short-circuit. `''` means "never
     * short-circuit": either the definition declared no version column, or the
     * stamp query failed — both degrade to running the aggregation, which is
     * the v2.4.0 behaviour.
     *
     * The stamp is deliberately computed from the DATASET (MAX(column) + row
     * count under the ownership scope) combined with the request fingerprint,
     * which already encodes the resolved absolute period, measures and filters.
     */
    public static function stamp(ReportDefinition $definition, ReportRequest $request, ?Authenticatable $actor, ?string $dataset = null): string
    {
        $dataset ??= self::datasetStamp($definition, $actor);

        return $dataset === '' ? '' : sha1($request->fingerprint($actor) . '|' . $dataset);
    }

    /**
     * ONE cheap scalar query per (report, actor) — the caller memoises it across
     * a batch, so twelve widgets over the same report cost one stamp query, not
     * twelve. `''` disables the short-circuit.
     */
    public static function datasetStamp(ReportDefinition $definition, ?Authenticatable $actor): string
    {
        $column = $definition->versionKey();

        if ($column === null) {
            return '';
        }

        try {
            /** @var class-string<Model> $modelClass */
            $modelClass = $definition->modelClass();
            $table = (new $modelClass)->getTable();

            $query = $modelClass::query()
                ->tap(fn (Builder $q) => ($definition->scopeFn())($q, $actor))
                ->tap(fn (Builder $q) => Tenancy::applyForModel($modelClass, $q, $table))
                ->toBase();

            $wrapped = $query->getGrammar()->wrap($column);

            // Aggregation in SQL: one row, two scalars, no model hydration.
            $row = $query->selectRaw("MAX({$wrapped}) as myra_max, COUNT(*) as myra_count")->first();

            return (string) ($row->myra_max ?? '') . '|' . (string) ($row->myra_count ?? '0');
        } catch (Throwable $e) {
            report($e);

            return '';
        }
    }

    public function withVersion(string $version): self
    {
        $clone = clone $this;
        $clone->version = $version;

        return $clone;
    }

    public function version(): string
    {
        return $this->version;
    }
    // <<< MYRA v2.5 [B] END

    public function toArray(): array
    {
        $r = $this->request;
        $comparison = $r->comparisonPeriod();

        return [
            'report' => $this->definition->key(),
            'state' => $r->toArray(),
            'period' => $r->period()->toArray() + ['bucket' => $r->bucket()?->value],
            'comparison' => $comparison === null ? null : [
                'mode' => $r->comparison()->value,
                'from' => $comparison->toArray()['from'],
                'to' => $comparison->toArray()['to'],
            ],
            'dimension' => $r->dimension()->toClientSchema(),
            'measures' => array_map(static fn (Measure $m) => $m->toClientSchema(), $r->measures()),
            'rows' => array_map(static fn (ReportRow $row) => $row->toArray(), $this->rows),
            'totals' => $this->totals,
            'previousTotals' => $this->previousTotals,
            'deltas' => $this->deltas,
            'truncated' => $this->truncated,
            'groupCount' => $this->groupCount,
        ];
    }
}
