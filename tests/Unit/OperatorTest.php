<?php

namespace Tests\Unit;

use App\Admin\QueryBuilder\Operator;
use Tests\TestCase;

class OperatorTest extends TestCase
{
    public function test_an_arbitrary_string_never_becomes_an_operator(): void
    {
        $this->assertNull(Operator::tryFrom('DROP'));
        $this->assertNull(Operator::tryFrom('='));
        $this->assertNull(Operator::tryFrom('1=1'));
    }

    public function test_for_type_returns_the_documented_set(): void
    {
        $values = fn (string $type) => array_map(fn (Operator $o) => $o->value, Operator::forType($type));

        $this->assertSame(['eq', 'neq', 'starts_with', 'ends_with', 'is_filled', 'is_blank'], $values('text'));
        $this->assertSame(['eq', 'neq', 'gt', 'gte', 'lt', 'lte', 'between', 'is_filled', 'is_blank'], $values('number'));
        $this->assertSame(['is_true', 'is_false'], $values('boolean'));
        $this->assertSame(['date_is', 'date_before', 'date_after', 'date_between', 'in_month', 'in_year', 'is_filled', 'is_blank'], $values('date'));
        $this->assertSame(['in', 'not_in', 'is_filled', 'is_blank'], $values('select'));
        $this->assertSame(['related_to', 'not_related_to', 'count_gte', 'count_lte', 'count_eq'], $values('relation'));
    }

    public function test_contains_is_opt_in_and_absent_from_the_default_text_set(): void
    {
        $this->assertNotContains(Operator::Contains, Operator::forType('text'));
        $this->assertNotContains(Operator::NotContains, Operator::forType('text'));
    }

    public function test_arity_matches_the_operand_count(): void
    {
        $this->assertSame(0, Operator::IsFilled->arity());
        $this->assertSame(0, Operator::IsTrue->arity());
        $this->assertSame(2, Operator::Between->arity());
        $this->assertSame(2, Operator::DateBetween->arity());
        $this->assertSame(-1, Operator::In->arity());
        $this->assertSame(-1, Operator::RelatedTo->arity());
        $this->assertSame(1, Operator::Equals->arity());
    }

    public function test_every_case_has_a_label_key_present_in_the_english_locale(): void
    {
        $locale = json_decode(
            file_get_contents(resource_path('js/i18n/locales/en.json')),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );

        foreach (Operator::cases() as $case) {
            $this->assertSame("filters.op.{$case->value}", $case->labelKey());
            $this->assertArrayHasKey(
                $case->value,
                $locale['filters']['op'],
                "Missing i18n key filters.op.{$case->value}",
            );
        }
    }
}
