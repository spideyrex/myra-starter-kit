<?php

namespace Tests\Feature\Dashboard;

use App\Admin\Dashboard\DashboardKey;
use App\Admin\Dashboard\WidgetCatalogue;
use App\Models\Role;
use App\Models\RoleDashboard;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/** The precedence ladder: personal → highest-priority role default → nothing. */
class RoleDashboardResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['myra.dashboard.catalogue' => [], 'myra.dashboard.editable' => true]);
        WidgetCatalogue::flush();
    }

    protected function tearDown(): void
    {
        WidgetCatalogue::flush();

        parent::tearDown();
    }

    private function entriesOnly(string $key): array
    {
        return ['version' => 1, 'entries' => [['key' => $key, 'order' => 0]], 'instances' => []];
    }

    private function seedRoleDashboard(string $role, string $entryKey, ?int $priority = null): Role
    {
        $model = Role::where('name', $role)->firstOrFail();

        if ($priority !== null) {
            $model->priority = $priority;
            $model->save();
        }

        RoleDashboard::updateOrCreate(
            ['role_id' => $model->id, 'dashboard_key' => DashboardKey::MAIN],
            ['payload' => $this->entriesOnly($entryKey)],
        );

        return $model->refresh();
    }

    private function prop(TestResponse $response, string $key): mixed
    {
        $props = $response->viewData('page')['props'] ?? [];

        $this->assertArrayHasKey($key, $props, "Inertia prop [{$key}] is missing.");

        return json_decode(json_encode($props[$key]), true);
    }

    private function renderDashboard(): TestResponse
    {
        return $this->get(route('dashboard'));
    }

    private function customiser(string $role): User
    {
        $user = $this->makeUser();
        $user->assignRole($role);
        $user->givePermissionTo('dashboard.customise');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function savePersonal(string $entryKey): TestResponse
    {
        return $this->put(route('admin.dashboard-layouts.update', DashboardKey::MAIN), [
            'dashboard_key' => DashboardKey::MAIN,
            'payload' => $this->entriesOnly($entryKey),
        ]);
    }

    public function test_a_role_default_applies_to_a_member_of_that_role(): void
    {
        $this->seedRoleDashboard('manager', 'user_growth');
        $this->actingAsRole('manager');

        $response = $this->renderDashboard();

        $this->assertSame('user_growth', $this->prop($response, 'dashboardLayout')['entries'][0]['key']);
        $this->assertSame(
            ['source' => 'role', 'role' => 'manager', 'hasRoleDefault' => true],
            $this->prop($response, 'dashboardLayoutSource'),
        );
    }

    public function test_a_personal_layout_beats_the_role_default_and_still_reports_it(): void
    {
        $this->seedRoleDashboard('manager', 'user_growth');
        $this->actingAs($this->customiser('manager'));

        $this->savePersonal('new_users')->assertRedirect();

        $response = $this->renderDashboard();

        $this->assertSame('new_users', $this->prop($response, 'dashboardLayout')['entries'][0]['key']);
        $this->assertSame(
            ['source' => 'personal', 'role' => 'manager', 'hasRoleDefault' => true],
            $this->prop($response, 'dashboardLayoutSource'),
        );
    }

    public function test_deleting_the_personal_row_falls_back_to_the_role_default(): void
    {
        $this->seedRoleDashboard('manager', 'user_growth');
        $this->actingAs($this->customiser('manager'));

        $this->savePersonal('new_users')->assertRedirect();

        // NO new endpoint: the existing personal-layout reset is the whole mechanism.
        $this->delete(route('admin.dashboard-layouts.destroy', DashboardKey::MAIN))->assertRedirect();

        $response = $this->renderDashboard();

        $this->assertSame('user_growth', $this->prop($response, 'dashboardLayout')['entries'][0]['key']);
        $this->assertSame('role', $this->prop($response, 'dashboardLayoutSource')['source']);
    }

    public function test_an_empty_personal_layout_still_beats_the_role_default(): void
    {
        $this->seedRoleDashboard('manager', 'user_growth');
        $this->actingAs($this->customiser('manager'));

        $this->put(route('admin.dashboard-layouts.update', DashboardKey::MAIN), [
            'dashboard_key' => DashboardKey::MAIN,
            'payload' => ['version' => 1, 'entries' => [], 'instances' => []],
        ])->assertRedirect();

        $response = $this->renderDashboard();

        $this->assertSame([], $this->prop($response, 'dashboardLayout')['entries']);
        $this->assertSame('personal', $this->prop($response, 'dashboardLayoutSource')['source']);
    }

    public function test_a_user_with_no_roles_resolves_to_nothing(): void
    {
        $this->seedRoleDashboard('manager', 'user_growth');
        $this->actingAsUser();

        $response = $this->renderDashboard();

        $this->assertNull($this->prop($response, 'dashboardLayout'));
        $this->assertSame(
            ['source' => 'none', 'role' => null, 'hasRoleDefault' => false],
            $this->prop($response, 'dashboardLayoutSource'),
        );
    }

    public function test_the_highest_priority_role_wins_for_a_multi_role_user(): void
    {
        $this->seedRoleDashboard('manager', 'user_growth', 30);
        $this->seedRoleDashboard('editor', 'new_users', 20);

        $user = $this->makeUser();
        $user->assignRole('manager');
        $user->assignRole('editor');
        $this->actingAs($user->fresh());

        $this->assertSame('manager', $this->prop($this->renderDashboard(), 'dashboardLayoutSource')['role']);
    }

    public function test_swapping_the_priorities_swaps_the_winner(): void
    {
        $this->seedRoleDashboard('manager', 'user_growth', 20);
        $this->seedRoleDashboard('editor', 'new_users', 30);

        $user = $this->makeUser();
        $user->assignRole('manager');
        $user->assignRole('editor');
        $this->actingAs($user->fresh());

        $response = $this->renderDashboard();

        $this->assertSame('editor', $this->prop($response, 'dashboardLayoutSource')['role']);
        $this->assertSame('new_users', $this->prop($response, 'dashboardLayout')['entries'][0]['key']);
    }

    public function test_a_top_priority_role_without_a_dashboard_never_blanks_the_user(): void
    {
        Role::where('name', 'manager')->update(['priority' => 90]);
        $this->seedRoleDashboard('editor', 'new_users', 20);

        $user = $this->makeUser();
        $user->assignRole('manager');
        $user->assignRole('editor');
        $this->actingAs($user->fresh());

        $response = $this->renderDashboard();

        $this->assertSame('editor', $this->prop($response, 'dashboardLayoutSource')['role']);
        $this->assertSame('new_users', $this->prop($response, 'dashboardLayout')['entries'][0]['key']);
    }

    public function test_equal_priorities_are_broken_by_role_id_ascending(): void
    {
        $manager = $this->seedRoleDashboard('manager', 'user_growth', 25);
        $editor = $this->seedRoleDashboard('editor', 'new_users', 25);

        $lowestId = $manager->id < $editor->id ? $manager : $editor;

        $user = $this->makeUser();
        $user->assignRole('manager');
        $user->assignRole('editor');
        $this->actingAs($user->fresh());

        $this->assertSame($lowestId->name, $this->prop($this->renderDashboard(), 'dashboardLayoutSource')['role']);
    }

    public function test_an_inactive_role_is_skipped_entirely(): void
    {
        $manager = $this->seedRoleDashboard('manager', 'user_growth', 30);
        $this->seedRoleDashboard('editor', 'new_users', 20);

        $manager->is_active = false;
        $manager->save();

        $user = $this->makeUser();
        $user->assignRole('manager');
        $user->assignRole('editor');
        $this->actingAs($user->fresh());

        $this->assertSame('editor', $this->prop($this->renderDashboard(), 'dashboardLayoutSource')['role']);
    }

    public function test_deactivating_the_only_role_with_a_dashboard_degrades_to_nothing(): void
    {
        $manager = $this->seedRoleDashboard('manager', 'user_growth', 30);
        $this->actingAsRole('manager');

        $this->assertSame('role', $this->prop($this->renderDashboard(), 'dashboardLayoutSource')['source']);

        $manager->is_active = false;
        $manager->save();

        $response = $this->renderDashboard();

        $this->assertNull($this->prop($response, 'dashboardLayout'));
        $this->assertSame('none', $this->prop($response, 'dashboardLayoutSource')['source']);
    }
}
