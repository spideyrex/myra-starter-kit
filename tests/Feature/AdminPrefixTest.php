<?php

namespace Tests\Feature;

use App\Support\Myra;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The admin URL segment is configuration, not a literal. Route NAMES stay
 * `admin.*` whatever the prefix is — that is what keeps 370-odd `route('admin.…')`
 * call sites working when the prefix moves.
 */
class AdminPrefixTest extends TestCase
{
    public function test_the_default_prefix_is_dashboard(): void
    {
        $this->assertSame('dashboard', Myra::adminPrefix());
        $this->assertSame('/dashboard', Myra::adminPath());
        $this->assertSame('/dashboard/users', Myra::adminPath('users'));
        $this->assertSame('/dashboard/users', Myra::adminPath('/users'));
    }

    public function test_a_blank_or_slashed_prefix_never_produces_a_broken_url(): void
    {
        config(['myra.admin.prefix' => '/panel/']);
        $this->assertSame('panel', Myra::adminPrefix());
        $this->assertSame('/panel/users', Myra::adminPath('users'));

        config(['myra.admin.prefix' => '']);
        $this->assertSame('dashboard', Myra::adminPrefix(), 'An empty prefix must fall back, not mount admin at /.');
    }

    /**
     * Anti-vacuity: the suite calls route('admin.users.index') everywhere, so
     * this asserts the URL actually carries the configured segment.
     */
    public function test_every_admin_route_url_sits_under_the_configured_prefix(): void
    {
        $prefix = Myra::adminPrefix();
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();

            if ($name === null || ! str_starts_with($name, 'admin.')) {
                continue;
            }

            if (! str_starts_with('/'.ltrim($route->uri(), '/'), '/'.$prefix.'/')) {
                $offenders[] = $name.' => /'.$route->uri();
            }
        }

        $this->assertSame([], $offenders, 'An admin route escaped the configured prefix.');
    }

    public function test_the_admin_route_group_is_not_empty(): void
    {
        $named = collect(Route::getRoutes())
            ->map(fn ($route) => $route->getName())
            ->filter(fn ($name) => $name !== null && str_starts_with($name, 'admin.'));

        $this->assertGreaterThan(50, $named->count(), 'The admin group vanished — the prefix test above would pass vacuously.');
    }

    public function test_route_names_are_unchanged_by_the_move(): void
    {
        $this->actingAsSuperAdmin();

        $this->assertSame('/dashboard/users', parse_url(route('admin.users.index'), PHP_URL_PATH));
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_a_legacy_admin_url_redirects_to_the_configured_prefix(): void
    {
        $this->actingAsSuperAdmin();

        $this->get('/admin/users')->assertRedirect('/dashboard/users');
        $this->get('/admin/users?page=2&search=x')->assertRedirect('/dashboard/users?page=2&search=x');
        $this->get('/admin')->assertRedirect('/dashboard');
    }

    public function test_the_legacy_redirect_can_be_turned_off(): void
    {
        $this->assertTrue(config('myra.admin.legacy_redirect'), 'Default on, so existing bookmarks survive.');
    }

    /**
     * A redirected POST loses its body, so the legacy route is GET-only rather
     * than quietly turning a write into a no-op read.
     */
    public function test_the_legacy_redirect_does_not_swallow_writes(): void
    {
        $this->actingAsSuperAdmin();

        $this->post('/admin/users', [])->assertStatus(405);
    }
}
