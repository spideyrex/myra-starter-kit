<?php

namespace Tests\Feature\Demo;

use App\Admin\Demo\DemoRegistry;
use Tests\TestCase;

class NewComponentDemoTest extends TestCase
{
    private const ROUTES = [
        'admin.demo.empty-and-item',
        'admin.demo.chart-primitives',
        'admin.demo.otp-and-combobox',
        'admin.demo.conversation',
        'admin.demo.questionnaire',
        'admin.demo.map-markers',
        'admin.demo.landing-templates',
    ];

    private const KEYS = [
        'emptyAndItem',
        'chartPrimitives',
        'otpAndCombobox',
        'conversation',
        'questionnaire',
        'mapMarkers',
        'landingTemplates',
    ];

    public function test_every_new_demo_route_renders_for_a_demo_viewer(): void
    {
        $this->actingAsSuperAdmin();

        foreach (self::ROUTES as $name) {
            $this->get(route($name))->assertOk();
        }
    }

    public function test_every_new_demo_route_is_gated_by_the_demo_permission(): void
    {
        $this->actingAsUser();

        foreach (self::ROUTES as $name) {
            $this->get(route($name))->assertForbidden();
        }
    }

    public function test_a_guest_is_redirected_rather_than_served(): void
    {
        foreach (self::ROUTES as $name) {
            $this->get(route($name))->assertRedirect();
        }
    }

    public function test_every_new_key_is_declared_in_the_registry(): void
    {
        DemoRegistry::seed();

        foreach (self::KEYS as $key) {
            $this->assertTrue(DemoRegistry::has($key), "Demo entry [{$key}] is missing from the registry.");
        }
    }

    /**
     * A random data generator in a demo controller makes the props differ on
     * every request, which breaks the committed JS fixtures and any screenshot
     * diff. The guard reads the raw source, so the ban covers comments too.
     */
    public function test_the_new_demo_controller_never_calls_fake(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/ComponentDemoController.php'));

        $this->assertStringNotContainsString('fake(', $source);
        $this->assertStringNotContainsString('faker', $source);
    }

    public function test_the_chart_demo_ships_a_screen_reader_data_table(): void
    {
        $page = file_get_contents(base_path('resources/js/Pages/Admin/Demo/ChartPrimitives.vue'));

        $this->assertStringContainsString('class="sr-only"', $page);
        $this->assertStringContainsString('<caption>', $page);
    }

    public function test_the_conversation_demo_uses_a_log_live_region(): void
    {
        $page = file_get_contents(base_path('resources/js/Pages/Admin/Demo/Conversation.vue'));

        $this->assertStringContainsString('role="log"', $page);
        $this->assertStringContainsString('aria-live="polite"', $page);
    }

    public function test_the_new_demo_payloads_are_static_arrays_not_database_reads(): void
    {
        $this->actingAsSuperAdmin();

        $first = $this->get(route('admin.demo.empty-and-item'))->viewData('page')['props']['projects'];
        $second = $this->get(route('admin.demo.empty-and-item'))->viewData('page')['props']['projects'];

        $this->assertSame($first, $second, 'A demo payload that changes between requests is not a fixture.');
        $this->assertNotEmpty($first);
    }

    public function test_the_landing_templates_demo_ships_the_real_registry_payload(): void
    {
        $this->actingAsSuperAdmin();

        $templates = $this->get(route('admin.demo.landing-templates'))
            ->assertOk()
            ->viewData('page')['props']['templates'];

        $this->assertNotEmpty($templates);
        $this->assertSame('classic', $templates[0]['key']);
        $this->assertArrayHasKey('supports', $templates[0]);
    }
}
