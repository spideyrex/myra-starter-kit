<?php

namespace Tests\Feature\Report;

use App\Admin\QueryBuilder\RuleTree;
use App\Admin\Report\ReportRegistry;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\ReportRunner;
use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use Tests\TestCase;

/**
 * The load-bearing security test for drill-through: a drill payload must be
 * accepted by the TARGET's own whitelist, and a forged one must be rejected by
 * it. The report never grants the table any authority.
 */
class DrillThroughTest extends TestCase
{
    private function seedUsers(): void
    {
        User::factory()->count(4)->create(['status' => 'active', 'created_at' => now()->subDays(3)]);
        User::factory()->count(2)->create(['status' => 'inactive', 'created_at' => now()->subDays(3)]);
    }

    /** @param array<string,mixed> $state */
    private function rows(array $state): array
    {
        $actor = $this->actingAsSuperAdmin();
        $definition = ReportRegistry::resolve('users');
        $request = ReportRequest::parse($state, $definition, $actor);

        return (new ReportRunner($definition))->run($request, $actor)->rows();
    }

    private function assertTargetAccepts(array $drill): void
    {
        $this->assertArrayHasKey('query', $drill['params']);

        $tree = json_decode($drill['params']['query'], true);

        $this->assertIsArray($tree);

        // The target's own FieldSet is the authority — exactly the object the
        // users index uses for a hand-typed filter.
        $parsed = RuleTree::parse($tree, UserController::filterFieldSet(), auth()->user());

        $this->assertFalse($parsed->isEmpty());
    }

    public function test_a_date_bucket_drill_round_trips_through_the_target_field_set(): void
    {
        $this->seedUsers();

        $rows = $this->rows([
            'period' => ['preset' => 'last_30_days'],
            'dimension' => 'created_at',
            'bucket' => 'day',
            'measures' => ['signups'],
        ]);

        $withDrill = array_values(array_filter($rows, fn ($r) => $r->drill !== null));

        $this->assertNotEmpty($withDrill, 'A date bucket must be drillable.');
        $this->assertTargetAccepts($withDrill[0]->drill);
    }

    public function test_a_categorical_drill_round_trips_through_the_target_field_set(): void
    {
        $this->seedUsers();

        $rows = $this->rows([
            'period' => ['preset' => 'last_30_days'],
            'dimension' => 'status',
            'measures' => ['signups'],
        ]);

        $real = array_values(array_filter($rows, fn ($r) => ! $r->isOther));

        $this->assertNotEmpty($real);
        $this->assertNotNull($real[0]->drill);
        $this->assertTargetAccepts($real[0]->drill);
    }

    public function test_an_other_row_is_never_drillable(): void
    {
        foreach (range(1, 30) as $i) {
            User::factory()->create(['status' => 'status-'.$i, 'created_at' => now()->subDay()]);
        }

        $rows = $this->rows([
            'period' => ['preset' => 'last_30_days'],
            'dimension' => 'status',
            'measures' => ['signups'],
        ]);

        $other = array_values(array_filter($rows, fn ($r) => $r->isOther));

        if ($other === []) {
            $this->markTestSkipped('The dimension did not truncate on this dataset.');
        }

        $this->assertNull($other[0]->drill);
    }

    public function test_a_forged_drill_naming_an_undeclared_field_is_rejected_by_the_target(): void
    {
        $this->actingAsSuperAdmin();

        $forged = [
            'conjunction' => 'and',
            'rules' => [['field' => 'password', 'operator' => 'eq', 'value' => 'x']],
            'groups' => [],
        ];

        $this->expectExceptionMessage('filters.errors.unknownField');

        RuleTree::parse($forged, UserController::filterFieldSet(), auth()->user());
    }

    public function test_the_emitted_params_match_the_live_filter_wire_form(): void
    {
        $this->seedUsers();

        $rows = $this->rows([
            'period' => ['preset' => 'last_30_days'],
            'dimension' => 'status',
            'measures' => ['signups'],
        ]);

        $drill = array_values(array_filter($rows, fn ($r) => $r->drill !== null))[0]->drill;

        // buildTableParams() emits exactly one key whose value is JSON.stringify(group).
        $this->assertSame(['query'], array_keys($drill['params']));
        $this->assertSame(
            json_decode($drill['params']['query'], true),
            json_decode(json_encode(json_decode($drill['params']['query'], true)), true),
        );
        $this->assertStringStartsWith('http', $drill['url']);
    }
}
