<?php

namespace App\Admin\Export;

use Illuminate\Database\Eloquent\Builder;

/**
 * Declarative description of one export. The declared column set is the only
 * authority: a client `columns[]` array is intersected with it, never trusted.
 */
final class ExportDefinition
{
    /** cursor() buffers in the MySQL PDO driver, so preserveSort() caps far lower. */
    public const PRESERVE_SORT_MAX_ROWS = 20000;

    /** @var ExportColumn[] */
    private array $columns = [];

    private array $eagerLoad = [];

    private int $chunkSize = 1000;

    private int $maxRows = 50000;

    private string $keyColumn = 'id';

    private bool $preserveSort = false;

    private string $filename;

    /** @var string[] */
    private array $formats = ['csv'];

    private function __construct(private readonly string $key)
    {
        $this->filename = $key;
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    /** @param  ExportColumn[]  $columns */
    public function columns(array $columns): self
    {
        $this->columns = array_values($columns);

        return $this;
    }

    public function eagerLoad(array $relations): self
    {
        $this->eagerLoad = $relations;

        return $this;
    }

    public function chunkSize(int $n = 1000): self
    {
        $this->chunkSize = max(1, $n);

        return $this;
    }

    public function maxRows(int $n = 50000): self
    {
        $this->maxRows = $this->preserveSort ? min($n, self::PRESERVE_SORT_MAX_ROWS) : max(1, $n);

        return $this;
    }

    public function keyColumn(string $col = 'id'): self
    {
        $this->keyColumn = $col;

        return $this;
    }

    /** Opts into cursor() so the caller's sort survives — and lowers the row cap. */
    public function preserveSort(bool $v = false): self
    {
        $this->preserveSort = $v;

        if ($v) {
            $this->maxRows = min($this->maxRows, self::PRESERVE_SORT_MAX_ROWS);
        }

        return $this;
    }

    public function filename(string $base): self
    {
        $this->filename = $base;

        return $this;
    }

    /** @param  string[]  $f */
    public function formats(array $f = ['csv']): self
    {
        $this->formats = array_values(array_intersect($f, ['csv', 'xlsx'])) ?: ['csv'];

        return $this;
    }

    /**
     * Client column picks, intersected with the declared set and returned in
     * declared order. Undeclared keys are dropped, never exported.
     *
     * @return ExportColumn[]
     */
    public function selected(?array $keys): array
    {
        $defaults = array_values(array_filter($this->columns, fn (ExportColumn $c) => $c->isEnabledByDefault()));

        if (! is_array($keys) || $keys === []) {
            return $defaults ?: $this->columns;
        }

        $wanted = array_map('strval', array_filter($keys, fn ($k) => is_scalar($k)));

        $picked = array_values(array_filter(
            $this->columns,
            fn (ExportColumn $c) => in_array($c->key(), $wanted, true),
        ));

        return $picked ?: ($defaults ?: $this->columns);
    }

    /** @return ExportColumn[] */
    public function allColumns(): array
    {
        return $this->columns;
    }

    /** Relations aggregated with withCount(), deduplicated. @return string[] */
    public function countRelations(): array
    {
        $rels = [];
        foreach ($this->columns as $c) {
            if ($c->countsRelation() !== null) {
                $rels[$c->countsRelation()] = true;
            }
        }

        return array_keys($rels);
    }

    /** @return array<string,string> relation => column */
    public function sumRelations(): array
    {
        $out = [];
        foreach ($this->columns as $c) {
            if ($sum = $c->sumRelation()) {
                $out[$sum[0]] = $sum[1];
            }
        }

        return $out;
    }

    /**
     * Query mutators applied once before iteration.
     *
     * @return array<int, callable(Builder): Builder>
     */
    public function aggregates(): array
    {
        $out = [];

        if ($counts = $this->countRelations()) {
            $out[] = fn (Builder $q) => $q->withCount($counts);
        }

        foreach ($this->sumRelations() as $rel => $col) {
            $out[] = fn (Builder $q) => $q->withSum($rel, $col);
        }

        return $out;
    }

    public function eagerLoads(): array
    {
        return $this->eagerLoad;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getMaxRows(): int
    {
        return $this->maxRows;
    }

    public function getKeyColumn(): string
    {
        return $this->keyColumn;
    }

    public function preservesSort(): bool
    {
        return $this->preserveSort;
    }

    public function filenameBase(): string
    {
        return $this->filename;
    }

    /** @return string[] */
    public function allowedFormats(): array
    {
        return $this->formats;
    }

    public function key(): string
    {
        return $this->key;
    }
}
