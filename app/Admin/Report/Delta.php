<?php

namespace App\Admin\Report;

/**
 * The one delta calculation, shared by ReportResult and StatResult.
 *
 * `percent === null` means "n/a" (the previous window was zero) and MUST render
 * as an em dash — never Infinity, never 100%.
 */
final class Delta
{
    /**
     * $additive says whether a missing current value means "zero" (count/sum)
     * or "unknown" (avg/min/max/count_distinct). Coercing an unknown to 0
     * would fabricate a -100% against a real previous window.
     *
     * @return array{absolute:float|int,percent:float|null,direction:string,good:bool}|null
     */
    public static function between(
        float|int|null $current,
        float|int|null $previous,
        bool $invertTrend = false,
        bool $additive = true,
    ): ?array {
        if ($previous === null) {
            return null;
        }

        if ($current === null && ! $additive) {
            return null;
        }

        $current ??= 0;

        if ((float) $previous === 0.0 && (float) $current === 0.0) {
            return ['absolute' => 0, 'percent' => 0.0, 'direction' => 'flat', 'good' => true];
        }

        if ((float) $previous === 0.0) {
            $direction = $current > 0 ? 'up' : 'down';

            return [
                'absolute' => $current,
                'percent' => null,
                'direction' => $direction,
                'good' => self::good($direction, $invertTrend),
            ];
        }

        $absolute = $current - $previous;
        $percent = round($absolute / abs($previous) * 100, 1);
        $direction = $absolute > 0 ? 'up' : ($absolute < 0 ? 'down' : 'flat');

        return [
            'absolute' => $absolute,
            'percent' => $percent,
            'direction' => $direction,
            'good' => self::good($direction, $invertTrend),
        ];
    }

    private static function good(string $direction, bool $invertTrend): bool
    {
        if ($direction === 'flat') {
            return true;
        }

        return $invertTrend ? $direction === 'down' : $direction === 'up';
    }
}
