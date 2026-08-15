<?php

namespace App\Admin\Report;

/**
 * The ONLY producer of an aggregate SQL fragment, exactly as Operator is the
 * only producer of a WHERE fragment.
 */
enum Aggregate: string
{
    case Count = 'count';
    case CountDistinct = 'count_distinct';
    case Sum = 'sum';
    case Average = 'avg';
    case Min = 'min';
    case Max = 'max';

    /** $wrapped is already grammar-wrapped. */
    public function sql(?string $wrapped): string
    {
        return match ($this) {
            self::Count => 'count(*)',
            self::CountDistinct => "count(distinct {$wrapped})",
            self::Sum => "coalesce(sum({$wrapped}), 0)",
            self::Average => "avg({$wrapped})",
            self::Min => "min({$wrapped})",
            self::Max => "max({$wrapped})",
        };
    }

    public function requiresColumn(): bool
    {
        return $this !== self::Count;
    }

    /** True when total(A ∪ B) == total(A) + total(B). Gates the "Other" row. */
    public function isAdditive(): bool
    {
        return $this === self::Count || $this === self::Sum;
    }

    public function labelKey(): string
    {
        return 'reports.aggregate.' . $this->value;
    }
}
