<?php

namespace Tests\Feature\Ai;

use App\Admin\QueryBuilder\FilterScopes;
use App\Admin\QueryBuilder\RuleCompiler;
use App\Admin\QueryBuilder\RuleTree;
use App\Models\User;
use App\Services\Ai\AiService;
use Tests\Support\FakeAiService;
use Tests\TestCase;

class AiFilterControllerTest extends TestCase
{
    private const TREE = '{"conjunction":"and","rules":[{"field":"status","operator":"in","value":["suspended"]}],"groups":[]}';

    private function enable(bool $filter = true, bool $provider = true, string $reply = self::TREE): FakeAiService
    {
        config()->set('myra.ai.features.filter', $filter);

        $fake = new FakeAiService([$reply], $provider);
        $this->app->instance(AiService::class, $fake);

        return $fake;
    }

    private function postCompile(array $body = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson(route('admin.ai.filter'), $body + [
            'prompt' => 'suspended accounts',
            'scope' => 'users',
        ]);
    }

    public function test_it_404s_when_the_feature_flag_is_off(): void
    {
        $this->actingAsSuperAdmin();
        $this->enable(filter: false);

        $this->postCompile()->assertNotFound();
    }

    public function test_it_404s_when_the_provider_is_disabled(): void
    {
        $this->actingAsSuperAdmin();
        $this->enable(provider: false);

        $this->postCompile()->assertNotFound();
    }

    public function test_it_403s_without_the_ability(): void
    {
        $this->actingAsUser();
        $this->enable();

        $this->postCompile()->assertForbidden();
    }

    public function test_an_unknown_scope_is_refused_by_validation(): void
    {
        $this->actingAsSuperAdmin();
        $this->enable();

        $this->postCompile(['scope' => 'secrets'])->assertStatus(422);
    }

    public function test_the_eleventh_call_in_a_minute_is_throttled(): void
    {
        $this->actingAsSuperAdmin();
        $this->enable();

        foreach (range(1, 10) as $ignored) {
            $this->postCompile()->assertOk();
        }

        $this->postCompile()->assertStatus(429);
    }

    public function test_a_compiled_tree_is_returned_unapplied(): void
    {
        $this->actingAsSuperAdmin();
        $this->enable();

        $response = $this->postCompile()->assertOk();

        $response->assertJsonPath('applied', false);
        $this->assertSame('and', $response->json('tree.conjunction'));
        $this->assertSame('status', $response->json('tree.rules.0.field'));
    }

    /**
     * §0.5 REAL PAYLOAD. The tree the server actually produced is fed back into
     * the ordinary users-index filter path and must select exactly what a
     * hand-built equivalent selects — the AI path and the manual path are one path.
     */
    public function test_the_real_tree_filters_identically_to_a_hand_built_one(): void
    {
        $admin = $this->actingAsSuperAdmin();
        $this->enable();

        User::factory()->count(2)->create(['status' => 'suspended', 'created_by' => $admin->id]);
        User::factory()->count(3)->create(['status' => 'active', 'created_by' => $admin->id]);

        $tree = $this->postCompile()->assertOk()->json('tree');

        $set = FilterScopes::resolve('users');

        $fromAi = (new RuleCompiler($set))->apply(User::query(), RuleTree::parse($tree, $set, $admin))->pluck('id');

        $manual = ['conjunction' => 'and', 'rules' => [
            ['field' => 'status', 'operator' => 'in', 'value' => ['suspended']],
        ], 'groups' => []];

        $fromHand = (new RuleCompiler($set))->apply(User::query(), RuleTree::parse($manual, $set, $admin))->pluck('id');

        $this->assertSame($fromHand->sort()->values()->all(), $fromAi->sort()->values()->all());
        $this->assertCount(2, $fromAi);

        // The index route re-parses the very same blob. Re-validation is the point.
        $this->get(route('admin.users.index', ['query_builder' => json_encode($tree)]))->assertOk();

        $this->writeFixture('ai-filter-tree', [
            'scope' => 'users',
            'applied' => false,
            'tree' => $tree,
            'constraints' => $set->toClientSchema($admin),
        ]);
    }

    /** §0.6 fixture bridge. */
    private function writeFixture(string $name, array $payload): void
    {
        $path = base_path("tests/js/fixtures/{$name}.json");
        $actual = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

        if (getenv('MYRA_WRITE_FIXTURES')) {
            file_put_contents($path, $actual);
        }

        $this->assertSame(
            file_exists($path) ? file_get_contents($path) : '',
            $actual,
            'Fixture drift. Re-run with MYRA_WRITE_FIXTURES=1 and review the diff.',
        );
    }
}
