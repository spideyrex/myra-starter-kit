<?php

namespace App\Admin\QueryBuilder;

use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use InvalidArgumentException;

/**
 * One whitelisted field. `column()` is the ONLY source of a SQL identifier in
 * the query builder, and it is validated at construction time.
 */
final class FieldSpec
{
    private const IDENTIFIER = '/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/i';

    private string $column;

    private ?string $labelKey = null;

    /** @var array<int,string>|null */
    private ?array $options = null;

    private ?string $relation = null;

    private string $titleAttribute = 'name';

    private bool $contains = false;

    private bool $nullable = false;

    private bool $integer = false;

    private ?string $permission = null;

    /** @var Operator[]|null */
    private ?array $operators = null;

    private function __construct(
        public readonly string $name,
        public readonly string $type,
    ) {
        $this->assertIdentifier($name);
        $this->column = $name;
    }

    public static function text(string $name): self
    {
        return new self($name, 'text');
    }

    public static function number(string $name): self
    {
        return new self($name, 'number');
    }

    public static function boolean(string $name): self
    {
        return new self($name, 'boolean');
    }

    public static function date(string $name): self
    {
        return new self($name, 'date');
    }

    public static function select(string $name): self
    {
        return new self($name, 'select');
    }

    public static function relation(string $name): self
    {
        return new self($name, 'relation');
    }

    public function column(string $sqlColumn): self
    {
        $this->assertIdentifier($sqlColumn);
        $this->column = $sqlColumn;

        return $this;
    }

    public function labelKey(string $key): self
    {
        $this->labelKey = $key;

        return $this;
    }

    /** @param  array<int,string|int>  $values */
    public function options(array $values): self
    {
        $this->options = array_map(static fn ($v) => (string) $v, array_values($values));

        return $this;
    }

    public function relationship(string $rel, string $titleAttribute = 'name'): self
    {
        $this->assertIdentifier($rel);
        $this->assertIdentifier($titleAttribute);
        $this->relation = $rel;
        $this->titleAttribute = $titleAttribute;

        return $this;
    }

    /** Opt in to `%term%` matching — non-indexed, so it is off by default. */
    public function contains(bool $v = true): self
    {
        $this->contains = $v;

        return $this;
    }

    public function nullable(bool $v = true): self
    {
        $this->nullable = $v;

        return $this;
    }

    public function integer(bool $v = true): self
    {
        $this->integer = $v;

        return $this;
    }

    public function permission(?string $ability): self
    {
        $this->permission = $ability;

        return $this;
    }

    /** @param  Operator[]  $ops */
    public function operators(array $ops): self
    {
        // Enums are not stringable, so intersect by ->value rather than array_intersect().
        $wanted = array_map(static fn (Operator $o) => $o->value, $ops);

        $this->operators = array_values(array_filter(
            Operator::forType($this->type),
            static fn (Operator $o) => in_array($o->value, $wanted, true),
        ));

        return $this;
    }

    /** @return Operator[] */
    public function allowedOperators(): array
    {
        $ops = $this->operators ?? Operator::forType($this->type);

        if ($this->contains) {
            $ops = array_merge($ops, [Operator::Contains, Operator::NotContains]);
        }

        $unique = [];

        foreach ($ops as $op) {
            $unique[$op->value] = $op;
        }

        return array_values($unique);
    }

    public function allows(Operator $op): bool
    {
        return in_array($op, $this->allowedOperators(), true);
    }

    public function sqlColumn(): string
    {
        return $this->column;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function isInteger(): bool
    {
        return $this->integer;
    }

    public function allowsContains(): bool
    {
        return $this->contains;
    }

    public function relationName(): ?string
    {
        return $this->relation;
    }

    public function titleAttribute(): string
    {
        return $this->titleAttribute;
    }

    /** @return array<int,string>|null */
    public function optionValues(): ?array
    {
        return $this->options;
    }

    public function permissionAbility(): ?string
    {
        return $this->permission;
    }

    public function visibleTo(?Authenticatable $user): bool
    {
        if ($this->permission === null) {
            return true;
        }

        return $user instanceof Authorizable && $user->can($this->permission);
    }

    /** @return array<string,mixed> */
    public function toClientSchema(): array
    {
        return array_filter([
            'name' => $this->name,
            'type' => $this->type,
            'labelKey' => $this->labelKey,
            'operators' => array_map(static fn (Operator $o) => $o->value, $this->allowedOperators()),
            'options' => $this->options,
            'nullable' => $this->nullable,
            'integer' => $this->integer,
        ], static fn ($v) => $v !== null);
    }

    private function assertIdentifier(string $value): void
    {
        if (preg_match(self::IDENTIFIER, $value) !== 1) {
            throw new InvalidArgumentException("Invalid SQL identifier [{$value}].");
        }
    }
}
