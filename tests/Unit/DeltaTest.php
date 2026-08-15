<?php

namespace Tests\Unit;

use App\Admin\Report\Delta;
use PHPUnit\Framework\TestCase;

class DeltaTest extends TestCase
{
    public function test_no_previous_window_is_not_a_delta(): void
    {
        $this->assertNull(Delta::between(5, null));
    }

    public function test_a_missing_additive_current_counts_as_zero(): void
    {
        $delta = Delta::between(null, 8);

        $this->assertSame(-100.0, $delta['percent']);
        $this->assertSame('down', $delta['direction']);
    }

    public function test_a_missing_non_additive_current_is_not_a_delta(): void
    {
        $this->assertNull(Delta::between(null, 8, false, false));
    }

    public function test_a_zero_previous_window_has_no_percent(): void
    {
        $delta = Delta::between(4, 0);

        $this->assertNull($delta['percent']);
        $this->assertSame('up', $delta['direction']);
    }

    public function test_invert_trend_flips_the_good_flag(): void
    {
        $this->assertFalse(Delta::between(10, 5, true)['good']);
        $this->assertTrue(Delta::between(5, 10, true)['good']);
    }
}
