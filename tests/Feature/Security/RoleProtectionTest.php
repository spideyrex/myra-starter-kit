<?php

namespace Tests\Feature\Security;

use App\Models\Role;
use App\Models\User;
use Tests\TestCase;

class RoleProtectionTest extends TestCase
{
    public function test_admin_cannot_assign_admin_role(): void
    {
        $this->actingAsAdmin();

        $response = $this->post(route('admin.users.store'), [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'password123',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $response->assertForbidden();
    }

    public function test_super_admin_can_assign_admin_role(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->post(route('admin.users.store'), [
            'name' => 'New Admin',
            'email' => 'newadmin@example.com',
            'password' => 'password123',
            'status' => 'active',
            'role' => 'admin',
        ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertTrue(User::where('email', 'newadmin@example.com')->first()->hasRole('admin'));
    }

    public function test_disabled_role_cannot_be_assigned(): void
    {
        $this->actingAsSuperAdmin();
        Role::create(['name' => 'temp', 'guard_name' => 'web', 'is_active' => false, 'visible' => true]);

        $response = $this->post(route('admin.users.store'), [
            'name' => 'Temp User',
            'email' => 'temp@example.com',
            'password' => 'password123',
            'status' => 'active',
            'role' => 'temp',
        ]);

        $response->assertStatus(422);
    }

    public function test_admin_cannot_manage_another_admin(): void
    {
        $other = User::factory()->create();
        $other->assignRole('admin');

        $this->actingAsAdmin();

        $this->get(route('admin.users.edit', $other))->assertForbidden();
    }

    public function test_super_admins_are_hidden_from_admin_user_list(): void
    {
        $super = User::factory()->create(['email' => 'hidden-super@example.com']);
        $super->assignRole('super-admin');

        $this->actingAsAdmin();

        $this->get(route('admin.users.index'))->assertOk()->assertDontSee('hidden-super@example.com');
    }
}
