<?php

namespace Tests\Unit\Ai;

use App\Admin\QueryBuilder\FieldSet;
use App\Admin\QueryBuilder\FieldSpec;
use App\Admin\QueryBuilder\FilterScopes;
use App\Admin\QueryBuilder\QueryBuilderException;
use App\Admin\QueryBuilder\RuleCompiler;
use App\Admin\QueryBuilder\RuleTree;
use App\Models\User;
use App\Services\Ai\AiCompileException;
use App\Services\Ai\Compiler\RuleTreeCompiler;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Support\FakeAiService;
use Tests\TestCase;

/**
 * The model is hostile input. Every one of these asserts a refusal happens
 * BEFORE a query is built, not after.
 */
class RuleTreeCompilerTest extends TestCase
{
    /** @param string[] $replies */
    private function compiler(array $replies): array
    {
        $fake = new FakeAiService($replies);

        return [new RuleTreeCompiler($fake), $fake];
    }

    private function set(): FieldSet
    {
        return FilterScopes::resolve('users');
    }

    public function test_a_sql_fragment_trips_the_guard_and_runs_no_query(): void
    {
        [$compiler] = $this->compiler(['SELECT * FROM users']);

        $queries = 0;
        DB::listen(function () use (&$queries) { $queries++; });

        try {
            $compiler->compile('everyone', $this->set(), null);
            $this->fail('A SQL fragment must never compile.');
        } catch (AiCompileException $e) {
            $this->assertSame('assistant.errors.sqlDetected', $e->key);
        }

        $this->assertSame(0, $queries, 'No query may run for a refused compile.');
    }

    public function test_a_hallucinated_column_is_refused(): void
    {
        [$compiler] = $this->compiler([
            '{"conjunction":"and","rules":[{"field":"password","operator":"eq","value":"x"}],"groups":[]}',
        ]);

        try {
            $compiler->compile('users with password x', $this->set(), null);
            $this->fail('An unknown field must never compile.');
        } catch (QueryBuilderException $e) {
            $this->assertSame('filters.errors.unknownField', $e->key);
        }
    }

    public function test_an_unknown_operator_is_refused(): void
    {
        [$compiler] = $this->compiler([
            '{"conjunction":"and","rules":[{"field":"name","operator":"raw","value":"x"}],"groups":[]}',
        ]);

        try {
            $compiler->compile('anything', $this->set(), null);
            $this->fail('An unknown operator must never compile.');
        } catch (QueryBuilderException $e) {
            $this->assertSame('filters.errors.unknownOperator', $e->key);
        }
    }

    public function test_an_injection_flavoured_value_compiles_to_a_bound_parameter(): void
    {
        [$compiler] = $this->compiler([
            '{"conjunction":"and","rules":[{"field":"name","operator":"eq","value":"1=1 OR 1=1"}],"groups":[]}',
        ]);

        $tree = $compiler->compile('sneaky', $this->set(), null);
        $this->assertInstanceOf(RuleTree::class, $tree);

        $set = $this->set();
        $query = User::query();
        (new RuleCompiler($set))->apply($query, $tree);

        $this->assertStringNotContainsString('1=1', $query->toSql());
        $this->assertContains('1=1 OR 1=1', $query->getBindings());
    }

    /** @return string a tree of $n `name eq` rules */
    private function manyRules(int $n): string
    {
        $rules = [];

        foreach (range(1, $n) as $i) {
            $rules[] = ['field' => 'name', 'operator' => 'eq', 'value' => 'user'.$i];
        }

        return json_encode(['conjunction' => 'and', 'rules' => $rules, 'groups' => []]);
    }

    public function test_more_rules_than_the_field_set_allows_are_refused(): void
    {
        [$compiler] = $this->compiler([$this->manyRules(10)]);

        try {
            $compiler->compile('ten things', $this->set()->maxRules(5), null);
            $this->fail('An over-cap tree must never compile.');
        } catch (QueryBuilderException $e) {
            $this->assertSame('filters.errors.tooManyRules', $e->key);
        }
    }

