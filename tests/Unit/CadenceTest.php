<?php

namespace Tests\Unit;

use App\Admin\Report\Schedule\Cadence;
use App\Admin\Report\Schedule\Frequency;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class CadenceTest extends TestCase
{
    public function test_weekly_lands_on_the_next_matching_local_weekday(): void
    {
        $cadence = Cadence::of(Frequency::Weekly, 'Asia/Kuala_Lumpur', 8, 0, 1);

        // A Wednesday.
        $from = CarbonImmutable::parse('2026-08-12 15:00', 'Asia/Kuala_Lumpur');
        $next = $cadence->nextRunAfter($from);

        $this->assertSame('2026-08-17 08:00', $next->format('Y-m-d H:i'));
        $this->assertSame('Monday', $next->format('l'));
    }

    public function test_weekly_skips_a_target_day_whose_time_has_passed(): void
    {
        $cadence = Cadence::of(Frequency::Weekly, 'UTC', 8, 0, 1);
        $from = CarbonImmutable::parse('2026-08-17 09:00', 'UTC');

        $this->assertSame('2026-08-24 08:00', $cadence->nextRunAfter($from)->format('Y-m-d H:i'));
    }

    public function test_monthly_day_31_clamps_into_a_short_month(): void
    {
        $cadence = Cadence::of(Frequency::Monthly, 'UTC', 8, 0, null, 31);
        $from = CarbonImmutable::parse('2026-01-31 09:00', 'UTC');

        $next = $cadence->nextRunAfter($from);

        $this->assertSame('2026-02-28', $next->format('Y-m-d'));
        $this->assertNotSame('03', $next->format('m'));
    }

    public function test_daily_never_returns_a_past_instant(): void
    {
        $cadence = Cadence::of(Frequency::Daily, 'UTC', 8, 0);
        $from = CarbonImmutable::parse('2026-08-16 08:00', 'UTC');

        $this->assertTrue($cadence->nextRunAfter($from)->greaterThan($from));
    }

    public function test_a_spring_forward_boundary_produces_a_real_local_time(): void
    {
        // 2026-03-29 02:30 does not exist in Europe/London.
        $cadence = Cadence::of(Frequency::Daily, 'Europe/London', 2, 30);
        $from = CarbonImmutable::parse('2026-03-28 12:00', 'Europe/London');

        $next = $cadence->nextRunAfter($from);
        $roundTrip = CarbonImmutable::parse($next->format('Y-m-d H:i:s'), 'Europe/London');

        $this->assertTrue($next->greaterThan($from));
        $this->assertSame($next->getTimestamp(), $roundTrip->getTimestamp());
    }

    public function test_quarterly_moves_a_whole_quarter(): void
    {
        $cadence = Cadence::of(Frequency::Quarterly, 'UTC', 8, 0, null, 1);
        $from = CarbonImmutable::parse('2026-08-16 09:00', 'UTC');

        $this->assertSame('2026-10-01 08:00', $cadence->nextRunAfter($from)->format('Y-m-d H:i'));
    }
}
