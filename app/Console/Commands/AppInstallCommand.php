<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AppInstallCommand extends Command
{
    protected $signature = 'app:install';

    protected $description = 'Run initial application setup: migrations, seeders, and admin account creation';

    public function handle(): int
    {
        $this->components->info('Starting application installation...');
        $this->newLine();

        // Check if already installed
        if ($this->isAlreadyInstalled()) {
            if (! $this->confirm('The application appears to be already installed. Continue anyway?')) {
                $this->components->warn('Installation cancelled.');

                return self::SUCCESS;
            }
        }

        // Validate environment
        $this->validateEnvironment();

        // Run migrations
        $this->components->task('Running migrations', function () {
            $this->callSilently('migrate', ['--force' => true]);
        });

        // Run seeders
        $this->components->task('Seeding roles and permissions', function () {
            $this->callSilently('db:seed', ['--class' => 'Database\\Seeders\\RoleAndPermissionSeeder', '--force' => true]);
        });

        $this->components->task('Seeding application settings', function () {
            $this->callSilently('db:seed', ['--class' => 'Database\\Seeders\\SettingsSeeder', '--force' => true]);
        });

        $this->components->task('Seeding email templates', function () {
            $this->callSilently('db:seed', ['--class' => 'Database\\Seeders\\EmailTemplateSeeder', '--force' => true]);
        });

        $this->newLine();

        // Create admin user
        $this->createAdminUser();

        // Summary
        $this->newLine();
        $this->components->info('Installation complete!');
        $this->components->bulletList([
            'Migrations: <fg=green>done</>',
            'Seeders: <fg=green>done</>',
            'Admin account: <fg=green>created</>',
            sprintf('URL: %s', config('app.url')),
        ]);

        return self::SUCCESS;
    }

    private function isAlreadyInstalled(): bool
    {
        try {
            return Schema::hasTable('settings') && DB::table('settings')->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function validateEnvironment(): void
    {
        $warnings = [];

        if (empty(config('app.key'))) {
            $warnings[] = 'APP_KEY is not set. Run: php artisan key:generate';
        }

        if (config('app.url') === 'http://localhost' && app()->isProduction()) {
            $warnings[] = 'APP_URL is set to http://localhost in production';
        }

        if (empty(config('database.default'))) {
            $warnings[] = 'DB_CONNECTION is not configured';
        }

        if (! empty($warnings)) {
            $this->components->warn('Environment warnings:');
            $this->components->bulletList($warnings);
            $this->newLine();
        }
    }

    private function createAdminUser(): void
    {
        $this->components->info('Create admin account');

        $name = $this->ask('Admin name', 'Admin');
        $email = $this->ask('Admin email');

        if (User::where('email', $email)->exists()) {
            $this->components->warn("User with email {$email} already exists. Assigning super-admin role.");
            $user = User::where('email', $email)->first();
            $user->assignRole('super-admin');

            return;
        }

        $password = $this->secret('Admin password');

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'status' => 'active',
        ]);

        $user->assignRole('super-admin');

        $this->components->info("Admin account created for {$email}");
    }
}
