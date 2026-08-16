<?php

namespace Tests\Feature\Resource;

use App\Models\MyraCourse;
use App\Models\User;
use Tests\TestCase;

class ClusterDemoPagesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    private function demoUser(): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo('demo.view');

        return $user->fresh();
    }

    public function test_the_courses_page_is_never_an_empty_screen(): void
    {
        $this->actingAs($this->demoUser());

        $response = $this->get(route('admin.learning.courses.index'));
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertCount(2, $props['courses']['data']);
        $this->assertEquals(3, $props['courses']['data'][0]['lessons_count']);
    }

    public function test_seeding_runs_once_per_owner(): void
    {
        $this->actingAs($this->demoUser());

        $this->get(route('admin.learning.courses.index'))->assertOk();
        $this->get(route('admin.learning.courses.index'))->assertOk();

        $this->assertSame(2, MyraCourse::query()->count());
    }

    public function test_the_courses_table_declares_a_table_key(): void
    {
        // Without table-key the saved-views menu silently disappears; the
        // controller ships the server half of that contract.
        $this->actingAs($this->demoUser());

        $props = $this->get(route('admin.learning.courses.index'))->viewData('page')['props'];

        $this->assertArrayHasKey('savedViews', $props);
        $this->assertSame([], $props['savedViews']);
    }

    public function test_the_courses_page_requires_the_demo_ability(): void
    {
        $this->actingAs($this->makeUser());

        $this->get(route('admin.learning.courses.index'))->assertForbidden();
    }

    public function test_search_is_escaped_and_scoped(): void
    {
        $this->actingAs($this->demoUser());

        MyraCourse::query()->create(['title' => 'Literal%Match']);
        MyraCourse::query()->create(['title' => 'Plain']);

        $props = $this->get(route('admin.learning.courses.index', ['search' => '%']))
            ->viewData('page')['props'];

        $titles = array_column($props['courses']['data'], 'title');

        $this->assertSame(['Literal%Match'], $titles);
    }
}
