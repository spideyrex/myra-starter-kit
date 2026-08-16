<?php

namespace Tests\Unit\Dashboard;

use App\Admin\Dashboard\RolePrincipal;
use App\Models\Role;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Auth\Authenticatable;
use Tests\TestCase;

/**
 * The authoring principal must answer from the ROLE's permission set alone.
 * Gate::before returns true for a super-admin on every ability; a preview built
 * through the author's Gate would ship tiles that evaporate at render.
 */
class RolePrincipalTest extends TestCase
{
    private function roleNamed(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }

    public function test_it_is_a_drop_in_for_the_existing_filtering_stack(): void
    {
        $principal = new RolePrincipal($this->roleNamed('viewer'));

        $this->assertInstanceOf(Authenticatable::class, $principal);
        $this->assertInstanceOf(Authorizable::class, $principal);
        $this->assertNull($principal->getAuthIdentifier());
    }

    public function test_it_answers_from_the_roles_own_permissions(): void
    {
        $this->assertTrue((new RolePrincipal($this->roleNamed('manager')))->can('reports.view'));
        $this->assertFalse((new RolePrincipal($this->roleNamed('viewer')))->can('reports.view'));
    }

    public function test_it_ignores_the_gate_even_for_a_super_admin_author(): void
    {
        $superAdmin = $this->actingAsSuperAdmin();

        // The author sees everything…
        $this->assertTrue($superAdmin->can('reports.view'));

        // …the principal for the role they are authoring does not.
        $this->assertFalse((new RolePrincipal($this->roleNamed('viewer')))->can('reports.view'));
    }

    public function test_an_unknown_ability_fails_closed(): void
    {
        $principal = new RolePrincipal($this->roleNamed('manager'));

        $this->assertFalse($principal->can('nope.not-a-real-ability'));
        $this->assertFalse($principal->can(''));
        $this->assertFalse($principal->can([]));
        $this->assertTrue($principal->cannot('nope.not-a-real-ability'));
        $this->assertTrue($principal->cant('nope.not-a-real-ability'));
    }

    public function test_can_any_is_a_disjunction_and_can_is_a_conjunction(): void
    {
        $principal = new RolePrincipal($this->roleNamed('manager'));

        $this->assertTrue($principal->canAny(['nope.not-a-real-ability', 'reports.view']));
        $this->assertFalse($principal->canAny(['nope.not-a-real-ability']));
        $this->assertFalse($principal->can(['reports.view', 'nope.not-a-real-ability']));
        $this->assertTrue($principal->can(['reports.view', 'reports.export']));
    }
}
