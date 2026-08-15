<?php

namespace Tests\Feature\Report;

use App\Models\User;
use Tests\TestCase;

/**
 * The server minted every cross-filter fragment, but the client is only the
 * transport — so each one is re-parsed against the report's own FieldSet on the
 * way back in. These tests prove that re-validation actually runs.
 */
class CrossFilterTest extends TestCase
{
    /** @param array<string,mixed> $cross */
    private function postCross(array $cross): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('admin.reports.data', 'users'), [
            'state' => [
                'period' => ['preset' => 'last_30_days'],
                'dimension' => 'status',
                'measures' => ['signups'],
                'cross' => $cross,
            ],
        ]);
    }

    private function chip(string $value): array
    {
        return [
            'conjunction' => 'and',
            'rules' => [['field' => 'status', 'operator' => 'in', 'value' => [$value]]],
            'groups' => [],
        ];
    }

    public function test_eight_cross_filters_are_accepted(): void
    {
        $this->actingAsSuperAdmin();
        User::factory()->count(3)->create(['status' => 'active']);

        $cross = [];

        foreach (range(1, 8) as $i) {
            $cross['widget-'.$i] = $this->chip('active');
        }

        $this->postCross($cross)->assertOk();
    }

    public function test_a_ninth_cross_filter_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        $cross = [];

        foreach (range(1, 9) as $i) {
            $cross['widget-'.$i] = $this->chip('active');
        }

        $this->postCross($cross)->assertStatus(422);
    }

    public function test_a_chip_naming_an_undeclared_field_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        $this->postCross([
            'rogue' => [
                'conjunction' => 'and',
                'rules' => [['field' => 'password', 'operator' => 'eq', 'value' => 'x']],
                'groups' => [],
            ],
        ])->assertStatus(422);
    }
}
