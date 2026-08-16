<?php

namespace Tests\Feature\Dashboard;

use App\Admin\Dashboard\CatalogueWidget;
use App\Admin\Dashboard\RolePrincipal;
use App\Admin\Dashboard\WidgetCatalogue;
use App\Models\Role;
use App\Models\RoleDashboard;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Bundle B — the authoring surface.
 *
 * The write path resolves instances against the TARGET ROLE, never the author,
 * so the catalogue a super-admin is offered while authoring is the role's.
 */
class RoleDashboardControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(RoleDashboard::class)) {
            $this->markTestSkipped('bundle A not merged');
        }

        config(['myra.dashboard.catalogue' => [], 'myra.dashboard.editable' => true]);
        WidgetCatalogue::flush();

        WidgetCatalogue::add(CatalogueWidget::chart('trend')
            ->titleKey('dashboardLayout.demo.trendTitle')
            ->report('users')
            ->dimensions(['created_at', 'status'])
            ->measures(['signups'])
            ->chartTypes(['area', 'bar'])
            ->defaults(['dimension' => 'created_at', 'measures' => ['signups'], 'limit' => 12])
            ->defaultColSpan(['sm' => 2, 'lg' => 2]));
    }

    protected function tearDown(): void
    {
        WidgetCatalogue::flush();

        parent::tearDown();
    }

    private function namedRole(string $name): Role
    {
        return Role::query()->where('name', $name)->sole();
    }

    private function propOf(TestResponse $response, string $key): mixed
    {
        $props = $response->viewData('page')['props'] ?? [];

        return json_decode(json_encode($props[$key] ?? null), true);
    }

    private function pageProps(TestResponse $response): array
    {
        return $response->viewData('page')['props'] ?? [];
    }

    private function putRoleLayout(Role $role, array $payload, string $key = 'admin.dashboard'): TestResponse
    {
        return $this->put(route('admin.role-dashboards.update', $role->id), [
            'dashboard_key' => $key,
            'payload' => $payload,
        ]);
    }

    /** The exact catalogue entry the authoring page rendered — never a literal. */
    private function authoringEntry(Role $role): array
    {
        $response = $this->get(route('admin.role-dashboards.edit', $role->id));
        $response->assertOk();

        $catalogue = $this->propOf($response, 'dashboardCatalogue');

        $this->assertIsArray($catalogue);
        $this->assertCount(1, $catalogue);

        return $catalogue[0];
    }

    private function permittedUser(array $abilities): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo($abilities);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    public function test_every_route_requires_the_manage_roles_ability(): void
    {
        $this->actingAs($this->permittedUser(['dashboard.customise', 'reports.view']));

        $role = $this->namedRole('viewer');

        $this->get(route('admin.role-dashboards.index'))->assertForbidden();
        $this->get(route('admin.role-dashboards.edit', $role->id))->assertForbidden();
        $this->putRoleLayout($role, ['version' => 1, 'entries' => [], 'instances' => []])->assertForbidden();
        $this->delete(route('admin.role-dashboards.destroy', $role->id))->assertForbidden();
    }

    public function test_the_authoring_catalogue_is_the_target_roles_not_the_authors(): void
    {
        $this->actingAsSuperAdmin();

        $viewer = $this->namedRole('viewer');
        $manager = $this->namedRole('manager');

        // Anti-vacuity: the author really can see the widget, the viewer cannot.
        $this->assertCount(1, $this->propOf($this->get(route('dashboard')), 'dashboardCatalogue'));
        $this->assertFalse((new RolePrincipal($viewer))->can('reports.view'));

        // Gate::before answers true for a super-admin on every ability. The
        // principal deliberately never reaches the Gate, so the preview is honest.
        $this->assertSame(
            [],
            $this->propOf($this->get(route('admin.role-dashboards.edit', $viewer->id)), 'dashboardCatalogue'),
        );
        $this->assertCount(
            1,
            $this->propOf($this->get(route('admin.role-dashboards.edit', $manager->id)), 'dashboardCatalogue'),
        );

        $this->getJson(route('admin.dashboard-catalogue.index', ['role' => $viewer->id]))
            ->assertOk()
            ->assertExactJson(['widgets' => []]);

        $this->assertCount(
            1,
            $this->getJson(route('admin.dashboard-catalogue.index', ['role' => $manager->id]))->json('widgets'),
        );
    }

    public function test_the_catalogue_role_parameter_needs_the_manage_ability_and_is_otherwise_unchanged(): void
    {
        $this->actingAs($this->permittedUser(['dashboard.customise', 'reports.view']));

        // No `role`: byte-identical to today.
        $this->assertCount(
            1,
            $this->getJson(route('admin.dashboard-catalogue.index'))->assertOk()->json('widgets'),
        );

        $this->getJson(route('admin.dashboard-catalogue.index', ['role' => $this->namedRole('viewer')->id]))
            ->assertForbidden();
    }

    public function test_a_locked_role_can_only_be_authored_by_a_super_admin(): void
    {
        $locked = $this->namedRole(config('shield.super_admin_role', 'super-admin'));

        $this->actingAsAdmin();

        $this->get(route('admin.role-dashboards.index'))->assertOk();
        $this->get(route('admin.role-dashboards.edit', $locked->id))->assertForbidden();
        $this->putRoleLayout($locked, ['version' => 1, 'entries' => [], 'instances' => []])->assertForbidden();
        $this->delete(route('admin.role-dashboards.destroy', $locked->id))->assertForbidden();

        $this->actingAsSuperAdmin();

        $this->get(route('admin.role-dashboards.edit', $locked->id))->assertOk();
    }

    public function test_the_real_payload_the_editor_emits_persists_and_renders_back(): void
    {
        $this->actingAsSuperAdmin();

        $manager = $this->namedRole('manager');
        $entry = $this->authoringEntry($manager);
        $instanceKey = $entry['key'].'#rolefixture';

        // Built from the catalogue prop the authoring page ACTUALLY rendered.
        $payload = [
            'version' => 1,
            'entries' => [
                ['key' => 'total_users', 'order' => 0, 'colSpan' => 1, 'rowSpan' => 1, 'hidden' => false],
                ['key' => $instanceKey, 'order' => 1, 'colSpan' => $entry['defaultColSpan'], 'rowSpan' => 1, 'hidden' => false],
                ['key' => 'pending_verifications', 'order' => 2, 'colSpan' => 1, 'rowSpan' => 1, 'hidden' => true],
            ],
            'instances' => [[
                'key' => $instanceKey,
                'catalogue' => $entry['key'],
                'binding' => $entry['defaults'],
                'title' => 'Manager trend',
                'chartType' => $entry['chartTypes'][0],
                'colSpan' => $entry['defaultColSpan'],
                'order' => 1,
            ]],
        ];

        $this->putRoleLayout($manager, $payload)->assertRedirect();

        $row = RoleDashboard::sole();

        $this->assertSame((int) $manager->id, (int) $row->role_id);
        $this->assertSame('admin.dashboard', $row->dashboard_key);
        $this->assertCount(1, $row->payload['instances']);
        $this->assertCount(3, $row->payload['entries']);

        $layout = $this->propOf($this->get(route('admin.role-dashboards.edit', $manager->id)), 'dashboardLayout');

        $this->assertSame(1, $layout['version']);
        $this->assertCount(1, $layout['instances']);
        $this->assertSame($instanceKey, $layout['instances'][0]['key']);
        $this->assertSame('users', $layout['instances'][0]['binding']['report']);

        // Idempotent: the same body twice is one row and the same prop.
        $this->putRoleLayout($manager, $payload)->assertRedirect();

        $this->assertDatabaseCount('role_dashboards', 1);
        $this->assertSame(
            $layout,
            $this->propOf($this->get(route('admin.role-dashboards.edit', $manager->id)), 'dashboardLayout'),
        );
    }

    public function test_an_instance_the_target_role_cannot_see_is_refused_at_write_time(): void
    {
        $this->actingAsSuperAdmin();

        $manager = $this->namedRole('manager');
        $viewer = $this->namedRole('viewer');

        // A REAL catalogue entry, taken from a role that may see it…
        $entry = $this->authoringEntry($manager);
        $instanceKey = $entry['key'].'#leak';

        $this->assertFalse((new RolePrincipal($viewer))->can('reports.view'));

        // …then aimed at a role that may not.
        $this->putRoleLayout($viewer, [
            'version' => 1,
            'entries' => [
                ['key' => $instanceKey, 'order' => 0],
                ['key' => 'total_users', 'order' => 1],
            ],
            'instances' => [[
                'key' => $instanceKey,
                'catalogue' => $entry['key'],
                'binding' => $entry['defaults'],
                'chartType' => $entry['chartTypes'][0],
                'order' => 0,
            ]],
        ])->assertRedirect();

        $stored = RoleDashboard::sole()->payload;

        $this->assertSame([], $stored['instances'], 'A widget the role cannot see was authored into its dashboard.');
        $this->assertSame(['total_users'], array_column($stored['entries'], 'key'));
    }

    public function test_an_unknown_dashboard_key_is_a_404(): void
    {
        $this->actingAsSuperAdmin();

        $this->putRoleLayout($this->namedRole('manager'), [
            'version' => 1, 'entries' => [], 'instances' => [],
        ], 'admin.nope')->assertNotFound();

        $this->assertDatabaseCount('role_dashboards', 0);
    }

    public function test_a_malformed_payload_is_a_422_and_never_a_truncated_row(): void
    {
        $this->actingAsSuperAdmin();

        $this->putRoleLayout($this->namedRole('manager'), [
            'version' => 2, 'entries' => [], 'instances' => [],
        ])->assertSessionHasErrors('payload');

        $this->assertDatabaseCount('role_dashboards', 0);
    }

    public function test_delete_clears_only_that_roles_row(): void
    {
        $this->actingAsSuperAdmin();

        $manager = $this->namedRole('manager');
        $editor = $this->namedRole('editor');
        $body = ['version' => 1, 'entries' => [['key' => 'total_users', 'order' => 0]], 'instances' => []];

        $this->putRoleLayout($manager, $body)->assertRedirect();
        $this->putRoleLayout($editor, $body)->assertRedirect();
        $this->assertDatabaseCount('role_dashboards', 2);

        $this->delete(route('admin.role-dashboards.destroy', $manager->id))->assertRedirect();

        $this->assertDatabaseCount('role_dashboards', 1);
        $this->assertSame((int) $editor->id, (int) RoleDashboard::sole()->role_id);
    }

    public function test_the_index_lists_every_role_with_its_configuration_state(): void
    {
        $this->actingAsSuperAdmin();

        $manager = $this->namedRole('manager');

        $this->putRoleLayout($manager, [
            'version' => 1,
            'entries' => [
                ['key' => 'total_users', 'order' => 0],
                ['key' => 'pending_verifications', 'order' => 1, 'hidden' => true],
            ],
            'instances' => [],
        ])->assertRedirect();

        $roles = $this->propOf($this->get(route('admin.role-dashboards.index')), 'roles');

        $this->assertSame(
            ['id', 'name', 'priority', 'is_active', 'is_locked', 'configured', 'widget_count', 'updated_at'],
            array_keys($roles[0]),
            'The row contract the Vue page reads changed.',
        );

        // Priority order, highest first, ties broken by id.
        $priorities = array_column($roles, 'priority');
        $sorted = $priorities;
        rsort($sorted);
        $this->assertSame($sorted, $priorities);

        $row = collect($roles)->firstWhere('name', 'manager');

        $this->assertTrue($row['configured']);
        $this->assertSame(1, $row['widget_count'], 'A hidden entry is not a widget the role sees.');
        $this->assertNotNull($row['updated_at']);

        $this->assertFalse(collect($roles)->firstWhere('name', 'editor')['configured']);
    }

    public function test_with_nothing_configured_the_dashboard_is_exactly_what_it_is_today(): void
    {
        $user = $this->permittedUser(['dashboard.customise', 'reports.view']);
        $user->assignRole('manager');
        $this->actingAs($user->fresh());

        $this->assertDatabaseCount('role_dashboards', 0);

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        $props = $this->pageProps($response);

        $this->assertArrayNotHasKey('dashboardAuthoringRole', $props);
        $this->assertNull($this->propOf($response, 'dashboardLayout'));
        $this->assertCount(1, $this->propOf($response, 'dashboardCatalogue'));
        $this->assertTrue($props['canCustomiseDashboard']);
    }
}
