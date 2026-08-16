<?php

namespace Tests\Feature\Plugin;

use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShieldGenerateTest extends TestCase
{
    public function test_shield_generate_creates_permissions_for_plugin_modules(): void
    {
        Permission::query()->where('name', 'myra-example.view')->delete();

        Artisan::call('shield:generate');

        $this->assertNotNull(Permission::query()->where('name', 'myra-example.view')->first());
    }

    public function test_the_admin_role_receives_the_plugin_permission(): void
    {
        Artisan::call('shield:generate');

        $this->assertTrue($this->actingAsAdmin()->can('myra-example.view'));
    }

    public function test_a_plugin_cannot_drop_a_core_module(): void
    {
        // array_replace_recursive is additive: the merge never removes a module.
        $this->assertArrayHasKey('users', config('shield.modules'));
        $this->assertArrayHasKey('reports', config('shield.modules'));
    }
}
