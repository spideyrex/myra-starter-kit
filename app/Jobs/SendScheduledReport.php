<?php

namespace App\Jobs;

use App\Admin\Export\XlsxRowWriter;
use App\Admin\Report\Contracts\ReportDocumentRenderer;
use App\Admin\Report\ReportRegistry;
use App\Admin\Report\ReportResult;
use App\Admin\Report\ReportRunner;
use App\Admin\Report\Schedule\RecipientResolver;
use App\Admin\Tenancy\Concerns\TenantAware;
use App\Models\ReportSchedule;
use App\Models\User;
use App\Notifications\SystemNotification;
use App\Services\EmailService;
use App\Support\Csv;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs the report AS THE SCHEDULE OWNER, so the ownership scope and every
 * FieldSpec permission still apply to a mail sent at 3 a.m. If the owner has
 * since lost reports.view the schedule is paused, not sent.
 */
final class SendScheduledReport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public int $tries = 3;

    /** @var int[] */
    public array $backoff = [60, 300, 900];

    public int $uniqueFor = 3600;

    /**
     * @param  int|null  $testForUserId  "Send me one now": runs AS this user and
     *   mails only them. Never the schedule's recipient list — the test ability
     *   is granted at read level, and a teammate must not be able to fan a team
     *   schedule out to everyone on it, nor receive the owner's scoped rows.
     */
    public function __construct(
        public readonly int $scheduleId,
        public readonly ?int $testForUserId = null,
    ) {
        // A worker has no session; without this the report would run with no
        // tenant resolved and — the scope failing closed — mail an empty table.
        $this->captureTenant();
    }

    public function uniqueId(): string
    {
        return "report-schedule-{$this->scheduleId}"
            .($this->testForUserId === null ? '' : "-test-{$this->testForUserId}");
    }

    private function isTest(): bool
    {
        return $this->testForUserId !== null;
    }

    public function handle(EmailService $mail): void
    {
        $schedule = ReportSchedule::with('user')->find($this->scheduleId);

        if ($schedule === null || (! $schedule->is_active && ! $this->isTest())) {
            return;
        }

        $actor = $this->isTest()
            ? User::find($this->testForUserId)
            : $schedule->user;

        if ($actor === null || ! $actor->can('reports.view')) {
            if (! $this->isTest()) {
                $this->pause($schedule, 'reportDelivery.errors.ownerLostAccess');
            }

            return;
        }

        // Same authority the schedules picker uses — 'reports.view' alone says
        // nothing about THIS report.
        if (ReportRegistry::schemaFor([$schedule->report_key], $actor) === []) {
            if (! $this->isTest()) {
                $this->pause($schedule, 'reportDelivery.errors.ownerLostAccess');
            }

            return;
        }

        $previous = Auth::user();
        Auth::setUser($actor);

        $disk = (string) config('myra.report_schedules.pdf_disk', 'local');
        $path = null;

        try {
            $definition = ReportRegistry::resolve($schedule->report_key);
            $request = $schedule->toReportRequest($actor);
            $result = (new ReportRunner($definition))->run($request, $actor);

            // A test send always delivers: an empty report still proves the pipeline.
            if ($schedule->skip_if_empty && ! $this->isTest() && $result->rows() === []) {
                $this->finish($schedule, 'skipped');

                return;
            }

            $recipients = $this->isTest()
                ? [['email' => (string) $actor->email, 'name' => (string) $actor->name]]
                : RecipientResolver::resolve($schedule, $actor);

            if ($recipients === []) {
                $this->finish($schedule, 'no_recipients');

                return;
            }

            [$attachments, $path] = $this->attachment($schedule, $definition, $request, $result, $disk);

            $mail->queueTemplate(
                'scheduled-report',
                array_column($recipients, 'email'),
                $this->variables($schedule, $result),
                $attachments,
            );

            $this->finish($schedule, 'sent');
        } finally {
            if ($path !== null) {
                Storage::disk($disk)->delete($path);
            }

            if ($previous !== null) {
                Auth::setUser($previous);
            } else {
                Auth::forgetUser();
            }
        }
    }

    public function failed(Throwable $e): void
    {
        $schedule = ReportSchedule::find($this->scheduleId);

        // A failed test send must not count towards the auto-pause budget.
        if ($schedule === null || $this->isTest()) {
            return;
        }

        $failures = (int) $schedule->failure_count + 1;

        $schedule->forceFill([
            'last_run_at' => now(),
            'last_status' => 'failed',
            'last_error' => mb_substr($e->getMessage(), 0, 1000),
            'failure_count' => $failures,
        ])->save();

        if ($failures >= (int) config('myra.report_schedules.pause_after_fails', 3)) {
            $this->pause($schedule, 'reportDelivery.errors.pausedAfterFailures');
        }
    }

    /**
     * @return array{0: array<int, array{data:string, name:string, mime:string}>, 1: string|null}
     */
    private function attachment(
        ReportSchedule $schedule,
        mixed $definition,
        mixed $request,
        ReportResult $result,
        string $disk,
    ): array {
        $base = $schedule->report_key.'-'.now()->format('Ymd');

        if ($schedule->format === 'inline') {
            return [[], null];
        }

        if ($schedule->format === 'pdf') {
            $renderer = app()->bound(ReportDocumentRenderer::class) ? app(ReportDocumentRenderer::class) : null;

            if ($renderer === null) {
                return [[], null];
            }

            $path = $renderer->writeToDisk($definition, $request, $result, $disk);

            return [[[
                'data' => (string) Storage::disk($disk)->get($path),
                'name' => $base.'.pdf',
                'mime' => 'application/pdf',
            ]], $path];
        }

        [$labels, $rows] = $this->tabular($result);

        if ($schedule->format === 'xlsx' && XlsxRowWriter::isSupported()) {
            ob_start();
            $writer = new XlsxRowWriter($schedule->report_key);
            $writer->open();
            $writer->header($labels);
            foreach ($rows as $row) {
                $writer->row($row);
            }
            $writer->close();

            return [[[
                'data' => (string) ob_get_clean(),
                'name' => $base.'.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]], null];
        }

        $csv = implode("\n", array_map(
            static fn (array $row) => implode(',', array_map(
                static fn ($v) => '"'.str_replace('"', '""', Csv::cell($v)).'"',
                $row,
            )),
            array_merge([$labels], $rows),
        ));

        return [[['data' => $csv, 'name' => $base.'.csv', 'mime' => 'text/csv']], null];
    }

    /** @return array{0: string[], 1: array<int, array<int, mixed>>} */
    private function tabular(ReportResult $result): array
    {
        $payload = $result->toArray();
        $measures = array_values((array) ($payload['measures'] ?? []));

        $labels = [(string) ($payload['dimension']['key'] ?? 'key')];

        foreach ($measures as $measure) {
            $labels[] = (string) ($measure['key'] ?? '');
        }

        $rows = [];

        foreach ((array) ($payload['rows'] ?? []) as $row) {
            $line = [(string) ($row['label'] ?? '')];

            foreach ($measures as $measure) {
                $line[] = $row['values'][$measure['key']] ?? '';
            }

            $rows[] = $line;
        }

        return [$labels, $rows];
    }

    /**
     * Every scalar is escaped here: replaceVariables() does a raw str_replace into
     * the template's HTML body, so an unescaped schedule name is stored XSS in an
     * outbound mail. {{kpi_table}} is the one deliberate HTML value.
     *
     * @return array<string,string>
     */
    private function variables(ReportSchedule $schedule, ReportResult $result): array
    {
        $payload = $result->toArray();
        $period = sprintf('%s – %s', $payload['period']['from'] ?? '', $payload['period']['to'] ?? '');

        $kpis = [];

        foreach ((array) ($payload['measures'] ?? []) as $measure) {
            $key = (string) ($measure['key'] ?? '');
            $kpis[] = '<tr><td>'.e($key).'</td><td>'.e((string) ($payload['totals'][$key] ?? '—')).'</td></tr>';
        }

        $message = trim((string) ($schedule->message ?? ''));

        return [
            'report_name' => e((string) $schedule->name),
            'period' => e($period),
            'comparison' => e($payload['comparison'] === null
                ? '—'
                : sprintf('%s – %s', $payload['comparison']['from'] ?? '', $payload['comparison']['to'] ?? '')),
            'message' => $message === '' ? '' : '<p>'.nl2br(e($message)).'</p>',
            'kpi_table' => '<table>'.implode('', $kpis).'</table>',
            'view_url' => e($this->viewUrl($schedule)),
        ];
    }

    /** Built server-side. The schedule's free-text message is never a URL. */
    private function viewUrl(ReportSchedule $schedule): string
    {
        $router = app('router');

        if ($router->has('admin.reports.show')) {
            return route('admin.reports.show', $schedule->report_key);
        }

        return $router->has('admin.report-schedules.index')
            ? route('admin.report-schedules.index')
            : url('/');
    }

    private function finish(ReportSchedule $schedule, string $status): void
    {
        // A test send reports nothing about the recurring run's health.
        if ($this->isTest()) {
            return;
        }

        $schedule->forceFill([
            'last_run_at' => now(),
            'last_status' => $status,
            'last_error' => null,
            'failure_count' => 0,
        ])->save();
    }

    private function pause(ReportSchedule $schedule, string $reasonKey): void
    {
        $schedule->forceFill([
            'is_active' => false,
            'last_status' => 'paused',
            'last_error' => $reasonKey,
        ])->save();

        // A silently dead weekly report is worse than none.
        $schedule->user?->notify(new SystemNotification(
            __('reportDelivery.notifications.pausedTitle'),
            __('reportDelivery.notifications.pausedBody', ['name' => $schedule->name]),
        ));
    }
}
