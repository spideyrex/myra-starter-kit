<?php

namespace App\Admin\Testing;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

/** Builds the actors the probes need: exact abilities, nothing more. */
final class MyraActors
{
    public static function superAdmin(TestCase $test, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole(config('shield.super_admin_role', 'super-admin'));
        $test->actingAs($user);

        return $user;
    }

    /** @param string[] $abilities */
    public static function withPermissions(TestCase $test, array $abilities, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        if ($abilities !== []) {
            $role = Role::findOrCreate('myra-probe-' . Str::lower(Str::random(10)), 'web');

            foreach ($abilities as $ability) {
                Permission::findOrCreate($ability, 'web');
            }

            $role->syncPermissions($abilities);
            $user->assignRole($role);
        }

        $test->actingAs($user);

        return $user->fresh();
    }
}
