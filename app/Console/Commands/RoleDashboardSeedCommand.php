<?php

namespace App\Console\Commands;

use Database\Seeders\RoleDashboardSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** Explicit, idempotent starter role dashboards. Never runs from DatabaseSeeder. */
class RoleDashboardSeedCommand extends Command
{
    protected $signature = 'myra:role-dashboards:seed';

    protected $description = 'Seed starter dashboards for the built-in roles (existing rows are never overwritten)';

    public function handle(): int
    {
        if (! Schema::hasTable('role_dashboards')) {
            $this->components->error('Table [role_dashboards] is missing. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $seeder = new RoleDashboardSeeder;
        $seeder->setContainer($this->laravel)->setCommand($this);
        $seeder->run();

        $this->table(
            ['Role', 'Status', 'Widgets'],
            array_map(
                fn (array $row) => [$row['role'], $row['status'], $row['widgets']],
                $seeder->summary,
            ),
        );

        return self::SUCCESS;
    }
}
