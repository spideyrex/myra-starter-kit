<?php

namespace Tests\Feature\Plugin;

use App\Support\Myra;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * THE DRIFT GATE. Myra::ADMIN_MIDDLEWARE is a literal copy of the stack
 * declared in routes/web.php. If either moves, plugin routes silently lose (or
 * gain) a guard — so this test compares the two against the live route table.
 */
class AdminMiddlewareParityTest extends TestCase
{
    public function test_a_core_admin_route_uses_exactly_the_declared_stack(): void
    {
        $gathered = Route::getRoutes()->getByName('admin.users.index')->gatherMiddleware();

        $this->assertSame(
            Myra::ADMIN_MIDDLEWARE,
            array_values(array_diff($gathered, ['permission:users.view'])),
        );
    }

    public function test_a_plugin_route_uses_exactly_the_same_stack(): void
    {
        $gathered = Route::getRoutes()->getByName('admin.myra-example.ping')->gatherMiddleware();

        $this->assertSame(
            Myra::ADMIN_MIDDLEWARE,
            array_values(array_diff($gathered, ['permission:myra-example.view'])),
        );
    }

    public function test_a_public_plugin_route_helper_only_declares_web(): void
    {
        Myra::publicRoutes(function () {
            Route::get('/myra-parity-probe', fn () => 'ok')->name('myra-parity-probe');
        });

        // RouteCollection indexes names inside add(), i.e. before the fluent
        // ->name() lands. After boot nothing rebuilds that table, so refresh it.
        Route::getRoutes()->refreshNameLookups();

        $probe = Route::getRoutes()->getByName('myra-parity-probe');
        $this->assertNotNull($probe, 'The probe route was not registered.');

        $this->assertSame(['web'], array_values($probe->gatherMiddleware()));
    }
}
