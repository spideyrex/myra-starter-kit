<?php

namespace Tests\Unit;

use App\Admin\Report\Bucket;
use App\Admin\Report\ComparisonPeriod;
use App\Admin\Report\Period;
use App\Admin\Report\PeriodPreset;
use App\Admin\Report\ReportException;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class PeriodTest extends TestCase
{
    private function spanning(int $days): Period
    {
        $to = CarbonImmutable::parse('2026-01-01')->addDays($days - 1)->format('Y-m-d');

        return Period::between('2026-01-01', $to);
    }

    public function test_last_30_days_is_thirty_days_and_half_open(): void
    {
        $period = Period::preset(PeriodPreset::Last30Days);

        $this->assertSame(30, $period->days());
        $this->assertSame(30 * 86400, $period->lengthInSeconds());
        $this->assertTrue($period->end->greaterThan($period->start));
    }

    public function test_previous_is_contiguous(): void
    {
        $period = Period::preset(PeriodPreset::Last30Days);
        $previous = $period->previous();

        $this->assertTrue($previous->end->equalTo($period->start));
        $this->assertSame($period->lengthInSeconds(), $previous->lengthInSeconds());
    }

    public function test_previous_year_preserves_month_and_day(): void
    {
        $period = Period::between('2026-03-05', '2026-03-11');
        $previous = $period->previousYear();

        $this->assertSame('03-05', $previous->start->format('m-d'));
        $this->assertSame('2025', $previous->start->format('Y'));
    }

    public function test_comparison_mode_none_yields_null(): void
    {
        $this->assertNull(Period::preset(PeriodPreset::Last7Days)->comparison(ComparisonPeriod::None));
        $this->assertNotNull(Period::preset(PeriodPreset::Last7Days)->comparison(ComparisonPeriod::Previous));
    }

    public function test_reversed_bounds_are_rejected(): void
    {
        $this->expectException(ReportException::class);

        Period::between('2026-08-16', '2026-07-01');
    }

    public function test_a_six_year_span_is_rejected(): void
    {
        try {
            Period::between('2020-01-01', '2026-01-01');
            $this->fail('Expected a six-year span to be rejected.');
        } catch (ReportException $e) {
            $this->assertSame('reports.errors.periodTooWide', $e->key);
        }
    }

    /** @dataProvider bucketBoundaries */
    public function test_default_bucket_boundaries(int $days, Bucket $expected): void
    {
        $this->assertSame($expected, $this->spanning($days)->defaultBucket());
    }

    public static function bucketBoundaries(): array
    {
        return [
            [2, Bucket::Hour],
            [3, Bucket::Day],
            [60, Bucket::Day],
            [61, Bucket::Week],
            [180, Bucket::Week],
            [181, Bucket::Month],
            [730, Bucket::Month],
            [731, Bucket::Quarter],
        ];
    }

    public function test_wire_form_reports_an_inclusive_end_date(): void
    {
        $period = Period::between('2026-07-17', '2026-08-16');
        $wire = $period->toArray();

        $this->assertSame('2026-07-17', $wire['from']);
        $this->assertSame('2026-08-16', $wire['to']);
        $this->assertSame('custom', $wire['preset']);
        $this->assertSame(31, $period->days());
    }
}
