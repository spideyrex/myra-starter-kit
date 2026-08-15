<?php

namespace App\Admin\Report\Schedule;

use App\Models\ReportSchedule;
use Carbon\CarbonImmutable;

/**
 * Turns a preset cadence into the next absolute instant. All arithmetic happens
 * in the schedule's own timezone so a DST shift moves the wall-clock time, not
 * the reader's expectation, and a day-of-month past the end of a short month is
 * clamped rather than rolled into the next one.
 */
final class Cadence
{
    private function __construct(
        public readonly Frequency $frequency,
        public readonly string $timezone,
        public readonly int $hour,
        public readonly int $minute,
        public readonly ?int $dayOfWeek,
        public readonly ?int $dayOfMonth,
    ) {}

    public static function of(
        Frequency $frequency,
        string $timezone = 'UTC',
        int $hour = 8,
        int $minute = 0,
        ?int $dayOfWeek = null,
        ?int $dayOfMonth = null,
    ): self {
        return new self(
            $frequency,
            $timezone,
            max(0, min(23, $hour)),
            max(0, min(59, $minute)),
            $dayOfWeek === null ? null : max(0, min(6, $dayOfWeek)),
            $dayOfMonth === null ? null : max(1, min(31, $dayOfMonth)),
        );
    }

    public static function fromModel(ReportSchedule $s): self
    {
        return self::of(
            $s->frequency instanceof Frequency ? $s->frequency : Frequency::from((string) $s->frequency),
            (string) ($s->timezone ?: 'UTC'),
            (int) $s->hour,
            (int) $s->minute,
            $s->day_of_week === null ? null : (int) $s->day_of_week,
            $s->day_of_month === null ? null : (int) $s->day_of_month,
        );
    }

    public function nextRunAfter(?CarbonImmutable $from = null): CarbonImmutable
    {
        $local = ($from ?? CarbonImmutable::now())->setTimezone($this->timezone);

        $candidate = match ($this->frequency) {
            Frequency::Daily => $this->daily($local),
            Frequency::Weekly => $this->weekly($local),
            Frequency::Monthly => $this->monthly($local),
            Frequency::Quarterly => $this->quarterly($local),
        };

        return $candidate->setTimezone($this->timezone);
    }

    public function describeKey(): string
    {
        return 'reportDelivery.describe.'.$this->frequency->value;
    }

    private function daily(CarbonImmutable $local): CarbonImmutable
    {
        $candidate = $this->at($local);

        return $candidate->greaterThan($local) ? $candidate : $this->at($local->addDay());
    }

    private function weekly(CarbonImmutable $local): CarbonImmutable
    {
        $target = $this->dayOfWeek ?? 1;
        $delta = ($target - (int) $local->dayOfWeek + 7) % 7;
        $candidate = $this->at($local->addDays($delta));

        return $candidate->greaterThan($local) ? $candidate : $this->at($local->addDays($delta + 7));
    }

    private function monthly(CarbonImmutable $local): CarbonImmutable
    {
        $candidate = $this->onDayOf($local->startOfMonth());

        if ($candidate->greaterThan($local)) {
            return $candidate;
        }

        return $this->onDayOf($local->startOfMonth()->addMonthNoOverflow());
    }

    private function quarterly(CarbonImmutable $local): CarbonImmutable
    {
        $candidate = $this->onDayOf($local->startOfQuarter());

        if ($candidate->greaterThan($local)) {
            return $candidate;
        }

        return $this->onDayOf($local->startOfQuarter()->addMonthsNoOverflow(3));
    }

    /** Same calendar day, at the configured wall-clock time. */
    private function at(CarbonImmutable $day): CarbonImmutable
    {
        return $day->setTime($this->hour, $this->minute, 0);
    }

    /** Clamps day 31 into a short month instead of overflowing into the next one. */
    private function onDayOf(CarbonImmutable $monthStart): CarbonImmutable
    {
        $day = min($this->dayOfMonth ?? 1, $monthStart->daysInMonth);

        return $this->at($monthStart->setDay($day));
    }
}
