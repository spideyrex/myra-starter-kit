<?php

namespace App\Models;

use App\Admin\Report\ReportRegistry;
use App\Admin\Report\ReportRequest;
use App\Admin\Report\Schedule\Cadence;
use App\Admin\Report\Schedule\Frequency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportSchedule extends Model
{
    protected $fillable = [
        'report_key', 'name', 'state', 'format', 'frequency', 'day_of_week', 'day_of_month',
        'hour', 'minute', 'timezone', 'recipients', 'subject', 'message', 'skip_if_empty',
        'is_active', 'team_id',
    ];

    protected $casts = [
        'recipients' => 'array',
        'state' => 'array',
        'is_active' => 'bool',
        'skip_if_empty' => 'bool',
        'frequency' => Frequency::class,
        'next_run_at' => 'datetime',
        'last_run_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** Index range scan over (is_active, next_run_at) — never a per-row cron evaluation. */
    public function scopeDue(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now());
    }

    /** Own schedules, plus the current team's — mirrors TableView::scopeVisibleTo. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $q->where(function (Builder $q) use ($user) {
            $q->where('user_id', $user->id)
                ->orWhere(function (Builder $q) use ($user) {
                    $q->whereNotNull('team_id')
                        ->where('team_id', $user->current_team_id)
                        ->whereIn('team_id', $user->teams()->select('teams.id'));
                });
        });
    }

    public function isOwnedBy(User $user): bool
    {
        return (int) $this->user_id === (int) $user->id;
    }

    public function cadence(): Cadence
    {
        return Cadence::fromModel($this);
    }

    /** Moves next_run_at strictly forward. Callers hold the row lock. */
    public function advance(?CarbonImmutable $from = null): void
    {
        $this->forceFill([
            'next_run_at' => $this->cadence()->nextRunAfter($from ?? CarbonImmutable::now()),
        ])->save();
    }

    public function toReportRequest(): ReportRequest
    {
        $definition = ReportRegistry::resolve($this->report_key);

        return ReportRequest::parse($this->state ?? [], $definition, $this->user);
    }

    /** @return array<string, mixed> The client shape the schedules page consumes. */
    public function toClientArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'report_key' => $this->report_key,
            'name' => $this->name,
            'format' => $this->format,
            'frequency' => $this->frequency instanceof Frequency ? $this->frequency->value : $this->frequency,
            'day_of_week' => $this->day_of_week,
            'day_of_month' => $this->day_of_month,
            'hour' => (int) $this->hour,
            'minute' => (int) $this->minute,
            'timezone' => $this->timezone,
            'recipients' => $this->recipients ?? [],
            'subject' => $this->subject,
            'message' => $this->message,
            'skip_if_empty' => (bool) $this->skip_if_empty,
            'is_active' => (bool) $this->is_active,
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'last_status' => $this->last_status,
            'last_error' => $this->last_error,
            'failure_count' => (int) $this->failure_count,
            'state' => $this->state ?? [],
        ];
    }
}
