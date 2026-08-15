<?php

namespace Tests\Feature\Security;

use App\Models\Article;
use App\Models\Page;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The bulk endpoints are gated on `{module}.edit` but accept destructive verbs.
 * Each verb must be authorized on its own ability inside the controller.
 */
class BulkActionPermissionTest extends TestCase
{
    private function actingAsUserWith(array $permissions): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }

    public function test_editor_cannot_force_delete_articles_through_the_bulk_endpoint(): void
    {
        $user = $this->actingAsUserWith(['articles.view', 'articles.edit']);
        $article = Article::factory()->create(['created_by' => $user->id]);
        $article->delete();

        $this->post(route('admin.articles.bulk-action'), [
            'ids' => [$article->id],
            'action' => 'force_delete',
        ])->assertForbidden();

        $this->assertNotNull(Article::withoutGlobalScopes()->find($article->id));
    }

    public function test_editor_cannot_delete_articles_through_the_bulk_endpoint(): void
    {
        $user = $this->actingAsUserWith(['articles.view', 'articles.edit']);
        $article = Article::factory()->create(['created_by' => $user->id]);

        $this->post(route('admin.articles.bulk-action'), [
            'ids' => [$article->id],
            'action' => 'delete',
        ])->assertForbidden();

        $this->assertNotSoftDeleted('articles', ['id' => $article->id]);
    }

    public function test_editor_can_still_publish_through_the_bulk_endpoint(): void
    {
        $user = $this->actingAsUserWith(['articles.view', 'articles.edit']);
        $article = Article::factory()->create(['created_by' => $user->id, 'status' => 'draft']);

        $this->post(route('admin.articles.bulk-action'), [
            'ids' => [$article->id],
            'action' => 'publish',
        ])->assertRedirect();

        $this->assertSame('published', $article->fresh()->status);
    }

    public function test_deleter_can_force_delete_articles(): void
    {
        $user = $this->actingAsUserWith(['articles.view', 'articles.edit', 'articles.delete']);
        $article = Article::factory()->create(['created_by' => $user->id]);
        $article->delete();

        $this->post(route('admin.articles.bulk-action'), [
            'ids' => [$article->id],
            'action' => 'force_delete',
        ])->assertRedirect();

        $this->assertNull(Article::withoutGlobalScopes()->find($article->id));
    }

    public function test_editor_cannot_force_delete_pages_through_the_bulk_endpoint(): void
    {
        $user = $this->actingAsUserWith(['pages.view', 'pages.edit']);
        $page = Page::factory()->create(['created_by' => $user->id]);
        $page->delete();

        $this->post(route('admin.pages.bulk-action'), [
            'ids' => [$page->id],
            'action' => 'force_delete',
        ])->assertForbidden();

        $this->assertNotNull(Page::withoutGlobalScopes()->find($page->id));
    }

    public function test_editor_cannot_force_delete_users_through_the_bulk_endpoint(): void
    {
        $this->actingAsUserWith(['users.view', 'users.edit']);
        $target = $this->makeUser();
        $target->delete();

        $this->post(route('admin.users.bulk-action'), [
            'ids' => [$target->id],
            'action' => 'force_delete',
        ])->assertForbidden();

        $this->assertNotNull(User::withTrashed()->find($target->id));
    }

    public function test_bulk_user_delete_cannot_touch_a_super_admin(): void
    {
        $this->actingAsUserWith(['users.view', 'users.edit', 'users.delete']);

        $superAdmin = $this->makeUser();
        $superAdmin->assignRole('super-admin');

        $this->post(route('admin.users.bulk-action'), [
            'ids' => [$superAdmin->id],
            'action' => 'delete',
        ])->assertForbidden();

        $this->assertNotSoftDeleted('users', ['id' => $superAdmin->id]);
    }
}
