<?php

namespace App\Admin\Report;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Grammars\Grammar;
use InvalidArgumentException;

/** One declared number. Aggregate::sql() is the only SQL it can produce. */
final class Measure
{
    public const FORMATS = ['number', 'currency', 'percent', 'duration', 'bytes'];

    private const IDENTIFIER = '/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/i';

    private ?string $column = null;

    private ?string $labelKey = null;

    private string $format = 'number';

    private int $decimals = 0;

    private ?float $goal = null;

    private bool $invertTrend = false;

    private ?string $permission = null;

    private function __construct(
        public readonly string $key,
        public readonly Aggregate $aggregate,
    ) {
        self::assertIdentifier($key);
    }

    public static function count(string $key = 'count'): self
    {
        return new self($key, Aggregate::Count);
    }

    public static function countDistinct(string $key, string $column): self
    {
        return (new self($key, Aggregate::CountDistinct))->on($column);
    }

    public static function sum(string $key, string $column): self
    {
        return (new self($key, Aggregate::Sum))->on($column);
    }

    public static function average(string $key, string $column): self
    {
        return (new self($key, Aggregate::Average))->on($column);
    }

    public static function min(string $key, string $column): self
    {
        return (new self($key, Aggregate::Min))->on($column);
    }

    public static function max(string $key, string $column): self
    {
        return (new self($key, Aggregate::Max))->on($column);
    }

    public function labelKey(string $key): self
    {
        $this->labelKey = $key;

        return $this;
    }

    public function format(string $f): self
    {
        if (! in_array($f, self::FORMATS, true)) {
            throw new InvalidArgumentException("Unsupported measure format [{$f}].");
        }

        $this->format = $f;

        return $this;
    }

    public function decimals(int $n = 0): self
    {
        $this->decimals = max(0, min($n, 6));

        return $this;
    }

    /** Drives gauge + reference line. */
    public function goal(float $target): self
    {
        $this->goal = $target;

        return $this;
    }

    /** Churn going UP is BAD — flips delta.good. */
    public function invertTrend(bool $v = true): self
    {
        $this->invertTrend = $v;

        return $this;
    }

    public function permission(?string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    public function visibleTo(?Authenticatable $user): bool
    {
        if ($this->permission === null) {
            return true;
        }

        return $user instanceof Authorizable && $user->can($this->permission);
    }

    /** $table qualifies an unqualified column so a joined dimension stays unambiguous. */
    public function selectExpression(Grammar $grammar, ?string $table = null): string
    {
        if ($this->column === null) {
            return $this->aggregate->sql(null);
        }

        $column = ($table !== null && ! str_contains($this->column, '.'))
            ? $table . '.' . $this->column
            : $this->column;

        return $this->aggregate->sql($grammar->wrap($column));
    }

    /** Always 'm_key'. */
    public function alias(): string
    {
        return 'm_' . preg_replace('/[^a-z0-9_]/i', '_', $this->key);
    }

    public function isAdditive(): bool
    {
        return $this->aggregate->isAdditive();
    }

    public function invertsTrend(): bool
    {
        return $this->invertTrend;
    }

    public function goalValue(): ?float
    {
        return $this->goal;
    }

    /** @return array<string,mixed> */
    public function toClientSchema(): array
    {
        return [
            'key' => $this->key,
            'labelKey' => $this->labelKey ?? ('reports.measure.' . $this->key),
            'format' => $this->format,
            'decimals' => $this->decimals,
            'goal' => $this->goal,
            'invertTrend' => $this->invertTrend,
            'additive' => $this->isAdditive(),
        ];
    }

    private function on(string $column): self
    {
        self::assertIdentifier($column);
        $this->column = $column;

        return $this;
    }

    private static function assertIdentifier(string $value): void
    {
        if (preg_match(self::IDENTIFIER, $value) !== 1) {
            throw new InvalidArgumentException("Invalid SQL identifier [{$value}].");
        }
    }
}
