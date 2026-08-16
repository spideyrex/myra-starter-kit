<?php

namespace App\Plugins\Example;

use App\Admin\Plugin\Manifest;
use App\Admin\Plugin\MyraPlugin;
use App\Plugins\Example\Http\PingController;
use Illuminate\Support\Facades\Route;

/** The in-repo reference plugin. Twelve lines of declaration, nothing else. */
class ExamplePlugin extends MyraPlugin
{
    public static function id(): string
    {
        return 'myra-example';
    }

    public function manifest(Manifest $manifest): Manifest
    {
        return $manifest
            ->requires('2.4.0')
            ->permissions(['myra-example' => ['view']])
            ->routes(function () {
                Route::get('/myra-example/ping', PingController::class)
                    ->middleware('permission:myra-example.view')
                    ->name('myra-example.ping');
            })
            ->navItems([[
                'group' => 'navGroups.demo',
                'labelKey' => 'plugins.example.nav',
                'href' => '/admin/myra-example/ping',
                'icon' => 'Puzzle',
                'permission' => 'myra-example.view',
                'sort' => 0,
                'items' => [],
            ]]);
    }
}
