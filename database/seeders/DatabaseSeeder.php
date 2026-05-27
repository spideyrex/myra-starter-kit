<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Roles, settings, and email templates are always safe to (re)seed.
        $this->call([
            RoleAndPermissionSeeder::class,
            SettingsSeeder::class,
            EmailTemplateSeeder::class,
        ]);

        // Demo users (factory password "password") must NEVER be created in
        // production. Use `php artisan app:install` to create a real admin.
        if (app()->isProduction() && ! env('ALLOW_DEMO_SEED', false)) {
            $this->command?->warn('Production environment detected — skipping demo user seeding. Run `php artisan app:install` to create an admin.');

            return;
        }

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
