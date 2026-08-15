<?php

namespace App\Admin\QueryBuilder;

/**
 * The closed operator set. `Operator::tryFrom()` is the only conversion from a
 * client string, and a null result is a 422 — there is no path from a request
 * string to raw SQL.
 */
enum Operator: string
{
    case Equals = 'eq';
    case NotEquals = 'neq';
    case Contains = 'contains';
    case NotContains = 'not_contains';
    case StartsWith = 'starts_with';
    case EndsWith = 'ends_with';
    case GreaterThan = 'gt';
    case GreaterOrEqual = 'gte';
    case LessThan = 'lt';
    case LessOrEqual = 'lte';
    case Between = 'between';
    case In = 'in';
    case NotIn = 'not_in';
    case IsFilled = 'is_filled';
    case IsBlank = 'is_blank';
    case IsTrue = 'is_true';
    case IsFalse = 'is_false';
    case DateIs = 'date_is';
    case DateBefore = 'date_before';
    case DateAfter = 'date_after';
    case DateBetween = 'date_between';
    case InMonth = 'in_month';
    case InYear = 'in_year';
    case RelatedTo = 'related_to';
    case NotRelatedTo = 'not_related_to';
    case CountAtLeast = 'count_gte';
    case CountAtMost = 'count_lte';
    case CountExactly = 'count_eq';

    /** 0 = no operand, 1 = one, 2 = a pair, -1 = a list. */
    public function arity(): int
    {
        return match ($this) {
            self::IsFilled, self::IsBlank, self::IsTrue, self::IsFalse => 0,
            self::Between, self::DateBetween => 2,
            self::In, self::NotIn, self::RelatedTo, self::NotRelatedTo => -1,
            default => 1,
        };
    }

    public function labelKey(): string
    {
        return 'filters.op.' . $this->value;
    }

    /** @return self[] */
    public static function forType(string $type): array
    {
        return match ($type) {
            'text' => [self::Equals, self::NotEquals, self::StartsWith, self::EndsWith, self::IsFilled, self::IsBlank],
            'number' => [self::Equals, self::NotEquals, self::GreaterThan, self::GreaterOrEqual, self::LessThan, self::LessOrEqual, self::Between, self::IsFilled, self::IsBlank],
            'boolean' => [self::IsTrue, self::IsFalse],
            'date' => [self::DateIs, self::DateBefore, self::DateAfter, self::DateBetween, self::InMonth, self::InYear, self::IsFilled, self::IsBlank],
            'select' => [self::In, self::NotIn, self::IsFilled, self::IsBlank],
            'relation' => [self::RelatedTo, self::NotRelatedTo, self::CountAtLeast, self::CountAtMost, self::CountExactly],
            default => [],
        };
    }
}
