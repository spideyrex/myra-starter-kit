<?php

namespace App\Admin\Report;

use Illuminate\Contracts\Support\Arrayable;

/** The frozen wire shape the chart and delivery bundles consume. */
final class ReportResult implements Arrayable
{
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
