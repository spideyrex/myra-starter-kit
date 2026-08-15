<?php

namespace Tests\Feature\Report;

use App\Admin\Report\Measure;
use App\Admin\Report\ReportException;
use App\Admin\Report\ReportRegistry;
use App\Admin\Report\ReportRequest;
use App\Models\User;
use Tests\TestCase;

class ReportRequestValidationTest extends TestCase
{
    private function post(array $state): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('admin.reports.data', 'users'), ['state' => $state]);
    }

    private function parse(array $state, ?User $actor = null): ReportRequest
    {
        $definition = ReportRegistry::resolve('users');

        return ReportRequest::parse($state, $definition, $actor ?? auth()->user());
    }

    public function test_an_undeclared_measure_is_dropped_not_rejected(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post(['measures' => ['nope', 'also_nope']]);

        $response->assertOk();
        $this->assertSame(['signups'], array_column($response->json('measures'), 'key'));
    }

    public function test_an_undeclared_dimension_falls_back_to_the_default(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post(['dimension' => 'not_a_dimension']);

        $response->assertOk();
        $this->assertSame('created_at', $response->json('dimension.key'));
    }

    public function test_a_bucket_outside_the_allowed_set_falls_back_to_the_default(): void
    {
        $this->actingAsSuperAdmin();

        // `hour` is not in the users report's allowedBuckets().
        $response = $this->post(['bucket' => 'hour']);

        $response->assertOk();
        $this->assertSame('day', $response->json('period.bucket'));
    }

    public function test_a_bucket_that_would_overflow_the_group_cap_is_a_422(): void
    {
        $this->actingAsSuperAdmin();

        $definition = ReportRegistry::resolve('users');

        try {
            ReportRequest::parse([
                'period' => ['preset' => 'last_90_days'],
                'dimension' => 'created_at',
                'bucket' => 'hour',
            ], $definition, auth()->user());
            $this->fail('Expected an hourly 90-day request to be refused.');
        } catch (ReportException $e) {
            $this->assertSame('reports.errors.tooManyBuckets', $e->key);
            $this->assertSame(422, $e->getStatusCode());
        }
    }

    public function test_a_query_naming_an_unknown_field_is_a_422(): void
    {
        $this->actingAsSuperAdmin();

        $this->post([
            'query' => [
                'conjunction' => 'and',
                'rules' => [['field' => 'password', 'operator' => 'eq', 'value' => 'x']],
                'groups' => [],
            ],
        ])->assertStatus(422)->assertJsonFragment(['message' => 'filters.errors.unknownField']);
    }

    public function test_nine_cross_filters_are_refused(): void
    {
        $this->actingAsSuperAdmin();

        $cross = [];
        for ($i = 0; $i < 9; $i++) {
            $cross['w' . $i] = [
                'conjunction' => 'and',
                'rules' => [['field' => 'status', 'operator' => 'in', 'value' => ['active']]],
                'groups' => [],
            ];
        }

        $this->post(['cross' => $cross])->assertStatus(422);
    }

    public function test_eight_cross_filters_are_accepted(): void
    {
        $this->actingAsSuperAdmin();

        $cross = [];
        for ($i = 0; $i < 8; $i++) {
            $cross['w' . $i] = [
                'conjunction' => 'and',
                'rules' => [['field' => 'status', 'operator' => 'in', 'value' => ['active']]],
                'groups' => [],
            ];
        }

        $this->post(['cross' => $cross])->assertOk();
    }

    public function test_an_oversized_state_string_is_malformed(): void
    {
        $this->actingAsSuperAdmin();

        $definition = ReportRegistry::resolve('users');

        try {
            ReportRequest::parse(str_repeat('a', 20000), $definition, auth()->user());
            $this->fail('Expected an oversized state to be refused.');
        } catch (ReportException $e) {
            $this->assertSame('reports.errors.malformed', $e->key);
        }
    }

    public function test_a_measure_the_actor_cannot_see_is_hidden_and_dropped(): void
    {
        $user = $this->actingAsUser();

        $definition = ReportRegistry::resolve('users')->measures([
            Measure::count('secret')->labelKey('reports.measure.count')->permission('users.delete'),
        ]);

        $schema = $definition->toClientSchema($user);
        $this->assertNotContains('secret', array_column($schema['measures'], 'key'));

        $request = ReportRequest::parse(['measures' => ['secret']], $definition, $user);
        $this->assertNotContains('secret', array_map(fn (Measure $m) => $m->key, $request->measures()));
    }

    public function test_the_canonical_state_round_trips(): void
    {
        $this->actingAsSuperAdmin();

        $request = $this->parse(['dimension' => 'status', 'measures' => ['signups'], 'compare' => 'previous']);
        $state = $request->toArray();

        $this->assertSame('status', $state['dimension']);
        $this->assertSame('previous', $state['compare']);
        $this->assertSame(['signups'], $state['measures']);

        $again = $this->parse($state);
        $this->assertSame($state['dimension'], $again->toArray()['dimension']);
        $this->assertSame($state['measures'], $again->toArray()['measures']);
    }
}
