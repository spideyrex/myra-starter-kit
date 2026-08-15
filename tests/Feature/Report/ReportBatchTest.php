<?php

namespace Tests\Feature\Report;

use App\Admin\Report\ReportBatch;
use App\Admin\Report\ReportException;
use App\Models\User;
use Tests\TestCase;

class ReportBatchTest extends TestCase
{
    private function specs(int $count): array
    {
        $specs = [];

        for ($i = 0; $i < $count; $i++) {
            $specs['widget-' . $i] = ['report' => 'users', 'dimension' => 'created_at'];
        }

        return $specs;
    }

    public function test_twelve_specs_run_and_are_keyed_by_slot(): void
    {
        $actor = $this->actingAsSuperAdmin();
        User::factory()->count(2)->create();

        $results = ReportBatch::run($this->specs(12), $actor);

        $this->assertCount(12, $results);
        $this->assertArrayHasKey('widget-0', $results);
        $this->assertSame('users', $results['widget-0']['report']);
        $this->assertIsArray($results['widget-0']['rows']);
        $this->assertArrayHasKey('totals', $results['widget-0']);
        $this->assertArrayHasKey('truncated', $results['widget-0']);
    }

    public function test_thirteen_specs_are_refused(): void
    {
        $actor = $this->actingAsSuperAdmin();

        try {
            ReportBatch::run($this->specs(13), $actor);
            $this->fail('Expected a 13-report batch to be refused.');
        } catch (ReportException $e) {
            $this->assertSame('reports.errors.tooManyReports', $e->key);
        }
    }

    public function test_stat_mode_returns_the_stat_wire_shape(): void
    {
        $actor = $this->actingAsSuperAdmin();
        User::factory()->count(3)->create();

        $results = ReportBatch::run([
            'kpi' => ['report' => 'users', 'mode' => 'stat', 'measures' => ['signups'], 'compare' => 'previous'],
        ], $actor);

        $this->assertArrayHasKey('kpi', $results);
        $this->assertSame('signups', $results['kpi']['measure']);
        $this->assertArrayHasKey('spark', $results['kpi']);
        $this->assertArrayHasKey('delta', $results['kpi']);
    }

    public function test_an_unknown_report_key_is_skipped_not_fatal(): void
    {
        $actor = $this->actingAsSuperAdmin();

        $results = ReportBatch::run(['a' => ['report' => 'nope']], $actor);

        $this->assertSame([], $results);
    }

    public function test_the_endpoint_refuses_an_oversized_batch(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson(route('admin.reports.widgets'), ['widgets' => $this->specs(13)])
            ->assertStatus(422);
    }
}
