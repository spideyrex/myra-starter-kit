<?php

namespace App\Admin\Report;

use Illuminate\Contracts\Support\Arrayable;

/** Ungrouped — one row per window, what a StatWidget needs. */
final class StatResult implements Arrayable
{
    public function __construct(
        private readonly ReportDefinition $definition,
        private readonly ReportRequest $request,
        private readonly array $values,
        private readonly ?array $previousValues,
        private readonly array $deltas,
        private readonly array $spark,
    ) {}

    public function value(string $measure): float|int|null
    {
        return $this->values[$measure] ?? null;
    }

    public function previous(string $measure): float|int|null
    {
        return $this->previousValues[$measure] ?? null;
    }

    public function delta(string $measure): ?array
    {
        return $this->deltas[$measure] ?? null;
    }

    /** @return array<int, float|int|null> <= 60 scalars, primary measure only */
    public function spark(): array
    {
        return $this->spark;
    }

    public function toArray(): array
    {
        $primary = $this->request->measures()[0];

        return [
            'report' => $this->definition->key(),
            'measure' => $primary->key,
            'value' => $this->value($primary->key),
            'previous' => $this->previous($primary->key),
            'delta' => $this->delta($primary->key),
            'spark' => $this->spark,
            'format' => $primary->toClientSchema()['format'],
            'decimals' => $primary->toClientSchema()['decimals'],
            'goal' => $primary->goalValue(),
            'drill' => null,
            'state' => $this->request->toArray(),
            'values' => $this->values,
            'previousValues' => $this->previousValues,
            'deltas' => $this->deltas,
        ];
    }
}
