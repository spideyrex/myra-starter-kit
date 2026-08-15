<?php

namespace App\Support;

use App\Admin\Report\Bucket;
use App\Admin\Report\Period;
use Carbon\CarbonImmutable;
use DateInterval;
use InvalidArgumentException;

/**
 * The ONLY place a time bucket becomes SQL. $column is asserted against the
 * same identifier regex FieldSpec uses; no request string reaches here.
 *
 * Week keys differ across drivers (sqlite %W vs ISO elsewhere). That is
 * acceptable: a week key is only ever compared to other keys produced by the
 * same driver in the same run, and series() reproduces the active driver's rule.
 */
final class DateBucket
{
    private const IDENTIFIER = '/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/i';

    /** Returns a grammar-safe grouping expression. */
    public static function sql(Bucket $b, string $column, string $driver): string
    {
        if (preg_match(self::IDENTIFIER, $column) !== 1) {
            throw new InvalidArgumentException("Invalid SQL identifier [{$column}].");
        }

        $c = $column;

        return match ($driver) {
            'sqlite' => match ($b) {
                Bucket::Hour => "strftime('%Y-%m-%d %H:00', {$c})",
                Bucket::Day => "strftime('%Y-%m-%d', {$c})",
                Bucket::Week => "strftime('%Y-W%W', {$c})",
                Bucket::Month => "strftime('%Y-%m', {$c})",
                Bucket::Quarter => "strftime('%Y', {$c}) || '-Q' || ((cast(strftime('%m', {$c}) as integer)+2)/3)",
                Bucket::Year => "strftime('%Y', {$c})",
            },
            'pgsql' => match ($b) {
                Bucket::Hour => "to_char({$c}, 'YYYY-MM-DD HH24:00')",
                Bucket::Day => "to_char({$c}, 'YYYY-MM-DD')",
                Bucket::Week => "to_char({$c}, 'IYYY-\"W\"IW')",
                Bucket::Month => "to_char({$c}, 'YYYY-MM')",
                Bucket::Quarter => "to_char({$c}, 'YYYY-\"Q\"Q')",
                Bucket::Year => "to_char({$c}, 'YYYY')",
            },
            default => match ($b) {
                Bucket::Hour => "date_format({$c}, '%Y-%m-%d %H:00')",
                Bucket::Day => "date_format({$c}, '%Y-%m-%d')",
                Bucket::Week => "date_format({$c}, '%x-W%v')",
                Bucket::Month => "date_format({$c}, '%Y-%m')",
                Bucket::Quarter => "concat(date_format({$c}, '%Y'), '-Q', quarter({$c}))",
                Bucket::Year => "date_format({$c}, '%Y')",
            },
        };
    }

    /**
     * Gap-filled key list for the period, in ascending order.
     *
     * @return string[]
     */
    public static function series(Bucket $b, Period $p, ?string $driver = null): array
    {
        $driver = $driver ?? 'sqlite';
        $cursor = self::floor($b, $p->start);
        $interval = new DateInterval($b->interval());

        $keys = [];
        $guard = 0;

        while ($cursor->lessThan($p->end) && $guard++ < 20000) {
            $keys[] = self::key($b, $cursor, $driver);
            $cursor = $cursor->add($interval);
        }

        return array_values(array_unique($keys));
    }

    /** The bucket key a given instant falls into, matching the driver's SQL. */
    public static function key(Bucket $b, CarbonImmutable $at, string $driver = 'sqlite'): string
    {
        return match ($b) {
            Bucket::Hour => $at->format('Y-m-d H:00'),
            Bucket::Day => $at->format('Y-m-d'),
            Bucket::Week => $driver === 'sqlite'
                ? $at->format('Y') . '-W' . str_pad((string) self::mondayBasedWeek($at), 2, '0', STR_PAD_LEFT)
                : $at->format('o-\WW'),
            Bucket::Month => $at->format('Y-m'),
            Bucket::Quarter => $at->format('Y') . '-Q' . (int) ceil(((int) $at->format('n')) / 3),
            Bucket::Year => $at->format('Y'),
        };
    }

    /** The half-open [start, end) instants a bucket key covers. */
    public static function boundsFor(Bucket $b, string $key, string $tz = 'UTC'): ?array
    {
        $start = match ($b) {
            Bucket::Hour => self::tryParse($key, 'Y-m-d H:i', $tz),
            Bucket::Day => self::tryParse($key, 'Y-m-d', $tz),
            Bucket::Month => self::tryParse($key . '-01', 'Y-m-d', $tz),
            Bucket::Year => self::tryParse($key . '-01-01', 'Y-m-d', $tz),
            Bucket::Quarter => self::quarterStart($key, $tz),
            Bucket::Week => null,
        };

        if ($start === null) {
            return null;
        }

        return [$start, $start->add(new DateInterval($b->interval()))];
    }

    /** '2026-08' -> 'Aug 2026'. Formatting only; never touches SQL. */
    public static function label(Bucket $b, string $key, string $locale = 'en'): string
    {
        $bounds = self::boundsFor($b, $key);

        if ($bounds === null) {
            return $key;
        }

        /** @var CarbonImmutable $at */
        $at = $bounds[0];

        return match ($b) {
            Bucket::Hour => $at->locale($locale)->isoFormat('D MMM HH:mm'),
            Bucket::Day => $at->locale($locale)->isoFormat('D MMM'),
            Bucket::Month => $at->locale($locale)->isoFormat('MMM YYYY'),
            Bucket::Quarter, Bucket::Year, Bucket::Week => $key,
        };
    }

    private static function floor(Bucket $b, CarbonImmutable $at): CarbonImmutable
    {
        return match ($b) {
            Bucket::Hour => $at->startOfHour(),
            Bucket::Day => $at->startOfDay(),
            Bucket::Week => $at->startOfWeek(),
            Bucket::Month => $at->startOfMonth(),
            Bucket::Quarter => $at->startOfQuarter(),
            Bucket::Year => $at->startOfYear(),
        };
    }

    /** Reproduces C strftime %W: Monday-first, days before the first Monday are week 00. */
    private static function mondayBasedWeek(CarbonImmutable $at): int
    {
        $dayOfYear = (int) $at->format('z');
        $dow = (int) $at->format('w');
        $mondayBased = $dow === 0 ? 6 : $dow - 1;

        return intdiv($dayOfYear + 7 - $mondayBased, 7);
    }

    private static function tryParse(string $value, string $format, string $tz): ?CarbonImmutable
    {
        $parsed = CarbonImmutable::createFromFormat('!' . $format, $value, $tz);

        return $parsed === false ? null : $parsed;
    }

    private static function quarterStart(string $key, string $tz): ?CarbonImmutable
    {
        if (preg_match('/^(\d{4})-Q([1-4])$/', $key, $m) !== 1) {
            return null;
        }

        $month = ((int) $m[2] - 1) * 3 + 1;

        return self::tryParse(sprintf('%s-%02d-01', $m[1], $month), 'Y-m-d', $tz);
    }
}
