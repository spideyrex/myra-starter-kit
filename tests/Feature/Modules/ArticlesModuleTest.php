<?php

namespace Tests\Feature\Modules;

use App\Models\Article;
use Tests\TestCase;

class ArticlesModuleTest extends TestCase
{
    public function test_index_requires_permission(): void
    {
        $this->actingAsUser();
        $this->get(route('admin.articles.index'))->assertForbidden();
    }

    public function test_super_admin_can_view_index(): void
    {
        $this->actingAsSuperAdmin();
        $this->get(route('admin.articles.index'))->assertOk();
    }

    public function test_can_create_article(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.articles.store'), [
            'title' => 'Hello Malaysia',
            'slug' => 'hello-malaysia',
            'body_html' => '<p>Selamat datang</p>',
            'status' => 'draft',
            'is_public' => true,
        ])->assertRedirect(route('admin.articles.index'));

        $this->assertDatabaseHas('articles', ['slug' => 'hello-malaysia']);
    }

    public function test_article_body_is_sanitized_on_store(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.articles.store'), [
            'title' => 'XSS Attempt',
            'slug' => 'xss-attempt',
            'body_html' => '<p>ok</p><script>alert(1)</script>',
            'status' => 'draft',
            'is_public' => true,
        ]);

        $article = Article::withoutGlobalScopes()->where('slug', 'xss-attempt')->first();
        $this->assertStringNotContainsString('<script', $article->body_html);
    }

    public function test_can_delete_article(): void
    {
        $this->actingAsSuperAdmin();
        $article = Article::factory()->create();

        $this->delete(route('admin.articles.destroy', $article))->assertRedirect();

        $this->assertSoftDeleted('articles', ['id' => $article->id]);
    }
}
