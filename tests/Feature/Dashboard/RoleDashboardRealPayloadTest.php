<?php

namespace Tests\Feature\Dashboard;

use App\Admin\Dashboard\CatalogueWidget;
use App\Admin\Dashboard\DashboardKey;
use App\Admin\Dashboard\WidgetCatalogue;
use App\Models\DashboardLayout;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * REAL PAYLOAD. The bytes stored as a role dashboard are the EXACT bytes
 * DashboardLayoutController::update persisted, from a PUT body derived from the
 * catalogue prop the server actually rendered. Nothing here is hand-authored.
 */
class RoleDashboardRealPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'myra.dashboard.catalogue' => [],
            'myra.dashboard.editable' => true,
            'myra.reports.max_groups' => 200,
        ]);

        WidgetCatalogue::flush();
        WidgetCatalogue::add(CatalogueWidget::chart('trend')
            ->titleKey('dashboardLayout.demo.trendTitle')
            ->descriptionKey('dashboardLayout.demo.trendDescription')
            ->categoryKey('dashboardLayout.categories.charts')
            ->icon('ChartArea')
            ->report('users')
            ->dimensions(['created_at', 'status'])
            ->measures(['signups', 'emails'])
            ->chartTypes(['area', 'bar', 'doughnut'])
            ->defaults(['dimension' => 'created_at', 'measures' => ['signups'], 'limit' => 12])
            ->defaultColSpan(['sm' => 2, 'lg' => 2])
            ->maxInstances(2)
            ->tags(['chart', 'trend']));
    }

    protected function tearDown(): void
    {
        WidgetCatalogue::flush();

        parent::tearDown();
    }

    private function prop(TestResponse $response, string $key): mixed
    {
        $props = $response->viewData('page')['props'] ?? [];

        $this->assertArrayHasKey($key, $props, "Inertia prop [{$key}] is missing.");

        return json_decode(json_encode($props[$key]), true);
    }

    private function memberOf(string $role, array $extraPermissions = []): User
    {
        $user = $this->makeUser();
        $user->assignRole($role);

        if ($extraPermissions !== []) {
            $user->givePermissionTo($extraPermissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    /** The exact JSON the personal write path persisted, replayed onto a role. */
    private function persistedBytesForRole(): string
    {
        $author = $this->memberOf('manager', ['dashboard.customise']);
        $this->actingAs($author);

        $catalogue = $this->prop($this->get(route('dashboard')), 'dashboardCatalogue');
        $this->assertCount(1, $catalogue);

        $entry = $catalogue[0];
        $instanceKey = $entry['key'].'#role';

        $this->put(route('admin.dashboard-layouts.update', DashboardKey::MAIN), [
            'dashboard_key' => DashboardKey::MAIN,
            'payload' => [
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
                    'title' => 'Role payload widget',
                    'chartType' => $entry['chartTypes'][0],
                    'colSpan' => $entry['defaultColSpan'],
                    'order' => 1,
                ]],
            ],
        ])->assertRedirect();

        $bytes = DashboardLayout::query()->firstOrFail()->getRawOriginal('payload');

        // The author's own row must not be what the role member later sees.
        DashboardLayout::query()->delete();

        return (string) $bytes;
    }

    private function forceRoleDashboard(string $role, string $bytes): void
    {
        DB::table('role_dashboards')->insert([
            'role_id' => Role::where('name', $role)->firstOrFail()->id,
            'dashboard_key' => DashboardKey::MAIN,
            'payload' => $bytes,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_real_persisted_bytes_render_as_a_role_default_for_a_member(): void
    {
        $bytes = $this->persistedBytesForRole();
        $this->forceRoleDashboard('manager', $bytes);

        $this->actingAs($this->memberOf('manager'));
        $response = $this->get(route('dashboard'));

        $layout = $this->prop($response, 'dashboardLayout');

        $this->assertSame(
            ['source' => 'role', 'role' => 'manager', 'hasRoleDefault' => true],
            $this->prop($response, 'dashboardLayoutSource'),
        );
        $this->assertSame(1, $layout['version']);
        $this->assertCount(1, $layout['instances']);
        $this->assertSame('trend#role', $layout['instances'][0]['key']);
        $this->assertSame('users', $layout['instances'][0]['binding']['report']);
        $this->assertSame(['total_users', 'trend#role', 'pending_verifications'], array_column($layout['entries'], 'key'));
        $this->assertSame([0, 1, 2], array_column($layout['entries'], 'order'));
    }

    public function test_the_same_real_bytes_leak_nothing_to_a_role_that_may_not_see_them(): void
    {
        $bytes = $this->persistedBytesForRole();
        $this->forceRoleDashboard('viewer', $bytes);

        $viewer = $this->memberOf('viewer');
        $this->assertFalse($viewer->can('reports.view'));

        $this->actingAs($viewer);
        $response = $this->get(route('dashboard'));

        $layout = $this->prop($response, 'dashboardLayout');

        $this->assertSame('role', $this->prop($response, 'dashboardLayoutSource')['source']);
        $this->assertSame([], $layout['instances']);
        $this->assertSame(['total_users', 'pending_verifications'], array_column($layout['entries'], 'key'));
        $response->assertDontSee('Role payload widget', false);
        $response->assertDontSee('trend#role', false);
    }
}
