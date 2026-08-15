<?php

namespace App\Admin\Import;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Declarative description of one import resource.
 *
 * `authorizeRow()` is mandatory: per-record authorization being the developer's
 * forgettable problem is exactly the gap this closes. assertConfigured() is
 * called by ImportRegistry before a definition is ever used.
 *
 * `chunkSize()` is the future job boundary — queued imports above a threshold
 * are deferred (see the v2.2 spec §5.6), not designed out.
 */
final class ImportDefinition
{
    private ?string $model = null;

    private string $permission = '';

    /** @var ImportColumn[] */
    private array $columns = [];

    private int $chunkSize = 250;

    private int $maxRows = 50000;

    private int $maxColumns = 128;

    /** @var (callable(array, ?Authenticatable): Model)|null */
    private $resolveRecordFn = null;

    /** @var (callable(array, ?Authenticatable): bool)|null */
    private $authorizeRowFn = null;

    /** @var (callable(Model, array, ?Authenticatable): void)|null */
    private $beforeSaveFn = null;

    /** @var (callable(Model, array, ?Authenticatable): void)|null */
    private $afterSaveFn = null;

    private bool $dryRunOnly = false;

    private function __construct(private readonly string $key)
    {
        $this->chunkSize = (int) config('myra.imports.chunk_size', 250);
        $this->maxRows = (int) config('myra.imports.max_rows', 50000);
        $this->maxColumns = (int) config('myra.imports.max_columns', 128);
    }

    public static function make(string $key): self
    {
        return new self($key);
    }

    public function model(string $class): self
    {
        $this->model = $class;

        return $this;
    }

    public function permission(string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    /** @param  ImportColumn[]  $cols */
    public function columns(array $cols): self
    {
        $this->columns = array_values($cols);

        return $this;
    }

    public function chunkSize(int $n = 250): self
    {
        $this->chunkSize = max(1, min($n, 1000));

        return $this;
    }

    public function maxRows(int $n = 50000): self
    {
        $this->maxRows = max(1, $n);

        return $this;
    }

    public function maxColumns(int $n = 128): self
    {
        $this->maxColumns = max(1, $n);

        return $this;
    }

    /** @param  callable(array, ?Authenticatable): Model  $fn */
    public function resolveRecord(callable $fn): self
    {
        $this->resolveRecordFn = $fn;

        return $this;
    }

    /** MANDATORY. @param  callable(array, ?Authenticatable): bool  $fn */
    public function authorizeRow(callable $fn): self
    {
        $this->authorizeRowFn = $fn;

        return $this;
    }

    /** @param  callable(Model, array, ?Authenticatable): void  $fn */
    public function beforeSave(callable $fn): self
    {
        $this->beforeSaveFn = $fn;

        return $this;
    }

    /** @param  callable(Model, array, ?Authenticatable): void  $fn */
    public function afterSave(callable $fn): self
    {
        $this->afterSaveFn = $fn;

        return $this;
    }

    /** Validate and report only — used by the demo, which has no table to write. */
    public function dryRunOnly(bool $v = true): self
    {
        $this->dryRunOnly = $v;

        return $this;
    }

    public function key(): string
    {
        return $this->key;
    }

    public function getPermission(): string
    {
        return $this->permission;
    }

    public function getChunkSize(): int
    {
        return $this->chunkSize;
    }

    public function getMaxRows(): int
    {
        return $this->maxRows;
    }

    public function getMaxColumns(): int
    {
        return $this->maxColumns;
    }

    public function isDryRunOnly(): bool
    {
        return $this->dryRunOnly;
    }

    /** @return ImportColumn[] */
    public function getColumns(): array
    {
        return $this->columns;
    }

    public function column(string $name): ?ImportColumn
    {
        foreach ($this->columns as $c) {
            if ($c->name() === $name) {
                return $c;
            }
        }

        return null;
    }

    /** @return string[] */
    public function columnNames(): array
    {
        return array_map(fn (ImportColumn $c) => $c->name(), $this->columns);
    }

    /** @return string[] */
    public function requiredMappingNames(): array
    {
        return array_values(array_map(
            fn (ImportColumn $c) => $c->name(),
            array_filter($this->columns, fn (ImportColumn $c) => $c->isRequiredMapping()),
        ));
    }

    public function assertConfigured(): void
    {
        if ($this->authorizeRowFn === null) {
            throw new LogicException("Import definition [{$this->key}] must declare authorizeRow().");
        }

        if ($this->columns === []) {
            throw new LogicException("Import definition [{$this->key}] declares no columns.");
        }

        if (! $this->dryRunOnly && $this->resolveRecordFn === null) {
            throw new LogicException("Import definition [{$this->key}] must declare resolveRecord().");
        }
    }

    public function passesAuthorization(array $row, ?Authenticatable $user): bool
    {
        $this->assertConfigured();

        return (bool) ($this->authorizeRowFn)($row, $user);
    }

    public function makeRecord(array $row, ?Authenticatable $user): Model
    {
        return ($this->resolveRecordFn)($row, $user);
    }

    public function runBeforeSave(Model $record, array $row, ?Authenticatable $user): void
    {
        if ($this->beforeSaveFn) {
            ($this->beforeSaveFn)($record, $row, $user);
        }
    }

    public function runAfterSave(Model $record, array $row, ?Authenticatable $user): void
    {
        if ($this->afterSaveFn) {
            ($this->afterSaveFn)($record, $row, $user);
        }
    }

    /** Client-facing column descriptors for the mapping step. */
    public function toClientSchema(): array
    {
        return array_map(fn (ImportColumn $c) => [
            'name' => $c->name(),
            'label' => $c->getLabel(),
            'required' => $c->isRequiredMapping(),
            'example' => $c->getExample(),
        ], $this->columns);
    }
}
