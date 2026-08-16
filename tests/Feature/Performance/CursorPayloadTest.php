<?php

namespace Tests\Feature\Performance;

use App\Admin\Testing\InteractsWithMyra;
use Tests\TestCase;

/**
 * REAL PAYLOAD. The JSON the vitest specs mount is the JSON this endpoint just
 * produced — regenerate with MYRA_WRITE_FIXTURES=1 when the shape moves.
 */
class CursorPayloadTest extends TestCase
{
    use InteractsWithMyra, ScaleFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pinRootUrl();
        $this->actingAsWithPermissions(['demo.view']);
        $this->seedScaleRows(12);
    }

    public function test_cursor_payload_matches_the_committed_fixture(): void
    {
        $response = $this->get('/admin/demo/scale-cursor?per_page=5');
        $response->assertSuccessful();

        $this->syncFixture(
            'tests/js/fixtures/cursor-page.json',
            $response->viewData('page')['props']['rows'],
        );
    }

    public function test_length_aware_payload_matches_the_committed_fixture(): void
    {
        $response = $this->get('/admin/demo/scale?per_page=5');
        $response->assertSuccessful();

        $this->syncFixture(
            'tests/js/fixtures/length-aware-page.json',
            $response->viewData('page')['props']['rows'],
        );
    }
}
