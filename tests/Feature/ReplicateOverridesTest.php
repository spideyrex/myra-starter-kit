<?php

namespace Tests\Feature;

use App\Models\Article;
use Tests\TestCase;

/**
 * ReplicateAction.schema() posts the modal's values under `overrides` alongside
 * the action's own except/only/relations/suffix config. Before the fix the modal
 * posted bare form values and every edit was discarded.
 */
class ReplicateOverridesTest extends TestCase
{
    public function test_modal_values_arriving_as_overrides_are_applied(): void
    {
        $this->actingAsSuperAdmin();
        $article = Article::factory()->create(['title' => 'Original', 'status' => 'draft']);

        $this->post(route('admin.articles.replicate', $article->id), [
            'except' => [],
            'only' => null,
            'relations' => [],
            'suffix' => null,
            'overrides' => ['title' => 'Edited in the modal', 'status' => 'published'],
            'redirect_to' => null,
        ])->assertRedirect();

        $replica = Article::withoutGlobalScopes()->where('id', '!=', $article->id)->firstOrFail();

        $this->assertSame('Edited in the modal', $replica->title);
        $this->assertSame('published', $replica->status);
    }

    public function test_an_override_outside_fillable_is_dropped(): void
    {
        $this->actingAsSuperAdmin();
        $article = Article::factory()->create(['title' => 'Original']);

        $this->post(route('admin.articles.replicate', $article->id), [
            'overrides' => ['title' => 'Edited', 'not_fillable' => 'x', 'id' => 4242],
        ])->assertRedirect();

        $replica = Article::withoutGlobalScopes()->where('id', '!=', $article->id)->firstOrFail();

        $this->assertSame('Edited', $replica->title);
        $this->assertNotSame(4242, $replica->id);
        $this->assertArrayNotHasKey('not_fillable', $replica->getAttributes());
    }

    public function test_except_and_relations_ride_alongside_overrides(): void
    {
        $this->actingAsSuperAdmin();
        $article = Article::factory()->create(['title' => 'Original', 'excerpt' => 'Keep me out']);

        $this->post(route('admin.articles.replicate', $article->id), [
            'except' => ['excerpt'],
            'relations' => ['category'],
            'overrides' => ['title' => 'Edited'],
        ])->assertRedirect();

        $replica = Article::withoutGlobalScopes()->where('id', '!=', $article->id)->firstOrFail();

        $this->assertSame('Edited', $replica->title);
        $this->assertNull($replica->excerpt);
    }

    public function test_a_suffix_posted_with_overrides_still_wins_for_its_field(): void
    {
        $this->actingAsSuperAdmin();
        $article = Article::factory()->create(['title' => 'Release checklist']);

        $this->post(route('admin.articles.replicate', $article->id), [
            'except' => [],
            'relations' => [],
            'overrides' => ['status' => 'draft'],
            'suffix' => ['field' => 'title', 'template' => ':value (copy)'],
        ])->assertRedirect();

        $replica = Article::withoutGlobalScopes()->where('id', '!=', $article->id)->firstOrFail();

        $this->assertSame('Release checklist (copy)', $replica->title);
        $this->assertSame('draft', $replica->status);
    }
}
