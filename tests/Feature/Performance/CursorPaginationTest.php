<?php

namespace Tests\Feature\Performance;

use App\Admin\Testing\InteractsWithMyra;
use Tests\TestCase;

/**
 * Everything here goes through the REAL /admin/demo/scale-cursor endpoint and
 * reads the REAL Inertia prop. Nothing is asserted against a hand-built payload.
 */
class CursorPaginationTest extends TestCase
{
    use InteractsWithMyra, ScaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pinRootUrl();
        $this->actingAsWithPermissions(['demo.view']);
        $this->seedScaleRows(250);
    }

    private function rows(string $url): array
    {
        $response = $this->get($url);
        $response->assertSuccessful();

        return json_decode(json_encode($response->viewData('page')['props']['rows']), true);
    }

    public function test_the_cursor_endpoint_stamps_its_mode_and_omits_the_count(): void
    {
        $rows = $this->rows('/admin/demo/scale-cursor');

        $this->assertSame('cursor', $rows['meta']['mode']);
        $this->assertNotNull($rows['links']['next']);
        $this->assertNull($rows['links']['prev']);
        $this->assertArrayNotHasKey('total', $rows['meta']);
        $this->assertArrayNotHasKey('last_page', $rows['meta']);
        $this->assertCount(50, $rows['data']);
    }

    public function test_the_probe_reads_the_cursor_mode_from_the_real_response(): void
    {
        $this->myraTable($this->get('/admin/demo/scale-cursor'), 'rows')
            ->assertPaginationMode('cursor')
            ->assertCountRecords(50)
            ->assertHasNextPage()
            ->assertSortedBy('created_at', 'desc');
    }

    public function test_length_aware_responses_carry_no_mode(): void
    {
        $this->myraTable($this->get('/admin/demo/scale'), 'rows')
            ->assertPaginationMode('length-aware')
            ->assertTotal(250);
    }

    public function test_following_the_next_link_yields_a_disjoint_page(): void
    {
        $first = $this->rows('/admin/demo/scale-cursor');
        $second = $this->rows($first['links']['next']);

        $firstIds = array_column($first['data'], 'id');
        $secondIds = array_column($second['data'], 'id');

        $this->assertCount(50, $secondIds);
        $this->assertSame([], array_intersect($firstIds, $secondIds));
    }

    /**
     * `status` has exactly four distinct values across 250 rows. Without the
     * mandatory id tiebreak a cursor walk over it skips and repeats rows; this
     * walks the whole dataset and proves it does neither.
     */
    public function test_a_walk_over_a_non_unique_sort_column_loses_and_repeats_nothing(): void
    {
        $seen = [];
        $url = '/admin/demo/scale-cursor?sort=status&direction=asc';
        $pages = 0;

        while ($url !== null && $pages < 20) {
            $page = $this->rows($url);
            foreach ($page['data'] as $row) {
                $this->assertNotContains($row['id'], $seen, "Row {$row['id']} was returned twice.");
                $seen[] = $row['id'];
            }
            $url = $page['links']['next'];
            $pages++;
        }

        sort($seen);
        $this->assertSame(range(1, 250), $seen);
    }

    public function test_per_page_is_capped_and_an_unknown_sort_is_ignored(): void
    {
        $capped = $this->rows('/admin/demo/scale-cursor?per_page=999');
        $this->assertSame(100, $capped['meta']['per_page']);

        $baseline = array_column($this->rows('/admin/demo/scale-cursor')['data'], 'id');
        $attempt = array_column($this->rows('/admin/demo/scale-cursor?sort=password')['data'], 'id');

        $this->assertSame($baseline, $attempt);
    }

    public function test_the_cursor_page_still_respects_the_search_whitelist(): void
    {
        $rows = $this->rows('/admin/demo/scale-cursor?search=scale7@example.test');

        $this->assertCount(1, $rows['data']);
        $this->assertSame('scale7@example.test', $rows['data'][0]['email']);
    }
}
