<?php

namespace Tests\Feature\Dashboard;

use App\Admin\Dashboard\CatalogueWidget;
use App\Admin\Dashboard\DashboardKey;
use App\Admin\Dashboard\WidgetCatalogue;
use App\Models\DashboardLayout;
use App\Models\Role;
use App\Models\RoleDashboard;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** Entitlement is re-derived every request — there is no cache to go stale. */
class RoleDashboardLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['myra.dashboard.catalogue' => [], 'myra.dashboard.editable' => true]);
        WidgetCatalogue::flush();
        WidgetCatalogue::add(CatalogueWidget::chart('trend')
            ->titleKey('dashboardLayout.demo.trendTitle')
            ->report('users')
            ->dimensions(['created_at'])
            ->measures(['signups'])
            ->chartTypes(['bar'])
            ->defaults(['dimension' => 'created_at', 'measures' => ['signups'], 'limit' => 12]));
    }

    protected function tearDown(): void
    {
        WidgetCatalogue::flush();

        parent::tearDown();
    }

    private function roleNamed(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }

    private function seedRoleDashboard(Role $role, string $entryKey): RoleDashboard
    {
        return RoleDashboard::create([
            'role_id' => $role->id,
            'dashboard_key' => DashboardKey::MAIN,
            'payload' => ['version' => 1, 'entries' => [['key' => $entryKey, 'order' => 0]], 'instances' => []],
        ]);
    }

    private function prop(TestResponse $response, string $key): mixed
    {
        $props = $response->viewData('page')['props'] ?? [];

        $this->assertArrayHasKey($key, $props, "Inertia prop [{$key}] is missing.");

        return json_decode(json_encode($props[$key]), true);
    }

    private function sourceFor(User $user): array
    {
        $this->actingAs($user->fresh());

        return $this->prop($this->get(route('dashboard')), 'dashboardLayoutSource');
    }

    public function test_deleting_a_role_cascades_its_dashboard_away(): void
    {
        $manager = $this->roleNamed('manager');
        $this->seedRoleDashboard($manager, 'user_growth');

        $user = $this->makeUser();
        $user->assignRole('manager');

        $this->assertSame('role', $this->sourceFor($user)['source']);

        $manager->delete();

        $this->assertSame(0, RoleDashboard::query()->count());
        $this->assertSame('none', $this->sourceFor($user)['source']);
    }

    public function test_unassigning_the_role_falls_through_on_the_next_request(): void
    {
        $manager = $this->roleNamed('manager');
        $this->seedRoleDashboard($manager, 'user_growth');

        $user = $this->makeUser();
        $user->assignRole('manager');

        $this->assertSame('role', $this->sourceFor($user)['source']);

        $user->removeRole('manager');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertSame('none', $this->sourceFor($user)['source']);
    }

    public function test_deactivating_the_role_falls_through_on_the_next_request(): void
    {
        $manager = $this->roleNamed('manager');
        $this->seedRoleDashboard($manager, 'user_growth');

        $user = $this->makeUser();
        $user->assignRole('manager');

        $this->assertSame('role', $this->sourceFor($user)['source']);

        $manager->is_active = false;
        $manager->save();

        $this->assertSame('none', $this->sourceFor($user)['source']);
    }

    /**
     * A personal layout carries NO role provenance — there is nothing to revoke.
     * The arrangement survives; anything inside it the user may no longer see is
     * simply re-filtered out of `instances`.
     */
    public function test_losing_a_role_keeps_the_personal_layout_but_drops_forbidden_widgets(): void
    {
        $user = $this->makeUser();
        $user->assignRole('manager');
        $user->givePermissionTo('dashboard.customise');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user = $user->fresh();

        $this->actingAs($user);
        $this->put(route('admin.dashboard-layouts.update', DashboardKey::MAIN), [
            'dashboard_key' => DashboardKey::MAIN,
            'payload' => [
                'version' => 1,
                'entries' => [
                    ['key' => 'total_users', 'order' => 0],
                    ['key' => 'trend#mine', 'order' => 1],
                ],
                'instances' => [[
                    'key' => 'trend#mine',
                    'catalogue' => 'trend',
                    'binding' => ['dimension' => 'created_at', 'measures' => ['signups']],
                    'chartType' => 'bar',
                ]],
            ],
        ])->assertRedirect();

        $before = $this->prop($this->get(route('dashboard')), 'dashboardLayout');
        $this->assertCount(1, $before['instances']);
        $this->assertSame(['total_users', 'trend#mine'], array_column($before['entries'], 'key'));

        // The manager role is where reports.view came from.
        $user->removeRole('manager');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($user->fresh());
        $response = $this->get(route('dashboard'));

        $this->assertSame('personal', $this->prop($response, 'dashboardLayoutSource')['source']);

        $after = $this->prop($response, 'dashboardLayout');
        $this->assertSame([], $after['instances']);
        $this->assertSame(['total_users'], array_column($after['entries'], 'key'));
        $this->assertSame(1, DashboardLayout::query()->count());
    }
}
