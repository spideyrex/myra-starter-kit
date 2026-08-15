<?php

namespace Tests\Feature\Report;

use App\Admin\Report\Schedule\RecipientResolver;
use App\Jobs\SendQueuedTemplateMail;
use App\Jobs\SendScheduledReport;
use App\Mail\ScheduledReportMail;
use App\Models\EmailTemplate;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Services\EmailService;
use App\Settings\EmailSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\ReportScheduleSeeder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
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
            // Attachment bytes are base64 in the payload: a queue payload is JSON
            // and raw PDF/xlsx bytes are not valid UTF-8.
            $csv = base64_decode((string) ($job->attachments[0]['data'] ?? ''), true);
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

    public function test_every_route_the_schedules_page_calls_is_registered(): void
    {
        foreach (['index', 'store', 'update', 'destroy', 'test'] as $action) {
            $this->assertTrue(
                Route::has("admin.report-schedules.{$action}"),
                "Route admin.report-schedules.{$action} is not registered.",
            );
        }
    }

    public function test_the_scheduled_report_template_ships_with_the_database_seeder(): void
    {
        $this->assertNull(EmailTemplate::where('slug', 'scheduled-report')->first());

        $this->seed(DatabaseSeeder::class);

        $this->assertNotNull(EmailTemplate::where('slug', 'scheduled-report')->first());
    }

    /** ShouldQueue would make Mailer::sendMailable() re-queue under the undefined "ondemand" mailer. */
    public function test_the_scheduled_report_mailable_is_not_itself_queueable(): void
    {
        $this->assertNotInstanceOf(ShouldQueue::class, new ScheduledReportMail('s', '<p>b</p>'));
    }

    /**
     * The whole delivery chain, end to end: the worker decodes the attachment,
     * the on-demand mailer carries a From (Symfony rejects a message without one)
     * and the mailable is transmitted rather than re-queued.
     */
    public function test_the_worker_actually_transmits_the_message(): void
    {
        Event::fake([MessageSent::class]);

        app(EmailSettings::class)->mail_mailer = 'array';

        (new SendQueuedTemplateMail(
            'someone@example.test',
            'Weekly signups',
            '<p>Body</p>',
            [['data' => base64_encode('a,b'), 'name' => 'report.csv', 'mime' => 'text/csv']],
        ))->handle(app(EmailService::class));

        Event::assertDispatched(MessageSent::class, function (MessageSent $event) {
            $message = $event->message;

            return $message->getFrom() !== []
                && $message->getTo()[0]->getAddress() === 'someone@example.test'
                && str_contains($message->toString(), base64_encode('a,b'));
        });
    }

    public function test_the_settings_built_mailer_carries_a_from_address(): void
    {
        $from = (new \ReflectionProperty(\Illuminate\Mail\Mailer::class, 'from'))
            ->getValue(app(EmailService::class)->mailer());

        $this->assertNotEmpty($from['address'] ?? null);
    }

    public function test_next_run_at_is_persisted_in_the_app_timezone(): void
    {
        config(['app.timezone' => 'UTC']);

        $owner = $this->actingAsRole('admin');
        $schedule = $this->schedule($owner, [
            'frequency' => 'daily',
            'timezone' => 'Asia/Kuala_Lumpur',
            'hour' => 8,
            'minute' => 0,
        ]);

        $schedule->advance(CarbonImmutable::now());

        // 08:00 in UTC+8 is 00:00 UTC. Storing the local wall clock verbatim would
        // fire the schedule eight hours early.
        $this->assertStringContainsString(
            '00:00:00',
            (string) DB::table('report_schedules')->where('id', $schedule->id)->value('next_run_at'),
        );
    }

    public function test_a_test_send_reaches_only_the_actor_and_leaves_the_schedule_alone(): void
    {
        $this->seed(ReportScheduleSeeder::class);
        Queue::fake([SendQueuedTemplateMail::class]);

        $owner = $this->actingAsRole('admin');
        $teammate = $this->makeUser(['created_by' => $owner->id, 'status' => 'active']);
        $actor = $this->makeUser(['created_by' => $owner->id, 'status' => 'active']);
        $actor->assignRole('admin');

        $schedule = $this->schedule($owner, [
            'recipients' => [
                ['type' => 'user', 'id' => $owner->id],
                ['type' => 'user', 'id' => $teammate->id],
            ],
        ]);

        (new SendScheduledReport($schedule->id, $actor->id))->handle(app(EmailService::class));

        Queue::assertPushed(SendQueuedTemplateMail::class, 1);
        Queue::assertPushed(
            SendQueuedTemplateMail::class,
            fn (SendQueuedTemplateMail $job) => $job->to === $actor->email,
        );

        $this->assertNull($schedule->fresh()->last_status);
    }

    public function test_schedule_text_is_escaped_before_it_reaches_the_mail_body(): void
    {
        $this->seed(ReportScheduleSeeder::class);
        Queue::fake([SendQueuedTemplateMail::class]);

        $owner = $this->actingAsRole('admin');
        $schedule = $this->schedule($owner, [
            'name' => '<script>alert(1)</script>',
            'message' => '<img src=x onerror=alert(1)>',
        ]);

        (new SendScheduledReport($schedule->id))->handle(app(EmailService::class));

        Queue::assertPushed(SendQueuedTemplateMail::class, function (SendQueuedTemplateMail $job) {
            return ! str_contains($job->body, '<script>')
                && ! str_contains($job->body, '<img ')
                && str_contains($job->body, '&lt;script&gt;')
                && str_contains($job->body, '&lt;img ');
        });
    }
}
