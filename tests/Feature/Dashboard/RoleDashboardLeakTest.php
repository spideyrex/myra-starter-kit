<?php

namespace Tests\Feature\Dashboard;

use App\Admin\Dashboard\CatalogueWidget;
use App\Admin\Dashboard\DashboardKey;
use App\Admin\Dashboard\RolePrincipal;
use App\Admin\Dashboard\WidgetCatalogue;
use App\Admin\Dashboard\WidgetInstance;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * THE security test. A role dashboard is authored by one person and rendered
 * for another, so the stored payload is UNTRUSTED INPUT at render time.
 */
class RoleDashboardLeakTest extends TestCase
{
    private const SECRET_CATALOGUE = 'revenue-secret';

    private const SECRET_INSTANCE = 'revenue-secret#1';

    private const SECRET_MEASURE = 'grossmargin';

    private const SECRET_TITLE = 'Gross margin by region';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'myra.dashboard.catalogue' => [],
            'myra.dashboard.editable' => true,
            // 'pending_verifications' is deliberately gated here so the entry
            // filter has something real to bite on.
            'myra.dashboard.static_widgets' => [
                'total_users' => null,
                'active_users' => null,
                'new_users' => null,
                'pending_verifications' => 'reports.view',
                'user_growth' => null,
                'users_by_status' => null,
            ],
        ]);

        WidgetCatalogue::flush();
        WidgetCatalogue::add(CatalogueWidget::chart(self::SECRET_CATALOGUE)
            ->titleKey('dashboardLayout.demo.trendTitle')
            ->permission('reports.view')
            ->report('users')
            ->dimensions(['created_at'])
            ->measures([self::SECRET_MEASURE])
            ->chartTypes(['bar'])
            ->defaults(['dimension' => 'created_at', 'measures' => [self::SECRET_MEASURE], 'limit' => 12]));
    }

    protected function tearDown(): void
    {
        WidgetCatalogue::flush();

        parent::tearDown();
    }

    private function secretInstance(): array
    {
        return [
            'key' => self::SECRET_INSTANCE,
            'catalogue' => self::SECRET_CATALOGUE,
            'binding' => ['dimension' => 'created_at', 'measures' => [self::SECRET_MEASURE]],
            'title' => self::SECRET_TITLE,
            'chartType' => 'bar',
        ];
    }

    private function viewerRole(): Role
    {
        return Role::where('name', 'viewer')->firstOrFail();
    }

    /** A fresh user holding ONLY the viewer role. */
    private function plainViewer(): User
    {
        $user = $this->makeUser();
        $user->assignRole('viewer');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /** A plain admin — NOT a super-admin, whose Gate::before would pass for the wrong reason. */
    private function plainAdmin(): User
    {
        $user = $this->makeUser();
        $user->assignRole('admin');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /** Force-insert, bypassing every write-path filter: a tampered or pre-existing row. */
    private function forceRoleDashboard(Role $role, array $payload): void
    {
        DB::table('role_dashboards')->insert([
            'role_id' => $role->id,
            'dashboard_key' => DashboardKey::MAIN,
            'payload' => json_encode($payload),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function prop(TestResponse $response, string $key): mixed
    {
        $props = $response->viewData('page')['props'] ?? [];

        $this->assertArrayHasKey($key, $props, "Inertia prop [{$key}] is missing.");

        return json_decode(json_encode($props[$key]), true);
    }

    public function test_case_a_the_write_path_refuses_an_instance_the_target_role_cannot_see(): void
    {
        $viewer = $this->plainViewer();

        // ANTI-VACUITY: if a future seeding change grants viewers reports.view,
        // this fails loudly instead of passing empty.
        $this->assertFalse($viewer->can('reports.view'));

        $author = $this->plainAdmin();
        $this->actingAs($author);

        // The instance IS otherwise resolvable — the refusal is about the role.
        $this->assertNotNull(WidgetInstance::resolve($this->secretInstance(), $author));

        $this->assertNull(WidgetInstance::resolve($this->secretInstance(), new RolePrincipal($this->viewerRole())));
    }

    public function test_case_b_a_tampered_role_row_leaks_nothing_to_the_viewer(): void
    {
        $viewer = $this->plainViewer();
        $this->assertFalse($viewer->can('reports.view'));

        $this->forceRoleDashboard($this->viewerRole(), [
            'version' => 1,
            'entries' => [
                ['key' => 'total_users', 'order' => 0],
                ['key' => self::SECRET_INSTANCE, 'order' => 1],
            ],
            'instances' => [$this->secretInstance()],
        ]);

        $this->actingAs($viewer);
        $response = $this->get(route('dashboard'));
        $response->assertOk();

        $layout = $this->prop($response, 'dashboardLayout');

        $this->assertSame([], $layout['instances']);
        $this->assertSame(['total_users'], array_column($layout['entries'], 'key'));

        // Not the title, not the binding, not the report key, not the catalogue key.
        $response->assertDontSee(self::SECRET_TITLE, false);
        $response->assertDontSee(self::SECRET_MEASURE, false);
        $response->assertDontSee(self::SECRET_CATALOGUE, false);
        $response->assertDontSee(self::SECRET_INSTANCE, false);
    }

    public function test_case_c_a_static_key_the_viewer_may_not_see_never_reaches_the_client(): void
    {
        $viewer = $this->plainViewer();
        $this->assertFalse($viewer->can('reports.view'));

        $this->forceRoleDashboard($this->viewerRole(), [
            'version' => 1,
            'entries' => [
                ['key' => 'pending_verifications', 'order' => 0],
                ['key' => 'total_users', 'order' => 1],
            ],
            'instances' => [],
        ]);

        $this->actingAs($viewer);
        $layout = $this->prop($this->get(route('dashboard')), 'dashboardLayout');

        $this->assertSame(['total_users'], array_column($layout['entries'], 'key'));
        $this->assertSame(0, $layout['entries'][0]['order']);
    }

    public function test_case_c_the_same_key_survives_for_someone_who_may_see_it(): void
    {
        $admin = $this->plainAdmin();
        $adminRole = Role::where('name', 'admin')->firstOrFail();

        $this->assertTrue($admin->can('reports.view'));

        $this->forceRoleDashboard($adminRole, [
            'version' => 1,
            'entries' => [
                ['key' => 'pending_verifications', 'order' => 0],
                ['key' => 'total_users', 'order' => 1],
            ],
            'instances' => [],
        ]);

        $this->actingAs($admin);
        $layout = $this->prop($this->get(route('dashboard')), 'dashboardLayout');

        $this->assertSame(['pending_verifications', 'total_users'], array_column($layout['entries'], 'key'));
    }

    public function test_case_d_a_malformed_foreign_payload_is_sanitised_not_echoed(): void
    {
        $viewer = $this->plainViewer();

        $this->forceRoleDashboard($this->viewerRole(), [
            'version' => 1,
            'entries' => [
                'not-an-array',
                ['key' => '<script>alert(1)</script>', 'order' => 0],
                ['key' => 'total_users', 'order' => 'nope'],
                ['key' => 'total_users', 'order' => 0, 'nonsense' => true],
                ['key' => 'active_users', 'order' => 7],
            ],
            'instances' => ['garbage', ['catalogue' => 'nope']],
        ]);

        $this->actingAs($viewer);
        $response = $this->get(route('dashboard'));
        $response->assertOk();

        $layout = $this->prop($response, 'dashboardLayout');

        $this->assertSame(['active_users'], array_column($layout['entries'], 'key'));
        $this->assertSame(0, $layout['entries'][0]['order']);
        $this->assertSame([], $layout['instances']);
        $response->assertDontSee('alert(1)', false);
    }
}
