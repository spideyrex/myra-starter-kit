<?php

namespace App\Console\Commands\Myra;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Fills the scale demo table. Deterministic, chunked, never run automatically. */
class ScaleSeedCommand extends Command
{
    protected $signature = 'myra:scale-seed {count=100000 : Rows to insert} {--fresh : Truncate first}';

    protected $description = 'Seed myra_scale_rows for the 100k-row scale demo';

    private const CHUNK = 1000;

    private const STATUSES = ['active', 'pending', 'suspended', 'archived'];

    public function handle(): int
    {
        if (! Schema::hasTable('myra_scale_rows')) {
            $this->components->error('Table [myra_scale_rows] is missing. Run `php artisan migrate` first.');

            return self::FAILURE;
        }

        $count = max(0, (int) $this->argument('count'));

        if ($this->option('fresh')) {
            DB::table('myra_scale_rows')->truncate();
            $this->components->info('Truncated myra_scale_rows.');
        }

        if ($count === 0) {
            return self::SUCCESS;
        }

        fake()->seed(42);

        $base = Carbon::parse('2026-01-01 00:00:00');
        $now = now()->toDateTimeString();
        $bar = $this->output->createProgressBar($count);
        $bar->start();

        for ($offset = 0; $offset < $count; $offset += self::CHUNK) {
            $rows = [];
            $size = min(self::CHUNK, $count - $offset);

            for ($i = 0; $i < $size; $i++) {
                $n = $offset + $i + 1;
                $rows[] = [
                    'name' => fake()->name(),
                    'email' => 'scale' . $n . '@example.test',
                    'status' => self::STATUSES[$n % 4],
                    'amount' => ($n * 37) % 5000,
                    'created_at' => $base->copy()->addMinutes($n)->toDateTimeString(),
                    'updated_at' => $now,
                ];
            }

            DB::table('myra_scale_rows')->insert($rows);
            $bar->advance($size);
        }

        $bar->finish();
        $this->newLine(2);
        $this->components->info(number_format($count) . ' rows in myra_scale_rows.');

        return self::SUCCESS;
    }
}
