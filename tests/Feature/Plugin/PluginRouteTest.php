<?php

namespace Tests\Feature\Plugin;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * REAL PAYLOAD: the plugin route is hit through the real HTTP kernel, so this
 * proves the route landed inside the real admin middleware stack rather than
 * merely being declared.
 */
class PluginRouteTest extends TestCase
{
    public function test_the_plugin_route_exists_under_the_admin_prefix(): void
    {
        $route = Route::getRoutes()->getByName('admin.myra-example.ping');

        $this->assertNotNull($route, 'The example plugin did not register its route.');
        $this->assertSame(ltrim(\App\Support\Myra::adminPath('myra-example/ping'), '/'), $route->uri());
    }

    public function test_a_permitted_user_gets_the_real_json_body(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo('myra-example.view');
        $this->actingAs($user);

        $response = $this->getJson(route('admin.myra-example.ping'));

        $response->assertOk();
        $this->assertSame(['pong' => true], $response->json());
    }

    public function test_the_route_is_forbidden_without_the_plugin_permission(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.myra-example.ping'))->assertForbidden();
    }

    public function test_the_route_redirects_a_guest_to_login(): void
    {
        $this->get(route('admin.myra-example.ping'))->assertRedirect(route('login'));
    }

    public function test_a_super_admin_bypasses_the_plugin_permission(): void
    {
        $this->actingAsSuperAdmin();

        $this->getJson(route('admin.myra-example.ping'))->assertOk();
    }
}
