<?php

namespace Tests\Feature;

use App\Admin\QueryBuilder\FieldSet;
use App\Admin\QueryBuilder\FieldSpec;
use App\Admin\QueryBuilder\Operator;
use App\Admin\QueryBuilder\QueryBuilderException;
use App\Admin\Traits\HandlesQueryBuilder;
use App\Models\User;
use App\Support\Myra;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Tests\TestCase;

class QueryCompilerTest extends TestCase
{
    use HandlesQueryBuilder;

    private function fieldSet(): FieldSet
    {
        return FieldSet::make([
            FieldSpec::text('name'),
            FieldSpec::text('email')->contains(),
            FieldSpec::text('phone')->nullable(),
            FieldSpec::number('id')->integer(),
            FieldSpec::select('status')->options(['active', 'suspended', 'pending']),
            FieldSpec::date('created_at'),
            FieldSpec::relation('roles')->relationship('roles', 'name'),
            FieldSpec::text('secret_note')->permission('users.view'),
        ])->maxRules(25)->maxDepth(3);
    }

    private function request(mixed $tree, ?User $actor = null): Request
    {
        $request = Request::create(Myra::adminPath('demo/advanced-filters'), 'GET', [
            'q' => is_string($tree) ? $tree : json_encode($tree),
        ]);
        $request->setUserResolver(fn () => $actor);

        return $request;
    }

    private function compile(mixed $tree, ?Builder $base = null, ?User $actor = null): Builder
    {
        return $this->applyQueryBuilder(
            $base ?? User::query(),
            $this->request($tree, $actor),
            'q',
            $this->fieldSet(),
        );
    }

    private function assertRejected(mixed $tree, string $key, ?User $actor = null): void
    {
        $before = User::count();

        try {
            $this->compile($tree, null, $actor);
            $this->fail("Expected the tree to be rejected with [{$key}].");
        } catch (QueryBuilderException $e) {
            $this->assertSame(422, $e->getStatusCode());
            $this->assertSame($key, $e->key);
        }

        // Fails closed: the caller never receives a builder, so it can never
        // hand back the unfiltered set.
        $this->assertSame($before, User::count());
    }

    private function group(array $rules, string $conjunction = 'and', array $groups = []): array
    {
        return ['conjunction' => $conjunction, 'rules' => $rules, 'groups' => $groups];
    }

    // --- rejection table ----------------------------------------------------

    public function test_an_unknown_field_is_rejected(): void
    {
        $this->assertRejected(
            $this->group([['field' => 'password', 'operator' => 'eq', 'value' => 'x']]),
            'filters.errors.unknownField',
        );
    }

    public function test_an_unknown_operator_is_rejected(): void
    {
        $this->assertRejected(
            $this->group([['field' => 'name', 'operator' => 'DROP', 'value' => 'x']]),
            'filters.errors.unknownOperator',
        );
    }

    public function test_an_operator_valid_for_another_type_is_rejected(): void
    {
        $this->assertRejected(
            $this->group([['field' => 'name', 'operator' => 'in_month', 'value' => '3']]),
            'filters.errors.unknownOperator',
        );
    }

    public function test_a_select_value_outside_options_is_rejected(): void
    {
        $this->assertRejected(
            $this->group([['field' => 'status', 'operator' => 'in', 'value' => ['deleted']]]),
            'filters.errors.badValue',
        );
    }

    public function test_between_with_one_operand_is_rejected(): void
    {
        $this->assertRejected(
            $this->group([['field' => 'id', 'operator' => 'between', 'value' => ['5']]]),
            'filters.errors.badValue',
        );
    }

    public function test_a_non_numeric_value_into_a_number_field_is_rejected(): void
    {
        $this->assertRejected(
            $this->group([['field' => 'id', 'operator' => 'gt', 'value' => 'abc']]),
            'filters.errors.badValue',
        );
    }

    public function test_twenty_six_rules_are_rejected(): void
    {
        $rules = array_fill(0, 26, ['field' => 'name', 'operator' => 'eq', 'value' => 'x']);

        $this->assertRejected($this->group($rules), 'filters.errors.tooManyRules');
    }

    public function test_depth_four_is_rejected(): void
    {
        $level4 = $this->group([['field' => 'name', 'operator' => 'eq', 'value' => 'x']]);
        $level3 = $this->group([], 'and', [$level4]);
        $level2 = $this->group([], 'and', [$level3]);
        $level1 = $this->group([], 'and', [$level2]);

        $this->assertRejected($level1, 'filters.errors.tooDeep');
    }

    public function test_a_twenty_kilobyte_tree_is_rejected(): void
    {
        $this->assertRejected(
            $this->group([['field' => 'name', 'operator' => 'eq', 'value' => str_repeat('a', 20000)]]),
            'filters.errors.malformed',
        );
    }

    public function test_a_field_the_actor_cannot_see_is_indistinguishable_from_an_unknown_field(): void
    {
        $actor = $this->makeUser();

        $unknown = null;
        $forbidden = null;

        try {
            $this->compile($this->group([['field' => 'nope', 'operator' => 'eq', 'value' => 'x']]), null, $actor);
        } catch (QueryBuilderException $e) {
            $unknown = $e;
        }

        try {
            $this->compile($this->group([['field' => 'secret_note', 'operator' => 'eq', 'value' => 'x']]), null, $actor);
        } catch (QueryBuilderException $e) {
            $forbidden = $e;
        }

        $this->assertNotNull($unknown);
        $this->assertNotNull($forbidden);
        $this->assertSame($unknown->key, $forbidden->key);
        $this->assertSame($unknown->getMessage(), $forbidden->getMessage());
    }

