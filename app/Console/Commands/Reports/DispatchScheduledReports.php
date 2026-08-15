<?php

namespace App\Console\Commands\Reports;

use App\Jobs\SendScheduledReport;
use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Enqueues due schedules; it never renders inline. Each row is CLAIMED by
 * advancing next_run_at inside a transaction before the job is dispatched, so
 * two overlapping runs cannot double-send.
 */
class DispatchScheduledReports extends Command
{
    protected $signature = 'reports:dispatch {--limit=} {--schedule=} {--now}';

    protected $description = 'Dispatch report schedules that are due.';

    public function handle(): int
    {
        $limit = (int) ($this->option('limit') ?: config('myra.report_schedules.dispatch_limit', 200));
        $now = CarbonImmutable::now();

        $query = ReportSchedule::query();

        if ($id = $this->option('schedule')) {
            $query->whereKey((int) $id);
        } else {
            $query->due();
        }

        $ids = $query->orderBy('next_run_at')->limit(max(1, $limit))->pluck('id');
        $dispatched = 0;

        foreach ($ids as $id) {
            if ($this->claim((int) $id, $now)) {
                SendScheduledReport::dispatch((int) $id);
                $dispatched++;
            }
        }

        $this->info("Dispatched {$dispatched} report schedule(s).");

        return self::SUCCESS;
    }

    /** Advancing next_run_at under a row lock IS the claim. */
    private function claim(int $id, CarbonImmutable $now): bool
    {
        return (bool) DB::transaction(function () use ($id, $now) {
            $schedule = ReportSchedule::whereKey($id)->lockForUpdate()->first();

            if ($schedule === null || ! $schedule->is_active) {
                return false;
            }

            $forced = (bool) $this->option('now') || $this->option('schedule') !== null;

            if (! $forced && ($schedule->next_run_at === null || $schedule->next_run_at->isFuture())) {
                return false;
            }

            $schedule->advance($now);

            return true;
        });
    }
}
