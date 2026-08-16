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

    // >>> MYRA v2.7 [C] START

    public function test_deleting_a_role_takes_its_assignments_with_it(): void
    {
        $this->actingAsSuperAdmin();

        $role = Role::where('name', 'editor')->first();
        $member = $this->makeUser();
        $member->assignRole($role);

        $this->assertDatabaseHas('model_has_roles', ['role_id' => $role->id, 'model_id' => $member->id]);

        $this->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('model_has_roles', ['role_id' => $role->id]);
        $this->assertFalse($member->fresh()->hasRole('editor'));
    }

    public function test_system_roles_cannot_be_deleted(): void
    {
        $this->actingAsSuperAdmin();
        $role = Role::where('name', 'admin')->first();

        $this->delete(route('admin.roles.destroy', $role));

        $this->assertDatabaseHas('roles', ['id' => $role->id]);
    }

    public function test_reorder_writes_priority_in_tens_highest_first(): void
    {
        $this->actingAsSuperAdmin();

        $ids = Role::orderBy('id')->pluck('id')->all();
        $count = count($ids);

        $this->post(route('admin.roles.reorder'), ['ids' => $ids])->assertRedirect();

        foreach ($ids as $index => $id) {
            $this->assertSame(($count - $index) * 10, (int) Role::findOrFail($id)->priority);
        }
    }

    public function test_reorder_puts_the_first_id_at_the_top_of_the_resolution_order(): void
    {
        $this->actingAsSuperAdmin();

        $manager = Role::where('name', 'manager')->firstOrFail();
        $editor = Role::where('name', 'editor')->firstOrFail();

        $this->post(route('admin.roles.reorder'), ['ids' => [$editor->id, $manager->id]])->assertRedirect();

        $this->assertGreaterThan((int) $manager->fresh()->priority, (int) $editor->fresh()->priority);
    }

    public function test_reorder_requires_the_roles_edit_permission(): void
    {
        $this->actingAsRole('manager');

        $this->post(route('admin.roles.reorder'), ['ids' => [1]])->assertForbidden();
    }

    public function test_reorder_is_refused_to_a_non_super_admin(): void
    {
        $this->actingAsAdmin();

        $this->post(route('admin.roles.reorder'), ['ids' => [1]])->assertForbidden();
    }

    public function test_reorder_writes_nothing_when_an_id_is_unknown(): void
    {
        $this->actingAsSuperAdmin();

        $before = Role::orderBy('id')->pluck('priority', 'id')->all();

        $this->from(route('admin.roles.index'))
            ->post(route('admin.roles.reorder'), ['ids' => [Role::max('id') + 999]])
            ->assertSessionHasErrors('ids.0');

        $this->assertSame($before, Role::orderBy('id')->pluck('priority', 'id')->all());
    }

    public function test_index_exposes_priority_and_dashboard_state(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('admin.roles.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('roles.0.priority')
                ->has('roles.0.has_dashboard')
                ->has('canManageRoleDashboards'));
    }

    // <<< MYRA v2.7 [C] END
}
