<?php

namespace Tests\Feature\Modules;

use App\Models\Role;
use Tests\TestCase;

class RolesModuleTest extends TestCase
{
    public function test_index_requires_permission(): void
    {
        $this->actingAsUser();
        $this->get(route('admin.roles.index'))->assertForbidden();
    }

    public function test_super_admin_can_view_index(): void
    {
        $this->actingAsSuperAdmin();
        $this->get(route('admin.roles.index'))->assertOk();
    }

    public function test_can_create_role(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.roles.store'), [
            'name' => 'support',
            'permissions' => ['users.view'],
        ])->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseHas('roles', ['name' => 'support']);
    }

    public function test_can_clone_role(): void
    {
        $this->actingAsSuperAdmin();
        $role = Role::where('name', 'editor')->first();

        $this->post(route('admin.roles.clone', $role))->assertRedirect();

        $this->assertDatabaseHas('roles', ['name' => 'editor-copy']);
    }

    public function test_super_admin_can_toggle_role_active(): void
    {
        $this->actingAsSuperAdmin();
        $role = Role::where('name', 'editor')->first();

        $this->post(route('admin.roles.toggle-active', $role))->assertRedirect();

        $this->assertFalse($role->fresh()->is_active);
    }

    public function test_super_admin_role_cannot_be_disabled(): void
    {
        $this->actingAsSuperAdmin();
        $role = Role::where('name', 'super-admin')->first();

        $this->post(route('admin.roles.toggle-active', $role));

        $this->assertTrue($role->fresh()->is_active);
    }
}
