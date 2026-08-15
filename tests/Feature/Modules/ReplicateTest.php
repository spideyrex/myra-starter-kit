<?php

namespace Tests\Feature\Modules;

use App\Models\Article;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReplicateTest extends TestCase
{
    /** Authenticate a role-less user holding exactly the given permissions. */
    private function actingAsUserWith(array $permissions): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'except' => [],
            'relations' => [],
            'overrides' => [],
        ], $overrides);
    }

    public function test_replicate_requires_create_permission(): void
    {
        $user = $this->actingAsUserWith(['articles.view', 'articles.edit']);
        $article = Article::factory()->create(['created_by' => $user->id]);

        $this->post(route('admin.articles.replicate', $article->id), $this->payload())
            ->assertForbidden();

        $this->assertSame(1, Article::withoutGlobalScopes()->count());
    }

    public function test_replicate_creates_a_copy(): void
    {
        $this->actingAsSuperAdmin();
        $article = Article::factory()->create(['title' => 'Launch notes']);

        $this->post(route('admin.articles.replicate', $article->id), $this->payload())
            ->assertRedirect();

        $this->assertSame(2, Article::withoutGlobalScopes()->count());
    }

    public function test_replicate_regenerates_a_unique_slug_when_excluded(): void
    {
        $this->actingAsSuperAdmin();
        $article = Article::factory()->create();

        $this->post(route('admin.articles.replicate', $article->id), $this->payload(['except' => ['slug']]));

        $replica = Article::withoutGlobalScopes()->where('id', '!=', $article->id)->firstOrFail();

        $this->assertNotNull($replica->slug);
        $this->assertNotSame($article->slug, $replica->slug);
    }

    public function test_replicate_ignores_relations_outside_the_model_whitelist(): void
    {
        $this->actingAsSuperAdmin();
        $article = Article::factory()->create();

        // Article declares no $replicable relations, so both names must be
        // dropped rather than resolved against the model.
        $this->post(route('admin.articles.replicate', $article->id), $this->payload([
            'relations' => ['secrets', 'category'],
        ]))->assertRedirect();

        $this->assertSame(2, Article::withoutGlobalScopes()->count());
    }

    public function test_replicate_strips_overrides_that_are_not_fillable(): void
    {
        $this->actingAsSuperAdmin();
        $article = Article::factory()->create();

        $this->post(route('admin.articles.replicate', $article->id), $this->payload([
            'overrides' => ['id' => 999, 'status' => 'archived'],
        ]));

        $replica = Article::withoutGlobalScopes()->where('id', '!=', $article->id)->firstOrFail();

        $this->assertNotSame(999, $replica->id);
        $this->assertSame('archived', $replica->status);
    }

    public function test_replicate_suffix_increments_on_repeat(): void
    {
        $this->actingAsSuperAdmin();
        $article = Article::factory()->create(['title' => 'Release checklist']);
        $suffix = ['field' => 'title', 'template' => ':value (copy)'];

        $this->post(route('admin.articles.replicate', $article->id), $this->payload(['suffix' => $suffix]));
        $this->post(route('admin.articles.replicate', $article->id), $this->payload(['suffix' => $suffix]));

        $titles = Article::withoutGlobalScopes()->pluck('title')->all();

        $this->assertContains('Release checklist (copy)', $titles);
        $this->assertContains('Release checklist (copy 2)', $titles);
    }

    public function test_replicate_is_ownership_scoped(): void
    {
        $owner = $this->makeUser();
        $article = Article::factory()->create(['created_by' => $owner->id]);

        $this->actingAsUserWith(['articles.view', 'articles.create']);

        // The owned global scope hides the record entirely.
        $this->post(route('admin.articles.replicate', $article->id), $this->payload())
            ->assertNotFound();

        $this->assertSame(1, Article::withoutGlobalScopes()->count());
    }
}
