<?php

namespace App\Admin\Export;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * One declared column of an export. The declaration is always server-side; the
 * client may only pick from the declared set (see ExportDefinition::selected()).
 */
final class ExportColumn
{
    private string $label = '';

    /** @var (callable(Model): mixed)|null */
    private $stateFn = null;

    /** @var (callable(mixed, Model): mixed)|null */
    private $formatFn = null;

    private ?string $dateFormat = null;

    private bool $enabled = true;

    private ?string $countsRelation = null;

    /** @var array{0:string,1:string}|null */
    private ?array $sumRelation = null;

    private ?int $limit = null;

    private bool $sensitive = false;

    private function __construct(private readonly string $key) {}

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /** @param  callable(Model): mixed  $fn */
    public function state(callable $fn): self
    {
        $this->stateFn = $fn;

        return $this;
    }

    /** @param  callable(mixed, Model): mixed  $fn */
    public function formatStateUsing(callable $fn): self
    {
        $this->formatFn = $fn;

        return $this;
    }

    public function date(string $format = 'Y-m-d H:i'): self
    {
        $this->dateFormat = $format;

        return $this;
    }

    public function enabledByDefault(bool $v = true): self
    {
        $this->enabled = $v;

        return $this;
    }

    /** Compiles to a single withCount() on the query — never a per-row query. */
    public function counts(string $relation): self
    {
        $this->countsRelation = $relation;

        return $this;
    }

    public function sum(string $relation, string $column): self
    {
        $this->sumRelation = [$relation, $column];

        return $this;
    }

    public function limit(int $chars): self
    {
        $this->limit = $chars;

        return $this;
    }

    /** Value is replaced by '***' — for columns that must never leave the system. */
    public function sensitive(bool $v = true): self
    {
        $this->sensitive = $v;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function getLabel(): string
    {
        return $this->label !== '' ? $this->label : Str::headline($this->key);
    }

    public function isEnabledByDefault(): bool
    {
        return $this->enabled;
    }

    public function isSensitive(): bool
    {
        return $this->sensitive;
    }

    public function countsRelation(): ?string
    {
        return $this->countsRelation;
    }

    /** @return array{0:string,1:string}|null */
    public function sumRelation(): ?array
    {
        return $this->sumRelation;
    }

    public function resolve(Model $record): mixed
    {
        if ($this->sensitive) {
            return '***';
        }

        $state = $this->stateFn ? ($this->stateFn)($record) : $this->rawState($record);

        if ($this->formatFn) {
            $state = ($this->formatFn)($state, $record);
        }

        if ($this->dateFormat !== null && $state !== null && $state !== '') {
            $state = $state instanceof Carbon
                ? $state->format($this->dateFormat)
                : Carbon::parse((string) $state)->format($this->dateFormat);
        }

        if ($this->limit !== null && is_string($state)) {
            $state = Str::limit($state, $this->limit);
        }

        if (is_array($state)) {
            $state = implode(', ', $state);
        }

        return $state;
    }

    private function rawState(Model $record): mixed
    {
        if ($this->countsRelation !== null) {
            return $record->getAttribute(Str::snake($this->countsRelation) . '_count');
        }

        if ($this->sumRelation !== null) {
            [$rel, $col] = $this->sumRelation;

            return $record->getAttribute(Str::snake($rel) . '_sum_' . Str::snake($col));
        }

        return data_get($record, $this->key);
    }
}
