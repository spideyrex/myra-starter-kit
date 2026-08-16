<?php

namespace Tests\Feature\Dashboard;

use App\Admin\Dashboard\CatalogueWidget;
use App\Admin\Dashboard\WidgetCatalogue;
use App\Models\DashboardLayout;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DashboardLayoutControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['myra.dashboard.catalogue' => [], 'myra.dashboard.editable' => true]);
        WidgetCatalogue::flush();

        WidgetCatalogue::add(CatalogueWidget::chart('trend')
            ->titleKey('dashboardLayout.demo.trendTitle')
            ->report('users')
            ->dimensions(['created_at', 'status'])
            ->measures(['signups'])
            ->chartTypes(['area', 'bar'])
            ->defaults(['dimension' => 'created_at', 'measures' => ['signups'], 'limit' => 12]));
    }

    protected function tearDown(): void
    {
        WidgetCatalogue::flush();

        parent::tearDown();
    }

    private function editor(): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo(['dashboard.customise', 'reports.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function putLayout(array $payload): TestResponse
    {
        return $this->put(route('admin.dashboard-layouts.update', 'admin.dashboard'), [
            'dashboard_key' => 'admin.dashboard',
            'payload' => $payload,
        ]);
    }

    private function prop(TestResponse $response, string $key): mixed
    {
        $props = $response->viewData('page')['props'] ?? [];

        return json_decode(json_encode($props[$key] ?? null), true);
    }

    public function test_the_persisted_row_carries_the_acting_users_id(): void
    {
        $user = $this->editor();
        $this->actingAs($user);

        $this->putLayout(['version' => 1, 'entries' => [], 'instances' => []])->assertRedirect();

        $this->assertDatabaseHas('dashboard_layouts', [
            'user_id' => $user->id,
            'dashboard_key' => 'admin.dashboard',
        ]);

        $row = DashboardLayout::sole();

        $this->assertNotNull($row->user_id, 'The layout was created unowned.');
        $this->assertSame((int) $user->id, (int) $row->user_id);
        $this->assertTrue($row->isOwnedBy($user));

        // A second PUT updates that same owned row rather than minting another.
        $this->putLayout([
            'version' => 1,
            'entries' => [['key' => 'total_users', 'order' => 0]],
            'instances' => [],
        ])->assertRedirect();

        $this->assertDatabaseCount('dashboard_layouts', 1);
        $this->assertSame((int) $user->id, (int) DashboardLayout::sole()->user_id);

        // $fillable must carry the ownership column too: a create() that drops
        // user_id would leave the row unowned rather than fail loudly.
        $direct = DashboardLayout::create([
            'user_id' => $user->id,
            'dashboard_key' => 'admin.dashboard.probe',
            'payload' => ['version' => 1, 'entries' => [], 'instances' => []],
        ]);

        $this->assertSame((int) $user->id, (int) $direct->fresh()->user_id);
    }

    public function test_an_entry_for_an_undeclared_widget_persists_but_creates_nothing(): void
    {
        $user = $this->editor();
        $this->actingAs($user);

        $this->putLayout([
            'version' => 1,
            'entries' => [
                ['key' => 'total_users', 'order' => 0, 'colSpan' => 1, 'rowSpan' => 1, 'hidden' => false],
                ['key' => 'not-declared', 'order' => 1, 'colSpan' => 4, 'rowSpan' => 1, 'hidden' => false],
            ],
            'instances' => [],
        ])->assertRedirect();

        $row = DashboardLayout::where('user_id', $user->id)->firstOrFail();

        $this->assertCount(2, $row->payload['entries']);
        $this->assertSame([], $row->payload['instances']);
    }

    public function test_an_instance_naming_an_unknown_catalogue_key_is_rejected_at_write_time(): void
    {
        $user = $this->editor();
        $this->actingAs($user);

        $this->putLayout([
            'version' => 1,
            'entries' => [],
            'instances' => [
                ['key' => 'nope#aa11bb', 'catalogue' => 'nope', 'binding' => []],
                ['key' => 'trend#aa11bb', 'catalogue' => 'trend', 'binding' => ['dimension' => 'status', 'measures' => ['signups']]],
            ],
        ])->assertRedirect();

        $row = DashboardLayout::where('user_id', $user->id)->firstOrFail();

        $this->assertCount(1, $row->payload['instances']);
        $this->assertSame('trend', $row->payload['instances'][0]['catalogue']);
    }

    public function test_an_instance_disappears_when_the_actor_loses_the_report_ability(): void
    {
        $user = $this->editor();
        $this->actingAs($user);

        $this->putLayout([
            'version' => 1,
            'entries' => [],
            'instances' => [
                ['key' => 'trend#aa11bb', 'catalogue' => 'trend', 'binding' => ['dimension' => 'status', 'measures' => ['signups']]],
            ],
        ])->assertRedirect();

        $before = $this->prop($this->get(route('dashboard')), 'dashboardLayout');
        $this->assertCount(1, $before['instances']);

        $user->revokePermissionTo('reports.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $after = $this->prop($this->get(route('dashboard')), 'dashboardLayout');

        $this->assertSame([], $after['instances']);
        $this->assertDatabaseCount('dashboard_layouts', 1);
    }

    public function test_a_put_never_touches_another_users_row(): void
    {
        $victim = $this->editor();
        DashboardLayout::create([
            'user_id' => $victim->id,
            'dashboard_key' => 'admin.dashboard',
            'payload' => ['version' => 1, 'entries' => [['key' => 'victim', 'order' => 0]], 'instances' => []],
        ]);

        $attacker = $this->editor();
        $this->actingAs($attacker);

        $this->putLayout(['version' => 1, 'entries' => [['key' => 'attacker', 'order' => 0]], 'instances' => []])
            ->assertRedirect();

        $this->assertSame(
            'victim',
            DashboardLayout::where('user_id', $victim->id)->firstOrFail()->payload['entries'][0]['key'],
        );
        $this->assertDatabaseCount('dashboard_layouts', 2);
    }

    public function test_a_delete_never_touches_another_users_row(): void
    {
        $victim = $this->editor();
        DashboardLayout::create([
            'user_id' => $victim->id,
            'dashboard_key' => 'admin.dashboard',
            'payload' => ['version' => 1, 'entries' => [], 'instances' => []],
        ]);

        $this->actingAs($this->editor());
        $this->delete(route('admin.dashboard-layouts.destroy', 'admin.dashboard'))->assertRedirect();

        $this->assertDatabaseCount('dashboard_layouts', 1);
    }

    public function test_the_prop_is_null_when_no_row_exists(): void
    {
        $this->actingAs($this->editor());

        $this->assertNull($this->prop($this->get(route('dashboard')), 'dashboardLayout'));
    }

    public function test_an_unknown_dashboard_key_is_a_404(): void
    {
        $this->actingAs($this->editor());

        $this->put(route('admin.dashboard-layouts.update', 'admin.dashboard'), [
            'dashboard_key' => 'someone.elses.dashboard',
            'payload' => ['version' => 1, 'entries' => [], 'instances' => []],
        ])->assertNotFound();

        $this->delete(route('admin.dashboard-layouts.destroy', 'nope'))->assertNotFound();
    }

    public function test_the_routes_require_the_customise_ability(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo('reports.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user->fresh());

        $this->putLayout(['version' => 1, 'entries' => [], 'instances' => []])->assertForbidden();
        $this->get(route('admin.dashboard-catalogue.index'))->assertForbidden();
    }

    public function test_a_malformed_payload_is_a_422(): void
    {
        $this->actingAs($this->editor());

        $this->putJson(route('admin.dashboard-layouts.update', 'admin.dashboard'), [
            'dashboard_key' => 'admin.dashboard',
            'payload' => ['version' => 1, 'entries' => [['key' => 'a', 'order' => 0, 'colSpan' => 99]], 'instances' => []],
        ])->assertStatus(422);
    }

    public function test_the_catalogue_endpoint_hides_entries_the_actor_cannot_see(): void
    {
        WidgetCatalogue::add(CatalogueWidget::stat('secret')
            ->titleKey('dashboardLayout.demo.countTitle')
            ->permission('nobody.holds.this'));

        $this->actingAs($this->editor());

        $keys = array_column($this->getJson(route('admin.dashboard-catalogue.index'))->json('widgets'), 'key');

        $this->assertContains('trend', $keys);
        $this->assertNotContains('secret', $keys);
    }

    public function test_the_editable_flag_gates_only_the_editor_not_a_saved_layout(): void
    {
        $user = $this->editor();
        $this->actingAs($user);

        $this->putLayout([
            'version' => 1,
            'entries' => [['key' => 'total_users', 'order' => 3, 'colSpan' => 2, 'rowSpan' => 1, 'hidden' => false]],
            'instances' => [],
        ])->assertRedirect();

        config(['myra.dashboard.editable' => false]);

        $response = $this->get(route('dashboard'));

        $this->assertFalse($this->prop($response, 'canCustomiseDashboard'));
        $this->assertSame(
            'total_users',
            $this->prop($response, 'dashboardLayout')['entries'][0]['key'],
        );
    }
}
