<?php

namespace App\Admin\Report;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * A half-open window [start, end). Half-open is deliberate: it makes
 * "previous period" contiguous with no off-by-one at the boundary and lets
 * whereBetween be replaced by >= / < , which uses a plain index range scan.
 *
 * WIRE vs INTERNAL: `from`/`to` on the wire are calendar dates in the period's
 * timezone and `to` is INCLUSIVE. Internally `end` is EXCLUSIVE
 * (end = to + 1 day in $tz) and both bounds are stored in UTC, which is what
 * the stored timestamps are. This is the single most likely source of
 * off-by-one bugs — read it twice before changing anything here.
 */
final class Period
{
    /** ~5 years; wider is a 422, not a slow query. */
    public const MAX_DAYS = 1830;

    private function __construct(
        public readonly CarbonImmutable $start,
        public readonly CarbonImmutable $end,
        public readonly PeriodPreset $preset,
        public readonly string $timezone,
    ) {}

    public static function preset(PeriodPreset $p, string $tz = 'UTC'): self
    {
        $tz = self::assertTimezone($tz);
        $now = CarbonImmutable::now($tz);

        [$start, $end] = match ($p) {
            PeriodPreset::Today => [$now->startOfDay(), $now->startOfDay()->addDay()],
            PeriodPreset::Yesterday => [$now->subDay()->startOfDay(), $now->startOfDay()],
            PeriodPreset::Last7Days => [$now->startOfDay()->subDays(6), $now->startOfDay()->addDay()],
            PeriodPreset::Last90Days => [$now->startOfDay()->subDays(89), $now->startOfDay()->addDay()],
            PeriodPreset::ThisWeek => [$now->startOfWeek(), $now->startOfWeek()->addWeek()],
            PeriodPreset::ThisMonth => [$now->startOfMonth(), $now->startOfMonth()->addMonth()],
            PeriodPreset::ThisQuarter => [$now->startOfQuarter(), $now->startOfQuarter()->addMonths(3)],
            PeriodPreset::ThisYear => [$now->startOfYear(), $now->startOfYear()->addYear()],
            PeriodPreset::LastWeek => [$now->subWeek()->startOfWeek(), $now->subWeek()->startOfWeek()->addWeek()],
            PeriodPreset::LastMonth => [$now->subMonthNoOverflow()->startOfMonth(), $now->startOfMonth()],
            PeriodPreset::LastQuarter => [$now->subQuarterNoOverflow()->startOfQuarter(), $now->startOfQuarter()],
            PeriodPreset::LastYear => [$now->subYear()->startOfYear(), $now->startOfYear()],
            // Custom without explicit bounds degrades to the default window
            // rather than an unbounded scan.
            default => [$now->startOfDay()->subDays(29), $now->startOfDay()->addDay()],
        };

        return new self(
            $start->setTimezone('UTC'),
            $end->setTimezone('UTC'),
            $p,
            $tz,
        );
    }

    /** @throws ReportException on reversed bounds, unparseable dates, or > MAX_DAYS */
    public static function between(string $from, string $to, string $tz = 'UTC'): self
    {
        $tz = self::assertTimezone($tz);

        try {
            $start = CarbonImmutable::parse($from, $tz)->startOfDay();
            $endInclusive = CarbonImmutable::parse($to, $tz)->startOfDay();
        } catch (Throwable) {
            throw ReportException::make('reports.errors.badPeriod');
        }

        if ($endInclusive->lessThan($start)) {
            throw ReportException::make('reports.errors.badPeriod');
        }

        $period = new self(
            $start->setTimezone('UTC'),
            $endInclusive->addDay()->setTimezone('UTC'),
            PeriodPreset::Custom,
            $tz,
        );

        if ($period->days() > self::MAX_DAYS) {
            throw ReportException::make('reports.errors.periodTooWide');
        }

        return $period;
    }

    /** Same LENGTH, immediately preceding: [start - len, start). */
    public function previous(): self
    {
        $seconds = $this->lengthInSeconds();

        return new self(
            $this->start->subSeconds($seconds),
            $this->start,
            $this->preset,
            $this->timezone,
        );
    }

    /** Same dates, minus one calendar year. */
    public function previousYear(): self
    {
        return new self(
            $this->start->subYear(),
            $this->end->subYear(),
            $this->preset,
            $this->timezone,
        );
    }

    public function comparison(ComparisonPeriod $mode): ?self
    {
        return match ($mode) {
            ComparisonPeriod::None => null,
            ComparisonPeriod::Previous => $this->previous(),
            ComparisonPeriod::Year => $this->previousYear(),
        };
    }

    /** Auto granularity: <=2d Hour, <=60d Day, <=180d Week, <=730d Month, else Quarter. */
    public function defaultBucket(): Bucket
    {
        $days = $this->days();

        return match (true) {
            $days <= 2 => Bucket::Hour,
            $days <= 60 => Bucket::Day,
            $days <= 180 => Bucket::Week,
            $days <= 730 => Bucket::Month,
            default => Bucket::Quarter,
        };
    }

    public function days(): int
    {
        return max(1, (int) round($this->lengthInSeconds() / 86400));
    }

    public function lengthInSeconds(): int
    {
        return $this->end->getTimestamp() - $this->start->getTimestamp();
    }

    /** ['preset'=>..,'from'=>'Y-m-d','to'=>'Y-m-d','tz'=>..] — the canonical wire form. */
    public function toArray(): array
    {
        return [
            'preset' => $this->preset->value,
            'from' => $this->start->setTimezone($this->timezone)->format('Y-m-d'),
            'to' => $this->end->setTimezone($this->timezone)->subSecond()->format('Y-m-d'),
            'tz' => $this->timezone,
        ];
    }

    private static function assertTimezone(string $tz): string
    {
        return in_array($tz, timezone_identifiers_list(), true) ? $tz : 'UTC';
    }
}
