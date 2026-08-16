<?php

namespace Tests\Unit\Dashboard;

use App\Admin\Dashboard\StaticWidgetRegistry;
use App\Models\User;
use Tests\TestCase;

/**
 * DRIFT GUARD: every tile Dashboard.vue renders must be declared in
 * config('myra.dashboard.static_widgets'), or a role-authored entry naming it
 * would be silently dropped for everyone.
 */
class StaticWidgetRegistryTest extends TestCase
{
    /** @return array<int,string> */
    private function keysRenderedByTheDashboardPage(): array
    {
        $path = base_path('resources/js/Pages/Dashboard.vue');

        $this->assertFileExists($path);

        preg_match_all(
            "/Widget\.make\(\s*'([^']+)'/",
            (string) file_get_contents($path),
            $matches,
        );

        $keys = array_values(array_unique($matches[1] ?? []));

        $this->assertNotEmpty($keys, 'Parsed no widget keys out of Dashboard.vue.');

        return $keys;
    }

    public function test_every_rendered_widget_key_is_declared(): void
    {
        foreach ($this->keysRenderedByTheDashboardPage() as $key) {
            $this->assertTrue(
                StaticWidgetRegistry::has($key),
                "Dashboard.vue renders [{$key}] but config('myra.dashboard.static_widgets') does not declare it.",
            );
        }
    }

    public function test_every_declared_key_is_actually_rendered(): void
    {
        $rendered = $this->keysRenderedByTheDashboardPage();

        foreach (array_keys((array) config('myra.dashboard.static_widgets')) as $key) {
            $this->assertContains($key, $rendered, "Declared static widget [{$key}] is not rendered by Dashboard.vue.");
        }
    }

    public function test_a_null_ability_is_visible_to_everyone_including_a_guest(): void
    {
        config(['myra.dashboard.static_widgets' => ['total_users' => null]]);

        $this->assertSame(['total_users'], StaticWidgetRegistry::visibleTo(null));
        $this->assertSame(['total_users'], StaticWidgetRegistry::visibleTo(User::factory()->create()));
    }

    public function test_a_declared_ability_narrows_the_list(): void
    {
        config(['myra.dashboard.static_widgets' => [
            'total_users' => null,
            'pending_verifications' => 'reports.view',
        ]]);

        $viewer = $this->makeUser();
        $viewer->assignRole('viewer');

        $manager = $this->makeUser();
        $manager->assignRole('manager');

        $this->assertSame(['total_users'], StaticWidgetRegistry::visibleTo($viewer->fresh()));
        $this->assertSame(
            ['total_users', 'pending_verifications'],
            StaticWidgetRegistry::visibleTo($manager->fresh()),
        );
        $this->assertSame(['total_users'], StaticWidgetRegistry::visibleTo(null));
    }
}
