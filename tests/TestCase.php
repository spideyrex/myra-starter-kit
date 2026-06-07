<?php

namespace Tests;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles/permissions (Shield) and core settings on the in-memory DB
        // so feature tests have a realistic RBAC + settings baseline.
        $this->seed([
            RoleAndPermissionSeeder::class,
            SettingsSeeder::class,
        ]);
        $this->artisan('shield:generate');
    }

    /** Create an active, verified user (no role) and return it. */
    protected function makeUser(array $attributes = []): User
    {
        return User::factory()->create($attributes);
    }

    /** Act as a brand-new user with the given role; returns the user. */
    protected function actingAsRole(string $role, array $attributes = []): User
    {
        $user = $this->makeUser($attributes);
        $user->assignRole($role);
        $this->actingAs($user);

        return $user;
    }

    protected function actingAsSuperAdmin(array $attributes = []): User
    {
        return $this->actingAsRole('super-admin', $attributes);
    }

    protected function actingAsAdmin(array $attributes = []): User
    {
        return $this->actingAsRole('admin', $attributes);
    }

    /** Act as an authenticated user with no roles; returns the user. */
    protected function actingAsUser(array $attributes = []): User
    {
        $user = $this->makeUser($attributes);
        $this->actingAs($user);

        return $user;
    }
}
