<?php

namespace Tests\Feature\Modules;

use App\Models\Article;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Restore / force-delete are first-class row actions now, so the single-record
 * endpoints must enforce the ability in the controller as well as the route.
 */
class SoftDeleteActionsTest extends TestCase
{
    private function actingAsUserWith(array $permissions): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        return $user;
    }

    public function test_restore_requires_edit_permission(): void
    {
        $user = $this->actingAsUserWith(['articles.view']);
        $article = Article::factory()->create(['created_by' => $user->id]);
        $article->delete();

        $this->post(route('admin.articles.restore', $article->id))->assertForbidden();

        $this->assertSoftDeleted('articles', ['id' => $article->id]);
    }

    public function test_editor_can_restore(): void
    {
        $user = $this->actingAsUserWith(['articles.view', 'articles.edit']);
        $article = Article::factory()->create(['created_by' => $user->id]);
        $article->delete();

        $this->post(route('admin.articles.restore', $article->id))->assertRedirect();

        $this->assertNotSoftDeleted('articles', ['id' => $article->id]);
    }

    public function test_force_delete_requires_delete_permission(): void
    {
        $user = $this->actingAsUserWith(['articles.view', 'articles.edit']);
        $article = Article::factory()->create(['created_by' => $user->id]);
        $article->delete();

        $this->delete(route('admin.articles.force-delete', $article->id))->assertForbidden();

        $this->assertNotNull(Article::withoutGlobalScopes()->find($article->id));
    }

    public function test_force_delete_is_ownership_scoped(): void
    {
        $owner = $this->makeUser();
        $article = Article::factory()->create(['created_by' => $owner->id]);
        $article->delete();

        $this->actingAsUserWith(['articles.view', 'articles.edit', 'articles.delete']);

        $this->delete(route('admin.articles.force-delete', $article->id))->assertNotFound();

        $this->assertNotNull(Article::withoutGlobalScopes()->find($article->id));
    }

    public function test_user_restore_cannot_touch_a_super_admin(): void
    {
        $this->actingAsUserWith(['users.view', 'users.edit']);

        $superAdmin = $this->makeUser();
        $superAdmin->assignRole('super-admin');
        $superAdmin->delete();

        $this->post(route('admin.users.restore', $superAdmin->id))->assertForbidden();

        $this->assertSoftDeleted('users', ['id' => $superAdmin->id]);
    }
}
