<?php

namespace Tests\Feature\Plugin;

use App\Admin\Plugin\PluginRegistry;
use App\Support\Myra;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * A plugin nav item must resolve to an Inertia response.
 *
 * The reference plugin shipped a nav item pointing at a plain-JSON route, so
 * clicking it raised "All Inertia requests must receive a valid Inertia
 * response, however a plain JSON response was received" and blocked the whole
 * admin behind a modal. Routing is fine; the nav target is the contract.
 */
class PluginNavRendersInertiaTest extends TestCase
{
    public function test_every_plugin_nav_item_targets_a_route_that_renders_inertia(): void
    {
        $user = $this->actingAsSuperAdmin();

        $items = collect(PluginRegistry::manifests())
            ->flatMap(fn ($manifest) => $manifest->navItemArrays())
            ->filter(fn (array $item) => ! empty($item['href']))
            ->values();

        if ($items->isEmpty()) {
            $this->markTestSkipped('No plugin contributes a nav item.');
        }

        foreach ($items as $item) {
            $href = (string) $item['href'];

            $response = $this->get($href);

            // A redirect (auth/permission) is fine; what must never happen is a
            // 2xx whose body is JSON rather than an Inertia page.
            if ($response->isRedirect()) {
                continue;
            }

            $response->assertOk();

            $contentType = (string) $response->headers->get('content-type');

            $this->assertStringNotContainsString(
                'application/json',
                $contentType,
                "Plugin nav item [{$href}] returns JSON. Inertia rejects a plain-JSON response "
                .'and the admin is blocked by an error modal. Point the nav item at a route that '
                .'renders an Inertia page and keep JSON routes out of the navigation.',
            );
        }
    }

    public function test_the_reference_plugin_nav_item_renders_a_page(): void
    {
        $this->actingAsSuperAdmin();

        if (! Route::has('admin.myra-example.index')) {
            $this->markTestSkipped('Reference plugin is not loaded.');
        }

        $this->get(route('admin.myra-example.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Plugins/Example/Index'));
    }

    public function test_the_json_api_route_is_not_reachable_from_navigation(): void
    {
        $hrefs = collect(PluginRegistry::manifests())
            ->flatMap(fn ($manifest) => $manifest->navItemArrays())
            ->pluck('href')->filter()->all();

        $this->assertNotContains(Myra::adminPath('myra-example/ping'), $hrefs,
            'The plain-JSON ping route must never be a nav target.');
    }
}
