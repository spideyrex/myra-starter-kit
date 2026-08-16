<?php

namespace Tests\Feature\Resource;

use App\Models\MyraCourse;
use App\Models\MyraLesson;
use App\Models\User;
use Tests\TestCase;

class NestedResourceScopeBindingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The assertions read Inertia page props out of the rendered root view.
        $this->withoutVite();
    }

    private function demoUser(): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo('demo.view');

        return $user->fresh();
    }

    public function test_index_lists_only_the_lessons_of_the_course_in_the_url(): void
    {
        $this->actingAs($this->demoUser());

        $first = MyraCourse::query()->create(['title' => 'First']);
        $second = MyraCourse::query()->create(['title' => 'Second']);

        $first->lessons()->create(['title' => 'Alpha', 'position' => 1]);
        $second->lessons()->create(['title' => 'Beta', 'position' => 1]);

        $response = $this->get(route('admin.learning.courses.lessons.index', $first));
        $response->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertSame(['Alpha'], array_column($props['lessons']['data'], 'title'));
        $this->assertSame('First', $props['parent']['title']);
    }

    public function test_the_breadcrumb_chain_travels_as_i18n_keys(): void
    {
        $this->actingAs($this->demoUser());

        $course = MyraCourse::query()->create(['title' => 'First']);

        $props = $this->get(route('admin.learning.courses.lessons.index', $course))
            ->viewData('page')['props'];

        $this->assertSame([
            ['labelKey' => 'clusters.learning.courses.label', 'label' => null, 'href' => '/admin/learning/courses'],
            ['labelKey' => null, 'label' => 'First', 'href' => null],
            ['labelKey' => 'clusters.learning.lessons.label', 'label' => null, 'href' => null],
        ], $props['crumbs']);

        $this->assertSame("/admin/learning/courses/{$course->id}/lessons", $props['storeUrl']);
    }

    public function test_a_child_of_another_parent_is_a_404(): void
    {
        $this->actingAs($this->demoUser());

        $first = MyraCourse::query()->create(['title' => 'First']);
        $second = MyraCourse::query()->create(['title' => 'Second']);

        $foreign = $second->lessons()->create(['title' => 'Beta', 'position' => 1]);

        // scopeBindings(): 'Beta' does not belong to 'First', so it is a 404 and
        // never a delete against someone else's child.
        $this->delete(route('admin.learning.courses.lessons.destroy', [$first, $foreign]))
            ->assertNotFound();

        $this->assertDatabaseHas('myra_lessons', ['id' => $foreign->id]);
    }

    public function test_a_child_of_the_right_parent_deletes(): void
    {
        $this->actingAs($this->demoUser());

        $course = MyraCourse::query()->create(['title' => 'First']);
        $lesson = $course->lessons()->create(['title' => 'Alpha', 'position' => 1]);

        $this->delete(route('admin.learning.courses.lessons.destroy', [$course, $lesson]))
            ->assertRedirect();

        $this->assertDatabaseMissing('myra_lessons', ['id' => $lesson->id]);
    }

    public function test_a_parent_the_actor_does_not_own_is_a_404_not_a_403(): void
    {
        $this->actingAs($this->demoUser());
        $foreignCourse = MyraCourse::query()->create(['title' => 'Not yours']);

        $this->actingAs($this->demoUser());

        $this->get(route('admin.learning.courses.lessons.index', $foreignCourse))
            ->assertNotFound();
    }

    public function test_store_takes_the_parent_from_the_url_not_the_body(): void
    {
        $this->actingAs($this->demoUser());

        $first = MyraCourse::query()->create(['title' => 'First']);
        $second = MyraCourse::query()->create(['title' => 'Second']);

        $this->post(route('admin.learning.courses.lessons.store', $first), [
            'title' => 'Injected',
            'position' => 2,
            'course_id' => $second->id,
        ])->assertRedirect();

        $lesson = MyraLesson::query()->where('title', 'Injected')->firstOrFail();

        $this->assertSame($first->id, $lesson->course_id);
    }

    public function test_store_validates(): void
    {
        $this->actingAs($this->demoUser());

        $course = MyraCourse::query()->create(['title' => 'First']);

        $this->post(route('admin.learning.courses.lessons.store', $course), ['title' => ''])
            ->assertSessionHasErrors('title');
    }

    public function test_the_routes_require_the_demo_ability(): void
    {
        $this->actingAs($this->demoUser());
        $course = MyraCourse::query()->create(['title' => 'First']);

        $this->actingAs($this->makeUser());

        $this->get(route('admin.learning.courses.lessons.index', $course))->assertForbidden();
        $this->post(route('admin.learning.courses.lessons.store', $course), ['title' => 'Nope'])->assertForbidden();
    }

    public function test_the_sort_whitelist_ignores_an_unlisted_column(): void
    {
        $this->actingAs($this->demoUser());

        $course = MyraCourse::query()->create(['title' => 'First']);
        $course->lessons()->create(['title' => 'Alpha', 'position' => 2]);
        $course->lessons()->create(['title' => 'Beta', 'position' => 1]);

        $response = $this->get(route('admin.learning.courses.lessons.index', [
            $course, 'sort' => 'created_by', 'direction' => 'asc',
        ]));

        $response->assertOk();

        // Falls back to the declared default (position asc), never to created_by.
        $this->assertSame(
            ['Beta', 'Alpha'],
            array_column($response->viewData('page')['props']['lessons']['data'], 'title'),
        );
    }
}