    public function test_a_grossly_over_cap_tree_is_stopped_by_the_shape_gate_first(): void
    {
        // ViewShape caps at 25 before RuleTree ever runs. The shape gate reports
        // filters.errors.malformed; RuleTree would have said tooManyRules. The
        // key is what proves the cheap bounded gate is genuinely in front.
        [$compiler, $fake] = $this->compiler([$this->manyRules(40)]);

        try {
            $compiler->compile('forty things', $this->set(), null);
            $this->fail('A 40-rule tree must never compile.');
        } catch (AiCompileException $e) {
            $this->assertSame('filters.errors.malformed', $e->key);
        }

        $this->assertSame(2, $fake->calls, 'The shape gate is retryable like any other refusal.');
    }

    public function test_the_shape_gate_never_leaks_a_validation_exception(): void
    {
        // A ValidationException here would name a request field that does not
        // exist and would bypass the repair retry entirely.
        [$compiler] = $this->compiler(['{"conjunction":"and","rules":[],"groups":[],"extra":1}']);

        try {
            $compiler->compile('anything', $this->set(), null);
            $this->fail('An off-shape tree must never compile.');
        } catch (ValidationException) {
            $this->fail('ViewShape must not leak ValidationException out of the compiler.');
        } catch (AiCompileException $e) {
            $this->assertSame('filters.errors.malformed', $e->key);
        }
    }

    public function test_a_valid_tree_returns_a_rule_tree(): void
    {
        [$compiler] = $this->compiler([
            '{"conjunction":"and","rules":[{"field":"status","operator":"in","value":["active"]}],"groups":[]}',
        ]);

        $tree = $compiler->compile('active users', $this->set(), null);

        $this->assertInstanceOf(RuleTree::class, $tree);
        $this->assertSame(1, $tree->ruleCount());
    }

    public function test_one_repair_retry_happens_and_only_one(): void
    {
        [$compiler, $fake] = $this->compiler([
            'not json at all',
            '{"conjunction":"and","rules":[{"field":"status","operator":"in","value":["active"]}],"groups":[]}',
            '{"conjunction":"and","rules":[],"groups":[]}',
        ]);

        $tree = $compiler->compile('active users', $this->set(), null);

        $this->assertSame(2, $fake->calls);
        $this->assertSame(1, $tree->ruleCount());
        $this->assertStringContainsString('rejected', $fake->userPrompts[1]);
    }

    public function test_the_second_failure_surfaces_the_first_error(): void
    {
        [$compiler, $fake] = $this->compiler(['nonsense', 'still nonsense']);

        $this->expectException(AiCompileException::class);

        try {
            $compiler->compile('active users', $this->set(), null);
        } finally {
            $this->assertSame(2, $fake->calls, 'Exactly one repair retry.');
        }
    }

    public function test_the_provider_never_sees_a_sql_identifier_a_table_name_or_a_row(): void
    {
        User::factory()->create(['name' => 'Zaphod Beeblebrox', 'email' => 'zaphod@example.test']);

        // `label` is exposed under a name that is NOT its SQL column, so a leak
        // of column() is detectable rather than indistinguishable from the name.
        $set = FieldSet::make([
            FieldSpec::text('label')->column('internal_secret_column')->labelKey('filters.field.name'),
            FieldSpec::select('status')->options(['active', 'suspended']),
        ]);

        [$compiler, $fake] = $this->compiler([
            '{"conjunction":"and","rules":[{"field":"status","operator":"in","value":["active"]}],"groups":[]}',
        ]);

        $compiler->compile('active rows', $set, null);

        $prompt = $fake->systemPrompts[0];

        $this->assertStringNotContainsString('internal_secret_column', $prompt);
        $this->assertStringNotContainsString('users', $prompt, 'No table name may leave the box.');
        $this->assertStringNotContainsString('Zaphod', $prompt, 'No row data may leave the box.');
        $this->assertStringNotContainsString('zaphod@example.test', $prompt);
        $this->assertStringContainsString('"label"', $prompt);
    }

    public function test_an_empty_or_oversized_prompt_never_reaches_the_provider(): void
    {
        [$compiler, $fake] = $this->compiler(['{"conjunction":"and","rules":[],"groups":[]}']);

        try {
            $compiler->compile(str_repeat('a', 501), $this->set(), null);
            $this->fail('An over-length prompt must be refused.');
        } catch (AiCompileException $e) {
            $this->assertSame('assistant.errors.promptLength', $e->key);
        }

        $this->assertSame(0, $fake->calls);
    }
}
