<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            SettingsSeeder::class,
            EmailTemplateSeeder::class,
        ]);

        // Create super admin
        $admin = User::factory()->active()->create([
            'name' => 'Admin',
            'email' => 'admin@admin.com',
        ]);
        $admin->assignRole('super-admin');

        // Create sample users with different roles
        $roles = ['admin', 'manager', 'editor', 'viewer'];
        foreach ($roles as $role) {
            $user = User::factory()->active()->create([
                'name' => ucfirst($role) . ' User',
                'email' => $role . '-user@admin.com',
            ]);
            $user->assignRole($role);
        }

        // Create additional viewer users
        User::factory(5)->active()->create()->each(function ($user) {
            $user->assignRole('viewer');
        });
    }
}
