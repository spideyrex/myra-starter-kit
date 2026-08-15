<?php

namespace App\Admin\QueryBuilder;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A parsed, fully validated rule tree.
 *
 * Filament's documented behaviour for an over-limit tree is to "safely ignore"
 * it and apply no constraints — which hands back the UNFILTERED set. That is a
 * data-exposure bug wearing a convenience costume. Myra fails closed: every
 * rejection below is a 422 and no partial or empty tree is ever returned.
 */
final class RuleTree
{
    public const MAX_BYTES = 16384;

    private function __construct(
        private readonly array $root,
        private readonly int $ruleCount,
        private readonly int $depth,
    ) {}

    /** @throws QueryBuilderException */
    public static function parse(mixed $raw, FieldSet $set, ?Authenticatable $user): self
    {
        if (is_string($raw)) {
            if (strlen($raw) > self::MAX_BYTES) {
                throw QueryBuilderException::make('filters.errors.malformed');
            }
            $raw = json_decode($raw, true);
        }

        if (! is_array($raw)) {
            throw QueryBuilderException::make('filters.errors.malformed');
        }

        $encoded = json_encode($raw);
        if ($encoded === false || strlen($encoded) > self::MAX_BYTES) {
            throw QueryBuilderException::make('filters.errors.malformed');
        }

        $visible = $set->visibleTo($user);
        $count = 0;
        $maxDepth = 0;
        $root = self::group($raw, $visible, 0, $count, $maxDepth);

        if ($count > $set->maxRuleCount()) {
            throw QueryBuilderException::make('filters.errors.tooManyRules');
        }

        if ($maxDepth > $set->maxDepthAllowed()) {
            throw QueryBuilderException::make('filters.errors.tooDeep');
        }

        return new self($root, $count, $maxDepth);
    }

    public static function empty(): self
    {
        return new self(['conjunction' => 'and', 'rules' => [], 'groups' => []], 0, 0);
    }

    public function isEmpty(): bool
    {
        return $this->ruleCount === 0;
    }

    public function ruleCount(): int
    {
        return $this->ruleCount;
    }

    public function depth(): int
    {
        return $this->depth;
    }

    public function root(): array
    {
        return $this->root;
    }

    public function toArray(): array
    {
        return $this->root;
    }

    private static function group(mixed $node, FieldSet $set, int $depth, int &$count, int &$maxDepth): array
    {
        if (! is_array($node)) {
            throw QueryBuilderException::make('filters.errors.malformed');
        }

        $maxDepth = max($maxDepth, $depth + 1);

        $conjunction = $node['conjunction'] ?? 'and';
        if (! in_array($conjunction, ['and', 'or'], true)) {
            throw QueryBuilderException::make('filters.errors.malformed');
        }

        $rules = $node['rules'] ?? [];
        $groups = $node['groups'] ?? [];

        if (! is_array($rules) || ! is_array($groups)) {
            throw QueryBuilderException::make('filters.errors.malformed');
        }

        $parsedRules = [];
        foreach ($rules as $rule) {
            $count++;
            $parsedRules[] = self::rule($rule, $set);
        }

        $parsedGroups = [];
        foreach ($groups as $child) {
            $parsedGroups[] = self::group($child, $set, $depth + 1, $count, $maxDepth);
        }

        return ['conjunction' => $conjunction, 'rules' => $parsedRules, 'groups' => $parsedGroups];
    }

    private static function rule(mixed $rule, FieldSet $set): array
    {
        if (! is_array($rule) || ! isset($rule['field'], $rule['operator'])
            || ! is_string($rule['field']) || ! is_string($rule['operator'])) {
            throw QueryBuilderException::make('filters.errors.malformed');
        }

        // The permission case deliberately reuses `unknownField`: a distinct
        // message would be a field-enumeration oracle.
        $field = $set->field($rule['field']);
        if ($field === null) {
            throw QueryBuilderException::make('filters.errors.unknownField');
        }

        $operator = Operator::tryFrom($rule['operator']);
        if ($operator === null || ! $field->allows($operator)) {
            throw QueryBuilderException::make('filters.errors.unknownOperator');
        }

        return [
            'field' => $field->name,
            'operator' => $operator->value,
            'value' => self::value($rule['value'] ?? null, $field, $operator),
        ];
    }

    private static function value(mixed $value, FieldSpec $field, Operator $operator): mixed
    {
        $arity = $operator->arity();

        if ($arity === 0) {
            return null;
        }

        if ($arity === 2) {
            if (! is_array($value) || count($value) !== 2) {
                throw QueryBuilderException::make('filters.errors.badValue');
            }

            return [
                self::scalar($value[array_key_first($value)], $field, $operator),
                self::scalar($value[array_key_last($value)], $field, $operator),
            ];
        }

        if ($arity === -1) {
            $list = is_array($value) ? array_values($value) : [$value];
            if ($list === [] || count($list) > 32) {
                throw QueryBuilderException::make('filters.errors.badValue');
            }

            return array_map(static fn ($v) => self::scalar($v, $field, $operator), $list);
        }

        if (is_array($value)) {
            throw QueryBuilderException::make('filters.errors.badValue');
        }

        return self::scalar($value, $field, $operator);
    }

    private static function scalar(mixed $value, FieldSpec $field, Operator $operator): string|int|float
    {
        if ($value === null || is_array($value) || is_bool($value) || $value === '') {
            throw QueryBuilderException::make('filters.errors.badValue');
        }

        $value = (string) $value;

        if (strlen($value) > 200) {
            throw QueryBuilderException::make('filters.errors.badValue');
        }

        if ($field->optionValues() !== null && ! in_array($value, $field->optionValues(), true)) {
            throw QueryBuilderException::make('filters.errors.badValue');
        }

        if (in_array($operator, [Operator::CountAtLeast, Operator::CountAtMost, Operator::CountExactly, Operator::InMonth, Operator::InYear], true)) {
            if (! is_numeric($value)) {
                throw QueryBuilderException::make('filters.errors.badValue');
            }

            return (int) $value;
        }

        if ($field->type === 'number') {
            if (! is_numeric($value)) {
                throw QueryBuilderException::make('filters.errors.badValue');
            }

            return $field->isInteger() ? (int) $value : (float) $value;
        }

        if ($field->type === 'date' && strtotime($value) === false) {
            throw QueryBuilderException::make('filters.errors.badValue');
        }

        return $value;
    }
}