    public function test_contains_requires_the_field_to_opt_in(): void
    {
        $this->assertRejected(
            $this->group([['field' => 'name', 'operator' => 'contains', 'value' => 'ali']]),
            'filters.errors.unknownOperator',
        );

        $sql = $this->compile($this->group([['field' => 'email', 'operator' => 'contains', 'value' => 'ali']]));

        $this->assertContains('%ali%', $sql->getQuery()->getBindings());
    }

    public function test_malformed_json_is_rejected(): void
    {
        $this->assertRejected('{not json', 'filters.errors.malformed');
    }

    // --- compilation --------------------------------------------------------

    public function test_starts_with_compiles_to_an_index_usable_like(): void
    {
        $sql = $this->compile($this->group([['field' => 'name', 'operator' => 'starts_with', 'value' => 'Ali']]));

        $this->assertContains('Ali%', $sql->getQuery()->getBindings());
        $this->assertStringContainsString('like', strtolower($sql->toSql()));
    }

    public function test_count_gte_compiles_to_a_correlated_subquery_never_with_count_plus_having(): void
    {
        $sql = $this->compile($this->group([['field' => 'roles', 'operator' => 'count_gte', 'value' => '2']]));

        $lowered = strtolower($sql->toSql());
        $this->assertStringContainsString('select count(*)', $lowered);
        $this->assertStringNotContainsString('roles_count', $lowered);
        $this->assertStringNotContainsString('having', $lowered);
    }

    public function test_related_to_compiles_to_a_correlated_exists(): void
    {
        $sql = $this->compile($this->group([['field' => 'roles', 'operator' => 'related_to', 'value' => ['admin']]]));

        $this->assertStringContainsString('exists', strtolower($sql->toSql()));
        $this->assertContains('admin', $sql->getQuery()->getBindings());
    }

    public function test_a_top_level_or_cannot_escape_the_ownership_scope(): void
    {
        $owner = $this->makeUser(['name' => 'Owner']);
        // created_by is not fillable — set it past the guard, as the service does.
        $mine = User::factory()->create(['name' => 'Alice Mine']);
        $mine->forceFill(['created_by' => $owner->id])->save();
        $theirs = User::factory()->create(['name' => 'Alice Theirs']);

        $scoped = User::query()->where('created_by', $owner->id);

        $tree = $this->group([
            ['field' => 'name', 'operator' => 'starts_with', 'value' => 'Alice'],
            ['field' => 'name', 'operator' => 'starts_with', 'value' => 'Owner'],
        ], 'or');

        $ids = $this->compile($tree, $scoped)->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_a_valid_tree_actually_filters(): void
    {
        $active = User::factory()->create(['name' => 'Zed', 'status' => 'active']);
        User::factory()->create(['name' => 'Zed Two', 'status' => 'suspended']);

        $tree = $this->group([
            ['field' => 'status', 'operator' => 'in', 'value' => ['active']],
            ['field' => 'name', 'operator' => 'starts_with', 'value' => 'Zed'],
        ]);

        $this->assertSame([$active->id], $this->compile($tree)->pluck('id')->all());
    }

    public function test_is_blank_takes_no_operand(): void
    {
        $withPhone = User::factory()->create(['phone' => '0123456789']);
        $withoutPhone = User::factory()->create(['phone' => null]);

        $ids = $this->compile($this->group([['field' => 'phone', 'operator' => 'is_blank']]))
            ->pluck('id')->all();

        $this->assertContains($withoutPhone->id, $ids);
        $this->assertNotContains($withPhone->id, $ids);
    }

    public function test_a_blank_parameter_leaves_the_query_untouched(): void
    {
        $request = Request::create('/x', 'GET', []);
        $before = User::query();

        $this->assertSame($before, $this->applyQueryBuilder($before, $request, 'q', $this->fieldSet()));
    }

    public function test_a_malformed_identifier_throws_at_construction(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FieldSpec::text('a; DROP TABLE');
    }

    public function test_column_is_validated_at_construction_too(): void
    {
        $this->expectException(InvalidArgumentException::class);

        FieldSpec::text('name')->column('name); DROP TABLE users; --');
    }

    public function test_a_field_can_narrow_its_operator_set(): void
    {
        $spec = FieldSpec::text('name')->operators([Operator::Equals]);

        $this->assertTrue($spec->allows(Operator::Equals));
        $this->assertFalse($spec->allows(Operator::StartsWith));

        $this->assertSame(['eq'], array_map(
            fn (Operator $o) => $o->value,
            $spec->allowedOperators(),
        ));
    }

    public function test_allowed_operators_are_deduplicated(): void
    {
        $spec = FieldSpec::text('bio')
            ->operators([Operator::Equals, Operator::Equals, Operator::Contains])
            ->contains();

        $this->assertSame(['eq', 'contains', 'not_contains'], array_map(
            fn (Operator $o) => $o->value,
            $spec->allowedOperators(),
        ));
    }
}
