<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Report\ReportRegistry;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\Schedule\Frequency;
use App\Admin\Report\Schedule\RecipientResolver;
use App\Http\Controllers\Controller;
use App\Jobs\SendScheduledReport;
use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReportScheduleController extends Controller
{
    /** Latin-scripted locales the core-14 PDF fonts can actually render. */
    private const DEFAULT_LATIN_LOCALES = ['en', 'ms', 'id', 'de', 'fr', 'es', 'pt', 'it', 'nl'];

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', ReportSchedule::class);

        $user = $request->user();

        return Inertia::render('Admin/Reports/Schedules', [
            'schedules' => ReportSchedule::visibleTo($user)
                ->orderBy('name')
                ->get()
                ->map(fn (ReportSchedule $s) => $s->toClientArray())
                ->values(),
            'reports' => collect(ReportRegistry::visibleTo($user))
                ->map(fn (array $schema, string $key) => [
                    'key' => $key,
                    'titleKey' => $schema['titleKey'] ?? ('reports.'.$key.'.title'),
                ])
                ->values(),
            'timezones' => DateTimeZone::listIdentifiers(),
            'people' => RecipientResolver::addressableFor($user),
            'canMailExternal' => (bool) $user?->can('reports.schedule.external'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', ReportSchedule::class);

        $user = $request->user();
        $data = $this->validated($request);

        $limit = (int) config('myra.report_schedules.max_per_user', 20);

        if (ReportSchedule::where('user_id', $user->id)->count() >= $limit) {
            throw ValidationException::withMessages([
                'name' => __('reportDelivery.errors.tooManySchedules'),
            ]);
        }

        $schedule = new ReportSchedule($data);
        $schedule->user_id = $user->id;
        $schedule->team_id = $user->current_team_id;
        $schedule->slug = (string) Str::ulid();
        $schedule->next_run_at = $schedule->cadence()->nextRunAfter(CarbonImmutable::now());

        $this->assertDeliverable($schedule, $user);
        $schedule->save();

        return back()->with('success', 'Schedule created.');
    }

    public function update(Request $request, ReportSchedule $reportSchedule): RedirectResponse
    {
        Gate::authorize('update', $reportSchedule);

        $reportSchedule->fill($this->validated($request));
        $reportSchedule->next_run_at = $reportSchedule->cadence()->nextRunAfter(CarbonImmutable::now());
        $reportSchedule->failure_count = 0;

        $this->assertDeliverable($reportSchedule, $request->user());
        $reportSchedule->save();

        return back()->with('success', 'Schedule updated.');
    }

    public function destroy(Request $request, ReportSchedule $reportSchedule): RedirectResponse
    {
        Gate::authorize('delete', $reportSchedule);

        $reportSchedule->delete();

        return back()->with('success', 'Schedule deleted.');
    }

    /** "Send me one now" — queued exactly like a due run, so it proves the pipeline. */
    public function test(Request $request, ReportSchedule $reportSchedule): RedirectResponse
    {
        Gate::authorize('test', $reportSchedule);

        SendScheduledReport::dispatch($reportSchedule->id);

        return back()->with('success', 'Test delivery queued.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'report_key' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:60'],
            'state' => ['required', 'array'],
            'format' => ['required', 'string', 'in:pdf,xlsx,csv,inline'],
            'frequency' => ['required', 'string', 'in:daily,weekly,monthly,quarterly'],
            'day_of_week' => ['nullable', 'integer', 'between:0,6'],
            'day_of_month' => ['nullable', 'integer', 'between:1,31'],
            'hour' => ['required', 'integer', 'between:0,23'],
            'minute' => ['required', 'integer', 'between:0,59'],
            'timezone' => ['required', 'string', 'max:64'],
            'recipients' => ['required', 'array', 'min:1', 'max:'.(int) config('myra.report_schedules.max_recipients', 25)],
            'recipients.*.type' => ['required', 'string', 'in:user,email'],
            'recipients.*.id' => ['nullable', 'integer'],
            'recipients.*.address' => ['nullable', 'email', 'max:190'],
            'subject' => ['nullable', 'string', 'max:190'],
            'message' => ['nullable', 'string', 'max:2000'],
            'skip_if_empty' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        if (! ReportRegistry::has($data['report_key'])) {
            throw ValidationException::withMessages([
                'report_key' => __('reportDelivery.errors.unknownReport'),
            ]);
        }

        if (! in_array($data['timezone'], DateTimeZone::listIdentifiers(), true)) {
            throw ValidationException::withMessages([
                'timezone' => __('reportDelivery.errors.badTimezone'),
            ]);
        }

        $frequency = Frequency::from($data['frequency']);

        if ($frequency->needsDayOfWeek() && $data['day_of_week'] === null) {
            $data['day_of_week'] = 1;
        }

        if ($frequency->needsDayOfMonth() && $data['day_of_month'] === null) {
            $data['day_of_month'] = 1;
        }

        // Size and shape first, then the SAME authority a live run uses. A saved
        // state is replayed through ReportRequest::parse(), so validating it here
        // with anything weaker would be theatre.
        $encoded = json_encode($data['state']);

        if ($encoded === false || strlen($encoded) > ReportRequest::MAX_BYTES) {
            throw ValidationException::withMessages(['state' => __('reports.errors.malformed')]);
        }

        ReportRequest::parse($data['state'], ReportRegistry::resolve($data['report_key']), $request->user());

        $this->assertPdfRenderable($data['format'], $request->user());

        return $data;
    }

    private function assertPdfRenderable(string $format, mixed $user): void
    {
        if ($format !== 'pdf') {
            return;
        }

        $locale = $user ? RecipientResolver::localeOf($user) : (string) config('app.locale', 'en');
        $latin = (array) config('myra.report_schedules.latin_locales', self::DEFAULT_LATIN_LOCALES);

        if (! in_array($locale, $latin, true)) {
            throw ValidationException::withMessages([
                'format' => __('reports.errors.pdfUnsupportedCharset'),
            ]);
        }
    }

    /** A schedule nobody can receive is a schedule that will never be noticed failing. */
    private function assertDeliverable(ReportSchedule $schedule, mixed $user): void
    {
        if (RecipientResolver::resolve($schedule, $user) === []) {
            throw ValidationException::withMessages([
                'recipients' => __('reportDelivery.errors.noRecipients'),
            ]);
        }
    }
}
