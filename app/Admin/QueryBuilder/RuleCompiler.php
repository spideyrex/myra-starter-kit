<?php

namespace App\Admin\QueryBuilder;

use App\Support\Sql;
use Illuminate\Database\Eloquent\Builder;

/**
 * Compiles a validated RuleTree into bound Eloquent predicates. Column names
 * come only from FieldSpec::column(); every value is a bound parameter.
 */
final class RuleCompiler
{
    public function __construct(private readonly FieldSet $set) {}

    public function apply(Builder $query, RuleTree $tree): Builder
    {
        if ($tree->isEmpty()) {
            return $query;
        }

        // ONE outer where(). A top-level `or` conjunction can never escape the
        // ownership scope already applied to $query.
        return $query->where(fn (Builder $q) => $this->group($q, $tree->root()));
    }

    private function group(Builder $query, array $group): Builder
    {
        $boolean = $group['conjunction'] === 'or' ? 'or' : 'and';

        foreach ($group['rules'] as $rule) {
            $query->where(fn (Builder $q) => $this->rule($q, $rule), null, null, $boolean);
        }

        foreach ($group['groups'] as $child) {
            $query->where(fn (Builder $q) => $this->group($q, $child), null, null, $boolean);
        }

        return $query;
    }

    private function rule(Builder $q, array $rule): Builder
    {
        $field = $this->set->field($rule['field']);
        if ($field === null) {
            return $q;
        }

        $operator = Operator::from($rule['operator']);
        $col = $field->sqlColumn();
        $value = $rule['value'];

        return match ($operator) {
            Operator::Equals => $q->where($col, '=', $value),
            Operator::NotEquals => $q->where($col, '!=', $value),
            Operator::Contains => Sql::whereLike($q, $col, (string) $value),
            Operator::NotContains => $q->whereNot(fn (Builder $b) => Sql::whereLike($b, $col, (string) $value)),
            Operator::StartsWith => Sql::whereLike($q, $col, (string) $value, 'starts'),
            Operator::EndsWith => Sql::whereLike($q, $col, (string) $value, 'ends'),
            Operator::GreaterThan => $q->where($col, '>', $value),
            Operator::GreaterOrEqual => $q->where($col, '>=', $value),
            Operator::LessThan => $q->where($col, '<', $value),
            Operator::LessOrEqual => $q->where($col, '<=', $value),
            Operator::Between => $q->whereBetween($col, [$value[0], $value[1]]),
            Operator::In => $q->whereIn($col, (array) $value),
            Operator::NotIn => $q->whereNotIn($col, (array) $value),
            Operator::IsFilled => $field->isNullable()
                ? $q->whereNotNull($col)
                : $q->where($col, '!=', ''),
            Operator::IsBlank => $field->isNullable()
                ? $q->whereNull($col)
                : $q->where($col, '=', ''),
            Operator::IsTrue => $q->where($col, '=', true),
            Operator::IsFalse => $q->where($col, '=', false),
            Operator::DateIs => $q->whereDate($col, '=', $value),
            Operator::DateBefore => $q->whereDate($col, '<', $value),
            Operator::DateAfter => $q->whereDate($col, '>', $value),
            Operator::DateBetween => $q->whereBetween($col, [$value[0] . ' 00:00:00', $value[1] . ' 23:59:59']),
            Operator::InMonth => $q->whereMonth($col, (int) $value),
            Operator::InYear => $q->whereYear($col, (int) $value),
            // Correlated EXISTS, never withCount()+having.
            Operator::RelatedTo => $q->whereHas(
                (string) $field->relationName(),
                fn ($r) => $r->whereIn($field->titleAttribute(), (array) $value),
            ),
            Operator::NotRelatedTo => $q->whereDoesntHave(
                (string) $field->relationName(),
                fn ($r) => $r->whereIn($field->titleAttribute(), (array) $value),
            ),
            Operator::CountAtLeast => $q->whereHas((string) $field->relationName(), null, '>=', (int) $value),
            Operator::CountAtMost => $q->whereHas((string) $field->relationName(), null, '<=', (int) $value),
            Operator::CountExactly => $q->whereHas((string) $field->relationName(), null, '=', (int) $value),
        };
    }
}
