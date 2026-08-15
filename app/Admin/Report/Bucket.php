<?php

namespace App\Admin\Report;

/** The closed set of time granularities. Never a raw string from the request. */
enum Bucket: string
{
    case Hour = 'hour';
    case Day = 'day';
    case Week = 'week';
    case Month = 'month';
    case Quarter = 'quarter';
    case Year = 'year';

    /** DateInterval spec used to step a gap-filled series. */
    public function interval(): string
    {
        return match ($this) {
            self::Hour => 'PT1H',
            self::Day => 'P1D',
            self::Week => 'P1W',
            self::Month => 'P1M',
            self::Quarter => 'P3M',
            self::Year => 'P1Y',
        };
    }

    public function labelKey(): string
    {
        return 'reports.bucket.' . $this->value;
    }

    /** Upper bound on buckets for a given day span; drives the pre-flight cap check. */
    public function approxCountForDays(int $days): int
    {
        $days = max(1, $days);

        return match ($this) {
            self::Hour => $days * 24,
            self::Day => $days,
            self::Week => (int) ceil($days / 7) + 1,
            self::Month => (int) ceil($days / 28) + 1,
            self::Quarter => (int) ceil($days / 90) + 1,
            self::Year => (int) ceil($days / 365) + 1,
        };
    }
}
