<?php

namespace Tests\Feature\Report;

use App\Admin\QueryBuilder\FieldSet;
use App\Admin\QueryBuilder\FieldSpec;
use App\Admin\Report\Bucket;
use App\Admin\Report\ComparisonPeriod;
use App\Admin\Report\Dimension;
use App\Admin\Report\Measure;
use App\Admin\Report\PeriodPreset;
use App\Admin\Report\ReportDefinition;
use App\Admin\Report\ReportRegistry;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\ReportRunner;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReportRunnerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        CarbonImmutable::setTestNow('2026-08-16 12:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    /** Warms the role relation so a scope's hasRole() does not land inside a query budget. */
    private function warm(User $actor): User
    {
        $actor->hasRole('super-admin');

        return $actor;
    }

    /** @return array{0: array<int,string>, 1: mixed} */
    private function capture(callable $fn): array
    {
        $statements = [];
        DB::listen(function ($query) use (&$statements) {
            $statements[] = $query->sql;
        });

        $value = $fn();

        return [$statements, $value];
    }

    private function runReport(array $state, ?Authenticatable $actor = null, ?ReportDefinition $definition = null)
    {
        $definition ??= ReportRegistry::resolve('users');
        $request = ReportRequest::parse($state, $definition, $actor);

        return (new ReportRunner($definition))->run($request, $actor);
    }

    // -- the invariant ------------------------------------------------------

    public function test_query_budget_and_no_materialisation(): void
    {
        $actor = $this->warm($this->actingAsSuperAdmin());
        User::factory()->count(3)->create();

        [$statements] = $this->capture(fn () => $this->runReport(['dimension' => 'created_at'], $actor));

        $this->assertCount(2, $statements, 'A grouped run without a comparison must cost exactly 2 queries.');

        foreach ($statements as $sql) {
            $this->assertStringNotContainsStringIgnoringCase('select *', $sql);

            if (str_contains($sql, '"users"')) {
                $this->assertTrue(
                    str_contains(strtolower($sql), 'group by') || preg_match('/\b(count|sum|avg|min|max)\s*\(/i', $sql) === 1,
                    "Every statement against the report model must be grouped or aggregated: {$sql}",
                );
            }
        }
    }

    public function test_query_budget_with_a_comparison(): void
    {
        $actor = $this->warm($this->actingAsSuperAdmin());
        User::factory()->count(2)->create();

        [$statements] = $this->capture(fn () => $this->runReport(
            ['dimension' => 'created_at', 'compare' => 'previous'],
            $actor,
        ));

        $this->assertCount(4, $statements);
    }

    public function test_query_budget_for_a_relation_dimension_with_a_comparison(): void
    {
        $actor = $this->warm($this->actingAsSuperAdmin());
        $role = Role::firstOrCreate(['name' => 'manager']);
        User::factory()->count(2)->create()->each(fn (User $u) => $u->assignRole($role->name));

        [$statements] = $this->capture(fn () => $this->runReport(
            ['dimension' => 'role', 'compare' => 'previous'],
            $actor,
        ));

        $this->assertLessThanOrEqual(5, count($statements));
        $this->assertGreaterThanOrEqual(4, count($statements));
    }

    // -- ownership ----------------------------------------------------------

    public function test_the_ownership_scope_precedes_every_rule_predicate(): void
    {
        $owner = $this->warm($this->actingAsRole('admin'));
        $stranger = $this->makeUser();

        User::factory()->count(3)->create(['created_by' => $owner->id, 'status' => 'active']);
        User::factory()->count(7)->create(['created_by' => $stranger->id, 'status' => 'active']);

        [$statements, $result] = $this->capture(fn () => $this->runReport([
            'dimension' => 'created_at',
            'query' => [
                'conjunction' => 'and',
                'rules' => [['field' => 'status', 'operator' => 'in', 'value' => ['active']]],
                'groups' => [],
            ],
        ], $owner));

        $this->assertSame(3, $result->totals()['signups']);

        $grouped = collect($statements)->first(fn (string $sql) => str_contains(strtolower($sql), 'group by'));
        $this->assertNotNull($grouped);
        $this->assertLessThan(
            strpos($grouped, 'status'),
            strpos($grouped, 'created_by'),
            'The ownership scope must be applied before any client-supplied predicate.',
        );
    }

    // -- alignment ----------------------------------------------------------

    public function test_date_comparison_is_gap_filled_and_zipped_by_ordinal(): void
    {
        $actor = $this->warm($this->actingAsSuperAdmin());

        User::factory()->count(3)->create(['created_at' => '2026-07-16 10:00:00']);
        User::factory()->count(5)->create(['created_at' => '2026-06-30 10:00:00']);

        $result = $this->runReport([
            'period' => ['preset' => 'custom', 'from' => '2026-07-16', 'to' => '2026-07-31'],
            'dimension' => 'created_at',
            'bucket' => 'day',
            'compare' => 'previous',
        ], $actor);

        $rows = $result->rows();

        $this->assertCount(16, $rows);
        $this->assertSame('2026-07-16', $rows[0]->key);
        $this->assertSame(3, $rows[0]->values['signups']);
        $this->assertSame(5, $rows[0]->previous['signups']);
        $this->assertSame(0, $rows[1]->values['signups'], 'A gap must be filled with 0 for an additive measure.');
    }

    public function test_categorical_comparison_is_zipped_by_key(): void
    {
        $actor = $this->warm($this->actingAsSuperAdmin());

        // Present only in the current window.
        User::factory()->count(2)->create(['status' => 'pending', 'created_at' => '2026-08-10 10:00:00']);
        // Present only in the previous window.
        User::factory()->count(4)->create(['status' => 'suspended', 'created_at' => '2026-07-20 10:00:00']);

        $result = $this->runReport([
            'period' => ['preset' => 'custom', 'from' => '2026-08-01', 'to' => '2026-08-15'],
            'dimension' => 'status',
            'compare' => 'previous',
        ], $actor);

        $keys = array_map(fn ($r) => $r->key, $result->rows());

        $this->assertContains('pending', $keys);
        $this->assertNotContains('suspended', $keys);

        $pending = collect($result->rows())->firstWhere('key', 'pending');
        $this->assertNull($pending->previous['signups']);
    }

    // -- top-N + Other ------------------------------------------------------

    public function test_top_n_folds_the_tail_into_one_sql_computed_other_row(): void
    {
        $actor = $this->warm($this->actingAsSuperAdmin());

        for ($i = 12; $i >= 1; $i--) {
            User::factory()->count($i)->create(['status' => 'status-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)]);
        }

        $result = $this->runReport(['dimension' => 'status', 'measures' => ['signups', 'emails']], $actor);
        $rows = $result->rows();

        $this->assertTrue($result->truncated());
        $this->assertCount(11, $rows);
        $this->assertSame(11, $result->groupCount());

        $other = $rows[10];
        $this->assertTrue($other->isOther);

        $topSum = 0;
        for ($i = 0; $i < 10; $i++) {
            $topSum += $rows[$i]->values['signups'];
        }

        $this->assertSame(
            (float) ($result->totals()['signups'] - $topSum),
            (float) $other->values['signups'],
        );

        // count_distinct is NOT derivable from the buckets. Do not fake it.
        $this->assertNull($other->values['emails']);
    }

    // -- deltas -------------------------------------------------------------

    public function test_a_zero_previous_window_yields_a_null_percent(): void
    {
        $actor = $this->warm($this->actingAsSuperAdmin());

        User::factory()->count(5)->create(['created_at' => '2026-08-10 10:00:00']);

        $result = $this->runReport([
            'period' => ['preset' => 'custom', 'from' => '2026-08-01', 'to' => '2026-08-15'],
            'dimension' => 'status',
            'compare' => 'previous',
        ], $actor);

        $delta = $result->deltas()['signups'];

        $this->assertNull($delta['percent']);
        $this->assertSame('up', $delta['direction']);
    }

    public function test_invert_trend_flips_the_good_flag(): void
    {
        $actor = $this->warm($this->actingAsSuperAdmin());

        User::factory()->count(5)->create(['created_at' => '2026-08-10 10:00:00']);
        User::factory()->count(2)->create(['created_at' => '2026-07-20 10:00:00']);

        $definition = $this->churnDefinition();

        $result = $this->runReport([
            'period' => ['preset' => 'custom', 'from' => '2026-08-01', 'to' => '2026-08-15'],
            'dimension' => 'created_at',
            'bucket' => 'day',
            'compare' => 'previous',
        ], $actor, $definition);

        $delta = $result->deltas()['churn'];

        $this->assertSame('up', $delta['direction']);
        $this->assertFalse($delta['good']);
    }

    // -- empty windows ------------------------------------------------------

    public function test_an_empty_window_gap_fills_a_date_dimension(): void
    {
        $actor = $this->warm($this->actingAsSuperAdmin());

        $result = $this->runReport([
            'period' => ['preset' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-05'],
            'dimension' => 'created_at',
            'bucket' => 'day',
        ], $actor);

        $this->assertCount(5, $result->rows());
        $this->assertSame(0, $result->rows()[0]->values['signups']);
    }

    public function test_an_empty_window_returns_no_rows_for_a_categorical_dimension(): void
    {
        $actor = $this->warm($this->actingAsSuperAdmin());

        $result = $this->runReport([
            'period' => ['preset' => 'custom', 'from' => '2026-01-01', 'to' => '2026-01-05'],
            'dimension' => 'status',
        ], $actor);

        $this->assertSame([], $result->rows());
        $this->assertFalse($result->truncated());
    }

    public function test_drill_is_null_when_no_resolver_is_bound(): void
    {
        // AppServiceProvider binds a DrillResolver, so unbind it to exercise the
        // no-resolver path the runner still has to support.
        $this->app->offsetUnset(\App\Admin\Report\Contracts\DrillResolver::class);

        $actor = $this->warm($this->actingAsSuperAdmin());
        User::factory()->count(1)->create();

        $result = $this->runReport(['dimension' => 'created_at'], $actor);

        foreach ($result->rows() as $row) {
            $this->assertNull($row->drill);
        }
    }

    public function test_drill_is_populated_when_a_resolver_is_bound(): void
    {
        $this->assertTrue($this->app->bound(\App\Admin\Report\Contracts\DrillResolver::class));

        $actor = $this->warm($this->actingAsSuperAdmin());
        User::factory()->count(1)->create();

        $result = $this->runReport(['dimension' => 'created_at'], $actor);

        $rows = iterator_to_array($result->rows());
        $this->assertNotEmpty($rows);

        foreach ($rows as $row) {
            $this->assertIsArray($row->drill);
            $this->assertArrayHasKey('url', $row->drill);
        }
    }

    private function churnDefinition(): ReportDefinition
    {
        return ReportDefinition::make('churn')
            ->model(User::class)
            ->titleKey('reports.users.title')
            ->permission('reports.view')
            ->scope(fn (Builder $q, ?Authenticatable $actor) => $q)
            ->dateColumn('created_at')
            ->dimensions([
                Dimension::date('created_at')
                    ->bucket(Bucket::Day)
                    ->allowedBuckets([Bucket::Day, Bucket::Week, Bucket::Month]),
            ])
            ->measures([
                Measure::count('churn')->labelKey('reports.measure.count')->invertTrend(),
            ])
            ->filters(FieldSet::make([FieldSpec::date('created_at')]))
            ->defaults('created_at', ['churn'], PeriodPreset::Last30Days)
            ->comparisons([ComparisonPeriod::Previous])
            ->maxGroups(200);
    }
}
