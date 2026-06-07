<?php

namespace Tests\Feature\Security;

use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ShieldTest extends TestCase
{
    public function test_shield_generate_creates_module_permissions(): void
    {
        // setUp() already runs shield:generate.
        foreach (['users.view', 'users.create', 'roles.view', 'articles.delete', 'demo.view', 'search.view'] as $perm) {
            $this->assertNotNull(Permission::where('name', $perm)->first(), "missing {$perm}");
        }
    }

    public function test_admin_role_receives_all_permissions(): void
    {
        $admin = $this->actingAsAdmin();
        $this->assertTrue($admin->can('users.view'));
        $this->assertTrue($admin->can('articles.delete'));
    }

    public function test_route_is_forbidden_without_permission(): void
    {
        $this->actingAsUser();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_route_is_allowed_with_permission(): void
    {
        $this->actingAsAdmin();
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_super_admin_bypasses_all_permission_checks(): void
    {
        $this->actingAsSuperAdmin();
        $this->get(route('admin.users.index'))->assertOk();
        $this->get(route('admin.system-health.index'))->assertOk();
    }
}
