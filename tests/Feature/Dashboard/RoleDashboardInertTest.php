<?php

namespace Tests\Feature\Dashboard;

use App\Admin\Dashboard\WidgetCatalogue;
use App\Models\RoleDashboard;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * INERT BY DEFAULT. With role_dashboards empty and no personal row, a user WITH
 * roles must see exactly what they saw in v2.6 — no flag involved.
 */
class RoleDashboardInertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['myra.dashboard.catalogue' => []]);
        WidgetCatalogue::flush();
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

    public function test_a_roled_user_with_nothing_configured_gets_todays_render(): void
    {
        $this->actingAsRole('manager');

        $this->assertSame(0, RoleDashboard::query()->count());

        $response = $this->get(route('dashboard'));
        $response->assertOk();

        $this->assertNull($this->prop($response, 'dashboardLayout'));
        $this->assertSame(
            ['source' => 'none', 'role' => null, 'hasRoleDefault' => false],
            $this->prop($response, 'dashboardLayoutSource'),
        );
    }

    public function test_the_six_business_props_are_untouched(): void
    {
        $this->actingAsRole('manager');

        $props = $this->get(route('dashboard'))->viewData('page')['props'] ?? [];

        foreach (['stats', 'usersByRole', 'usersByStatus', 'recentActivity', 'recentUsers', 'userGrowth'] as $key) {
            $this->assertArrayHasKey($key, $props);
        }

        $this->assertArrayHasKey('dashboardCatalogue', $props);
        $this->assertArrayHasKey('canCustomiseDashboard', $props);
    }

    public function test_the_source_prop_is_always_an_object_never_null(): void
    {
        $this->actingAsUser();

        $source = $this->prop($this->get(route('dashboard')), 'dashboardLayoutSource');

        $this->assertIsArray($source);
        $this->assertSame('none', $source['source']);
        $this->assertNull($source['role']);
        $this->assertFalse($source['hasRoleDefault']);
    }
}
