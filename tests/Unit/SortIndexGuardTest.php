<?php

namespace Tests\Unit;

use App\Admin\Query\SortIndexGuard;
use App\Admin\Query\UnindexedSortException;
use App\Models\MyraScaleRow;
use App\Models\User;
use Tests\TestCase;

class SortIndexGuardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SortIndexGuard::flush();
    }

    public function test_missing_reports_only_columns_without_a_leading_index(): void
    {
        // scale_status_created_idx leads on `status`; `email` has no index at all.
        $missing = SortIndexGuard::missing('myra_scale_rows', ['status', 'email', 'name', 'created_at']);

        $this->assertSame(['email'], $missing);
    }

    public function test_missing_accepts_the_column_to_index_map(): void
    {
        $this->assertSame(
            [],
            SortIndexGuard::missing('myra_scale_rows', ['email' => 'scale_created_id_idx']),
        );

        $this->assertSame(
            ['email'],
            SortIndexGuard::missing('myra_scale_rows', ['email' => 'no_such_index']),
        );
    }

    public function test_assert_is_a_no_op_while_the_flag_is_off(): void
    {
        config(['myra.performance.assert_indexed_sorts' => false]);

        SortIndexGuard::assert(new MyraScaleRow, 'email', ['email']);

        $this->assertTrue(true);
    }

    public function test_assert_throws_for_an_unindexed_sort_when_enabled(): void
    {
        config(['myra.performance.assert_indexed_sorts' => true]);

        $this->expectException(UnindexedSortException::class);
        $this->expectExceptionMessageMatches('/myra_scale_rows\.email/');

        SortIndexGuard::assert(new MyraScaleRow, 'email', ['email', 'name']);
    }

    public function test_assert_passes_for_an_indexed_sort(): void
    {
        config(['myra.performance.assert_indexed_sorts' => true]);

        SortIndexGuard::assert(new MyraScaleRow, 'name', ['name', 'email']);
        SortIndexGuard::assert(new MyraScaleRow, 'created_at', ['created_at']);

        $this->assertTrue(true);
    }

    public function test_assert_ignores_a_sort_that_is_not_whitelisted(): void
    {
        config(['myra.performance.assert_indexed_sorts' => true]);

        SortIndexGuard::assert(new MyraScaleRow, 'email', ['name']);

        $this->assertTrue(true);
    }

    public function test_production_never_throws(): void
    {
        config(['myra.performance.assert_indexed_sorts' => true]);
        app()->detectEnvironment(fn () => 'production');

        SortIndexGuard::assert(new User, 'phone', ['phone']);

        $this->assertTrue(true);
    }
}
