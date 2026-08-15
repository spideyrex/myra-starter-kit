<?php

namespace App\Admin\Import;

use Illuminate\Support\Str;

/** One declared, mappable target field of an import. */
final class ImportColumn
{
    private string $label = '';

    private bool $requiredMapping = false;

    /** @var string[] */
    private array $aliases = [];

    private array $rules = [];

    /** @var (callable(mixed): mixed)|null */
    private $castFn = null;

    private mixed $default = null;

    private bool $hasDefault = false;

    private bool $boolean = false;

    private bool $numeric = false;

    private int $decimals = 0;

    private ?string $separator = null;

    private bool $sensitive = false;

    /** @var (callable(mixed, array): mixed)|null */
    private $resolveFn = null;

    private ?string $example = null;

    private function __construct(private readonly string $name) {}

    public static function make(string $name): self
    {
        return new self($name);
    }

    public function label(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    public function requiredMapping(bool $v = true): self
    {
        $this->requiredMapping = $v;

        return $this;
    }

    /** @param  string[]  $aliases  header names that should auto-map to this column */
    public function guess(array $aliases): self
    {
        $this->aliases = array_map(fn ($a) => self::normalise((string) $a), $aliases);

        return $this;
    }

    public function rules(array $rules): self
    {
        $this->rules = $rules;

        return $this;
    }

    /** @param  callable(mixed): mixed  $fn */
    public function castUsing(callable $fn): self
    {
        $this->castFn = $fn;

        return $this;
    }

    public function default(mixed $v): self
    {
        $this->default = $v;
        $this->hasDefault = true;

        return $this;
    }

    public function boolean(): self
    {
        $this->boolean = true;

        return $this;
    }

    public function numeric(int $decimals = 0): self
    {
        $this->numeric = true;
        $this->decimals = $decimals;

        return $this;
    }

    public function multiple(string $separator = ','): self
    {
        $this->separator = $separator;

        return $this;
    }

    /** Value is written as '***' into the failures CSV. */
    public function sensitive(bool $v = true): self
    {
        $this->sensitive = $v;

        return $this;
    }

    /** @param  callable(mixed, array): mixed  $fn */
    public function resolveUsing(callable $fn): self
    {
        $this->resolveFn = $fn;

        return $this;
    }

    public function example(string $v): self
    {
        $this->example = $v;

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label !== '' ? $this->label : Str::headline($this->name);
    }

    public function isRequiredMapping(): bool
    {
        return $this->requiredMapping;
    }

    public function isSensitive(): bool
    {
        return $this->sensitive;
    }

    public function getRules(): array
    {
        return $this->rules;
    }

    public function getExample(): ?string
    {
        return $this->example;
    }

    /** @return string[] */
    public function aliases(): array
    {
        return $this->aliases;
    }

    /** Raw CSV string -> typed value. */
    public function cast(mixed $raw, array $row = []): mixed
    {
        $value = is_string($raw) ? trim($raw) : $raw;

        if (($value === null || $value === '') && $this->hasDefault) {
            $value = $this->default;
        }

        if ($this->separator !== null && is_string($value)) {
            $value = $value === ''
                ? []
                : array_values(array_filter(array_map('trim', explode($this->separator, $value)), fn ($v) => $v !== ''));
        }

        if ($this->boolean && ! is_array($value)) {
            $value = in_array(strtolower((string) $value), ['1', 'true', 'yes', 'y', 'on'], true);
        }

        if ($this->numeric && ! is_array($value) && $value !== null && $value !== '') {
            $clean = (float) preg_replace('/[^0-9.\-]/', '', (string) $value);
            $value = $this->decimals > 0 ? round($clean, $this->decimals) : (int) round($clean);
        }

        if ($this->castFn) {
            $value = ($this->castFn)($value);
        }

        if ($this->resolveFn) {
            $value = ($this->resolveFn)($value, $row);
        }

        return $value;
    }

    /** Lowercased, punctuation-stripped form used by the auto-mapper. */
    public static function normalise(string $header): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]+/i', '', $header));
    }
}
