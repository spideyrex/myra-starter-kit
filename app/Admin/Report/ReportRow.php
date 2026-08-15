<?php

namespace App\Admin\Report;

use Illuminate\Contracts\Support\Arrayable;

final class ReportRow implements Arrayable
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $values,
        public readonly ?array $previous,
        public readonly array $deltas,
        public readonly bool $isOther = false,
        public readonly ?array $drill = null,
    ) {}

    public function withDrill(?array $drill): self
    {
        return new self(
            $this->key,
            $this->label,
            $this->values,
            $this->previous,
            $this->deltas,
            $this->isOther,
            $drill,
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'values' => $this->values,
            'previous' => $this->previous,
            'deltas' => $this->deltas,
            'isOther' => $this->isOther,
            'drill' => $this->drill,
        ];
    }
}
