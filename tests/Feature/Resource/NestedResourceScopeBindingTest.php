<?php

namespace Tests\Feature\Resource;

use App\Models\MyraCourse;
use App\Models\MyraLesson;
use App\Models\User;
use App\Support\Myra;
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
            ['labelKey' => 'clusters.learning.courses.label', 'label' => null, 'href' => Myra::adminPath('learning/courses')],
            ['labelKey' => null, 'label' => 'First', 'href' => null],
            ['labelKey' => 'clusters.learning.lessons.label', 'label' => null, 'href' => null],
        ], $props['crumbs']);

        $this->assertSame(Myra::adminPath("learning/courses/{$course->id}/lessons"), $props['storeUrl']);
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
        $owner = $this->demoUser();
        $this->actingAs($owner);
        $foreignCourse = MyraCourse::query()->create(['title' => 'Not yours']);

        // A DIFFERENT holder of the same ability: the ability is not the thing
        // being tested here, ownership is.
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
        // The parent must be resolvable BY THE ACTOR. SubstituteBindings runs in
        // the web group, before any controller code, and the 'owned' scope turns
        // a parent the actor does not own into a 404 — which would hide the
        // ability check instead of proving it. So the actor owns this course.
        $plain = $this->makeUser();
        $this->actingAs($plain);

        $course = MyraCourse::query()->create(['title' => 'First']);

        $this->get(route('admin.learning.courses.lessons.index', $course))->assertForbidden();
        $this->post(route('admin.learning.courses.lessons.store', $course), ['title' => 'Nope'])->assertForbidden();

        // Positive control: same actor, same course, ability granted. If these
        // pass, the 403s above came from the ability gate and nothing else.
        $plain->givePermissionTo('demo.view');
        $this->actingAs($plain->fresh());

        $this->get(route('admin.learning.courses.lessons.index', $course))->assertOk();
        $this->post(route('admin.learning.courses.lessons.store', $course), ['title' => 'Allowed'])->assertRedirect();
    }

    public function test_a_blank_position_falls_back_to_the_column_default(): void
    {
        $this->actingAs($this->demoUser());

        $course = MyraCourse::query()->create(['title' => 'First']);

        // The Vue form initialises position: '' and ConvertEmptyStringsToNull
        // makes that a null; the column is NOT NULL, so writing it would 500.
        $this->post(route('admin.learning.courses.lessons.store', $course), [
            'title' => 'No position',
            'position' => '',
        ])->assertRedirect();

        $this->assertSame(0, MyraLesson::query()->where('title', 'No position')->firstOrFail()->position);
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
