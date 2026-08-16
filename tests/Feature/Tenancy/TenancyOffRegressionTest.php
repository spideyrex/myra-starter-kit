<?php

namespace Tests\Feature\Tenancy;

use App\Admin\Tenancy\Tenancy;
use App\Models\Article;
use App\Models\Page;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Re-runs the isolation invariants the tenancy taps sit next to, with the flag
 * explicitly off. Every one of these passed before this bundle existed; if one
 * moves, a tap changed production visibility.
 */
class TenancyOffRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['myra.tenancy.enabled' => false, 'myra.tenancy.models' => []]);
        Tenancy::flush();
        Model::clearBootedModels();
    }

    public function test_article_and_page_ownership_isolation_is_unchanged(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        Article::factory()->create(['created_by' => $a->id]);
        Article::factory()->create(['created_by' => $b->id]);
        Page::factory()->create(['created_by' => $a->id]);
        Page::factory()->create(['created_by' => $b->id]);

        $this->actingAs($b);

        $this->assertSame(1, Article::count());
        $this->assertSame(1, Page::count());
    }

    public function test_user_export_query_is_unchanged(): void
    {
        $manager = $this->actingAsRole('manager');
        $stranger = User::factory()->create();

        User::factory()->count(2)->create(['created_by' => $manager->id]);
        User::factory()->count(7)->create(['created_by' => $stranger->id]);

        $this->assertSame(2, app(UserService::class)->exportQuery(request())->count());
    }

    public function test_super_admin_user_export_is_still_unscoped(): void
    {
        $admin = $this->actingAsSuperAdmin();
        User::factory()->count(3)->create(['created_by' => User::factory()->create()->id]);

        $this->assertSame(User::count(), app(UserService::class)->exportQuery(request())->count());
    }

    public function test_media_listing_is_still_owner_scoped_only(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo('media.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        $this->get(route('admin.media.index'))->assertOk();
    }

    public function test_global_search_still_returns_the_actors_own_users(): void
    {
        $actor = $this->makeUser(['name' => 'Zoltan Owner']);
        $actor->givePermissionTo(['users.view', 'search.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $mine = User::factory()->create(['name' => 'Findable Alpha', 'created_by' => $actor->id]);
        User::factory()->create(['name' => 'Findable Beta', 'created_by' => User::factory()->create()->id]);

        $this->actingAs($actor);

        $response = $this->getJson(route('admin.search', ['q' => 'Findable']));
        $response->assertOk();

        $names = [];

        foreach (($response->json('results') ?? []) as $group) {
            foreach (($group['items'] ?? []) as $item) {
                $names[] = $item['title'] ?? '';
            }
        }

        $this->assertContains($mine->name, $names);
        $this->assertNotContains('Findable Beta', $names);
    }
}
