<?php

namespace Tests\Feature\Report;

use App\Admin\Report\Schedule\RecipientResolver;
use App\Jobs\SendQueuedTemplateMail;
use App\Jobs\SendScheduledReport;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Services\EmailService;
use Carbon\CarbonImmutable;
use Database\Seeders\ReportScheduleSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportScheduleDispatchTest extends TestCase
{
    /** @param array<string,mixed> $overrides */
    private function schedule(User $owner, array $overrides = []): ReportSchedule
    {
        $schedule = new ReportSchedule(array_merge([
            'report_key' => 'users',
            'name' => 'Weekly signups',
            'state' => [
                'period' => ['preset' => 'last_30_days'],
                'dimension' => 'status',
                'measures' => ['signups'],
            ],
            'format' => 'csv',
            'frequency' => 'weekly',
            'day_of_week' => 1,
            'hour' => 8,
            'minute' => 0,
            'timezone' => 'UTC',
            'recipients' => [['type' => 'user', 'id' => $owner->id]],
            'skip_if_empty' => false,
            'is_active' => true,
        ], $overrides));

        $schedule->user_id = $owner->id;
        $schedule->slug = (string) Str::ulid();
        $schedule->next_run_at = now()->subMinute();
        $schedule->save();

        return $schedule;
    }

    public function test_due_selects_only_active_rows_whose_time_has_come(): void
    {
        $owner = $this->actingAsRole('admin');

        $due = $this->schedule($owner);
        $future = $this->schedule($owner);
        $future->forceFill(['next_run_at' => now()->addDay()])->save();
        $paused = $this->schedule($owner);
        $paused->forceFill(['is_active' => false])->save();

        $ids = ReportSchedule::due()->pluck('id')->all();

        $this->assertSame([$due->id], $ids);
    }

    public function test_the_dispatcher_claims_a_row_before_dispatching_so_a_second_run_is_a_no_op(): void
    {
        Queue::fake();
        $owner = $this->actingAsRole('admin');
        $schedule = $this->schedule($owner);

        $this->artisan('reports:dispatch')->assertSuccessful();
        $this->artisan('reports:dispatch')->assertSuccessful();

        Queue::assertPushed(SendScheduledReport::class, 1);

        $this->assertTrue($schedule->fresh()->next_run_at->isFuture());
    }

    public function test_the_job_runs_as_the_owner_so_another_users_rows_are_excluded(): void
    {
        $this->seed(ReportScheduleSeeder::class);
        Queue::fake([SendQueuedTemplateMail::class]);

        $owner = $this->actingAsRole('admin');
        $stranger = $this->makeUser();

        User::factory()->count(3)->create(['created_by' => $owner->id, 'status' => 'active']);
        User::factory()->count(5)->create(['created_by' => $stranger->id, 'status' => 'active']);

        $schedule = $this->schedule($owner);

        (new SendScheduledReport($schedule->id))->handle(app(EmailService::class));

        Queue::assertPushed(SendQueuedTemplateMail::class, function (SendQueuedTemplateMail $job) {
            $csv = $job->attachments[0]['data'] ?? '';
            $total = 0;

            foreach (array_slice(preg_split('/\R/', trim($csv)) ?: [], 1) as $line) {
                $cells = str_getcsv($line);
                $total += (int) ($cells[1] ?? 0);
            }

            // Three rows belong to the owner; the stranger's five must not appear.
            return $total === 3;
        });
    }

    public function test_three_failures_pause_the_schedule(): void
    {
        $owner = $this->actingAsRole('admin');
        $schedule = $this->schedule($owner);

        foreach (range(1, 3) as $ignored) {
            (new SendScheduledReport($schedule->id))->failed(new \RuntimeException('smtp down'));
        }

        $schedule->refresh();

        $this->assertSame(3, (int) $schedule->failure_count);
        $this->assertFalse((bool) $schedule->is_active);
    }

    public function test_an_external_address_needs_the_external_permission(): void
    {
        $owner = $this->actingAsRole('admin');

        $schedule = $this->schedule($owner, [
            'recipients' => [['type' => 'email', 'address' => 'outside@example.test']],
        ]);

        $this->assertSame([], RecipientResolver::resolve($schedule, $owner->fresh()));

        $owner->givePermissionTo('reports.schedule.external');
        $owner->forgetCachedPermissions();

        $resolved = RecipientResolver::resolve($schedule, $owner->fresh());

        $this->assertSame(['outside@example.test'], array_column($resolved, 'email'));
    }

    public function test_a_pdf_schedule_is_refused_for_a_non_latin_locale(): void
    {
        $owner = $this->actingAsRole('admin');
        $owner->givePermissionTo('reports.schedule');
        config(['app.locale' => 'zh']);

        $this->post(route('admin.report-schedules.store'), [
            'report_key' => 'users',
            'name' => '报表',
            'state' => ['period' => ['preset' => 'last_30_days']],
            'format' => 'pdf',
            'frequency' => 'daily',
            'hour' => 8,
            'minute' => 0,
            'timezone' => 'UTC',
            'recipients' => [['type' => 'user', 'id' => $owner->id]],
            'skip_if_empty' => false,
            'is_active' => true,
        ])->assertSessionHasErrors('format');
    }

    public function test_the_rendered_attachment_is_deleted_after_the_mail_is_queued(): void
    {
        $this->seed(ReportScheduleSeeder::class);
        Storage::fake('local');
        Queue::fake([SendQueuedTemplateMail::class]);

        $owner = $this->actingAsRole('admin');
        User::factory()->count(2)->create(['created_by' => $owner->id, 'status' => 'active']);

        $schedule = $this->schedule($owner, ['format' => 'pdf']);

        (new SendScheduledReport($schedule->id))->handle(app(EmailService::class));

        $this->assertSame([], Storage::disk('local')->allFiles('reports'));
    }

    public function test_advance_moves_next_run_at_strictly_forward(): void
    {
        $owner = $this->actingAsRole('admin');
        $schedule = $this->schedule($owner);

        $schedule->advance(CarbonImmutable::now());

        $this->assertTrue($schedule->fresh()->next_run_at->isFuture());
    }
}
