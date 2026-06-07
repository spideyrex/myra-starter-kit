<?php

namespace Tests\Feature\Modules;

use App\Models\User;
use Tests\TestCase;

class UsersModuleTest extends TestCase
{
    public function test_index_requires_permission(): void
    {
        $this->actingAsUser();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_super_admin_can_view_index(): void
    {
        $this->actingAsSuperAdmin();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_can_create_user(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.users.store'), [
            'name' => 'Created User',
            'email' => 'created@example.com',
            'password' => 'password123',
            'status' => 'active',
            'role' => 'editor',
        ])->assertRedirect(route('admin.users.index'));

        $user = User::where('email', 'created@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->hasRole('editor'));
    }

    public function test_can_update_user(): void
    {
        $this->actingAsSuperAdmin();
        $target = User::factory()->create(['name' => 'Old Name']);

        $this->put(route('admin.users.update', $target), [
            'name' => 'New Name',
            'email' => $target->email,
            'status' => 'active',
        ])->assertRedirect(route('admin.users.index'));

        $this->assertSame('New Name', $target->fresh()->name);
    }

    public function test_can_soft_delete_user(): void
    {
        $this->actingAsSuperAdmin();
        $target = User::factory()->create();

        $this->delete(route('admin.users.destroy', $target))->assertRedirect();

        $this->assertSoftDeleted('users', ['id' => $target->id]);
    }
}
