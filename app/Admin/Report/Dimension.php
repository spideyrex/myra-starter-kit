<?php

namespace App\Admin\Report;

use App\Admin\QueryBuilder\Operator;
use App\Support\DateBucket;
use Closure;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Query\Grammars\Grammar;
use InvalidArgumentException;

/**
 * One whitelisted GROUP BY axis. column() is the only source of a SQL
 * identifier here and is validated at construction — same contract as FieldSpec.
 */
final class Dimension
{
    public const MAX_LIMIT = 200;

    private const IDENTIFIER = '/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/i';

    private string $column;

    private ?string $labelKey = null;

    private ?Bucket $bucket = null;

    /** @var Bucket[] */
    private array $allowedBuckets = [];

    private ?string $relation = null;

    private string $titleAttribute = 'name';

    private ?int $limit = null;

    private bool $other = false;

    private ?Closure $labelFn = null;

    private ?string $permission = null;

    private ?string $drillField = null;

    private Operator $drillOperator = Operator::Equals;

    private function __construct(
        public readonly string $key,
        public readonly string $type,
    ) {
        self::assertIdentifier($key);
        $this->column = $key;
    }

    public static function field(string $key): self
    {
        return new self($key, 'field');
    }

    public static function date(string $key): self
    {
        return new self($key, 'date');
    }

    public static function relation(string $key): self
    {
        return new self($key, 'relation');
    }

    public function column(string $sqlColumn): self
    {
        self::assertIdentifier($sqlColumn);
        $this->column = $sqlColumn;

        return $this;
    }

    public function labelKey(string $key): self
    {
        $this->labelKey = $key;

        return $this;
    }

    public function bucket(Bucket $b): self
    {
        $this->bucket = $b;

        return $this;
    }

    /** @param  Bucket[]  $allowed */
    public function allowedBuckets(array $allowed): self
    {
        $this->allowedBuckets = array_values($allowed);

        return $this;
    }

    public function relationship(string $relation, string $titleAttribute = 'name'): self
    {
        self::assertIdentifier($relation);
        self::assertIdentifier($titleAttribute);
        $this->relation = $relation;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    /** Top-N by the primary measure. MANDATORY for field/relation dimensions. */
    public function limit(int $n = 20): self
    {
        $this->limit = max(1, min($n, self::MAX_LIMIT));
        $this->other = true;

        return $this;
    }

    /** Fold the tail into one SQL-computed "Other" row. */
    public function other(bool $v = true): self
    {
        $this->other = $v;

        return $this;
    }

    /** Label map for a closed value set, e.g. status codes. PHP side only. */
    public function labelUsing(Closure $fn): self
    {
        $this->labelFn = $fn;

        return $this;
    }

    public function permission(?string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    /** The FieldSpec name in the drill FieldSet. Omit ⇒ this dimension is not drillable. */
    public function drillsInto(string $fieldName, Operator $operator = Operator::Equals): self
    {
        $this->drillField = $fieldName;
        $this->drillOperator = $operator;

        return $this;
    }

    // — resolved by the runner —

    public function visibleTo(?Authenticatable $user): bool
    {
        if ($this->permission === null) {
            return true;
        }

        return $user instanceof Authorizable && $user->can($this->permission);
    }

    /**
     * $table qualifies an unqualified column; $override replaces it entirely
     * (a relation dimension groups by the joined pivot key, not a base column).
     */
    public function selectExpression(
        string $driver,
        Grammar $grammar,
        ?Bucket $bucket,
        ?string $table = null,
        ?string $override = null,
    ): string {
        $column = $override ?? $this->column;

        if ($override === null && $table !== null && ! str_contains($column, '.')) {
            $column = $table . '.' . $column;
        }

        // DateBucket re-asserts the identifier and emits it unquoted, so the
        // raw column goes in — not the grammar-wrapped one.
        if ($this->type === 'date') {
            return DateBucket::sql($bucket ?? $this->bucket ?? Bucket::Day, $column, $driver);
        }

        return $grammar->wrap($column);
    }

    /** Always 'd_key'. */
    public function alias(): string
    {
        return 'd_' . preg_replace('/[^a-z0-9_]/i', '_', $this->key);
    }

    public function sqlColumn(): string
    {
        return $this->column;
    }

    public function isDate(): bool
    {
        return $this->type === 'date';
    }

    public function isRelation(): bool
    {
        return $this->type === 'relation';
    }

    public function relationName(): ?string
    {
        return $this->relation;
    }

    public function titleAttribute(): string
    {
        return $this->titleAttribute;
    }

    public function defaultBucket(): ?Bucket
    {
        return $this->bucket;
    }

    /** @return Bucket[] */
    public function allowedBucketList(): array
    {
        if (! $this->isDate()) {
            return [];
        }

        return $this->allowedBuckets ?: array_values(array_filter(
            Bucket::cases(),
            fn (Bucket $b) => $this->bucket === null || $b === $this->bucket,
        ));
    }

    public function effectiveBucket(?Bucket $requested, Period $p, ?int $maxGroups = null): ?Bucket
    {
        if (! $this->isDate()) {
            return null;
        }

        $allowed = $this->allowedBucketList();

        if ($requested !== null && in_array($requested, $allowed, true)) {
            return $requested;
        }

        $auto = $p->defaultBucket();

        if (in_array($auto, $allowed, true)) {
            return $auto;
        }

        // The auto granularity is not on offer. Falling back to the declared
        // default can be far finer than the window warrants and guarantee a
        // bucket-overflow 422, so pick the finest allowed bucket that still
        // fits the cap, and the coarsest one on offer when none does.
        $ordered = array_values(array_filter(
            Bucket::cases(),
            static fn (Bucket $b) => in_array($b, $allowed, true),
        ));

        if ($ordered === []) {
            return $this->bucket ?? Bucket::Day;
        }

        $cap = $maxGroups ?? self::MAX_LIMIT;
        $days = $p->days();

        foreach ($ordered as $b) {
            if ($b->approxCountForDays($days) <= $cap) {
                return $b;
            }
        }

        return end($ordered);
    }

    public function limitValue(): int
    {
        return $this->limit ?? self::MAX_LIMIT;
    }

    public function hasLimit(): bool
    {
        return $this->limit !== null;
    }

    public function foldsOther(): bool
    {
        return $this->other && ! $this->isDate();
    }

    public function drillField(): ?string
    {
        return $this->drillField;
    }

    public function drillOperator(): Operator
    {
        return $this->drillOperator;
    }

    public function labelFor(string $key): string
    {
        if ($this->labelFn !== null) {
            return (string) ($this->labelFn)($key);
        }

        return $key;
    }

    public function getLabelKey(): ?string
    {
        return $this->labelKey;
    }

    /** @return array<string,mixed> */
    public function toClientSchema(): array
    {
        return [
            'key' => $this->key,
            'labelKey' => $this->labelKey ?? ('reports.dim.' . $this->key),
            'type' => $this->type,
            'drillable' => $this->drillField !== null,
            'allowedBuckets' => array_map(static fn (Bucket $b) => $b->value, $this->allowedBucketList()),
            'limit' => $this->limitValue(),
        ];
    }

    private static function assertIdentifier(string $value): void
    {
        if (preg_match(self::IDENTIFIER, $value) !== 1) {
            throw new InvalidArgumentException("Invalid SQL identifier [{$value}].");
        }
    }
}
