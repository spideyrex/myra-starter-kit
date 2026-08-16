<?php

namespace Tests\Feature\Performance;

use App\Admin\Testing\InteractsWithMyra;
use App\Support\Myra;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The ship gate for the length-aware path: with `stable_sort` off the SQL a real
 * admin table issues is byte-identical to v2.3, and turning it on adds exactly
 * one trailing clause — nothing else.
 */
class StableSortTest extends TestCase
{
    use InteractsWithMyra, ScaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAsWithPermissions(['demo.view']);
        $this->seedScaleRows(5);
    }

    /** The row-fetching SELECT the length-aware demo issues (not the count). */
    private function selectSql(): string
    {
        $captured = [];
        DB::listen(function ($query) use (&$captured) {
            if (str_contains($query->sql, 'myra_scale_rows') && ! str_contains($query->sql, 'count(')) {
                $captured[] = $query->sql;
            }
        });

        $this->get(Myra::adminPath('demo/scale'))->assertSuccessful();

        $this->assertNotEmpty($captured, 'The demo issued no select against myra_scale_rows.');

        return end($captured);
    }

    public function test_disabled_is_the_v23_sql_with_exactly_one_order_by_term(): void
    {
        config(['myra.performance.stable_sort' => false]);

        $sql = $this->selectSql();

        $this->assertSame(
            'select * from "myra_scale_rows" order by "created_at" desc limit 100 offset 0',
            $sql,
        );
    }

    public function test_enabled_appends_exactly_one_id_tiebreak(): void
    {
        config(['myra.performance.stable_sort' => true]);

        $sql = $this->selectSql();

        $this->assertSame(
            'select * from "myra_scale_rows" order by "created_at" desc, "myra_scale_rows"."id" desc limit 100 offset 0',
            $sql,
        );
    }

    public function test_the_row_set_is_unchanged_either_way(): void
    {
        config(['myra.performance.stable_sort' => false]);
        $off = $this->myraTable($this->get(Myra::adminPath('demo/scale')), 'rows')->data();

        config(['myra.performance.stable_sort' => true]);
        $on = $this->myraTable($this->get(Myra::adminPath('demo/scale')), 'rows')->data();

        $this->assertSame(array_column($off, 'id'), array_column($on, 'id'));
    }
}
