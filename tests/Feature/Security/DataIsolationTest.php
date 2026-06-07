<?php

namespace Tests\Feature\Security;

use App\Models\Article;
use App\Models\Page;
use App\Models\User;
use Tests\TestCase;

class DataIsolationTest extends TestCase
{
    public function test_non_super_admin_only_sees_own_articles(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        Article::factory()->create(['created_by' => $a->id]);
        Article::factory()->create(['created_by' => $b->id]);

        $this->actingAs($b);

        $this->assertSame(1, Article::count());
        $this->assertTrue(Article::get()->every(fn ($x) => $x->created_by === $b->id));
    }

    public function test_super_admin_sees_all_articles(): void
    {
        Article::factory()->create(['created_by' => User::factory()->create()->id]);
        Article::factory()->create(['created_by' => User::factory()->create()->id]);

        $this->actingAsSuperAdmin();

        $this->assertSame(2, Article::count());
    }

    public function test_creating_stamps_current_user_as_owner(): void
    {
        $b = $this->actingAsUser();
        $article = Article::factory()->create();

        $this->assertSame($b->id, $article->created_by);
    }

    public function test_pages_are_isolated_per_owner(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();
        Page::factory()->create(['created_by' => $a->id]);
        Page::factory()->create(['created_by' => $b->id]);

        $this->actingAs($b);

        $this->assertSame(1, Page::count());
    }

    public function test_public_blog_shows_published_articles_to_any_user(): void
    {
        $owner = User::factory()->create();
        Article::factory()->published()->create([
            'created_by' => $owner->id,
            'is_public' => true,
            'title' => 'Nasi Lemak Guide',
        ]);

        // A different non-super user must still see the public post (scope exempt).
        $this->actingAs(User::factory()->create());

        $this->get(route('articles.index'))->assertOk()->assertSee('Nasi Lemak Guide');
    }
}
