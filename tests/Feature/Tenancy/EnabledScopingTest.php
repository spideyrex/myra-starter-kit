<?php

namespace Tests\Feature\Tenancy;

use App\Admin\Tenancy\Tenancy;
use App\Models\Article;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EnabledScopingTest extends TestCase
{
    private Team $teamA;

    private Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teamA = Team::create(['name' => 'Alpha']);
        $this->teamB = Team::create(['name' => 'Beta']);

        $this->enableTenancy();
    }

    protected function tearDown(): void
    {
        config(['myra.tenancy.enabled' => false, 'myra.tenancy.models' => []]);
        Tenancy::flush();
        Model::clearBootedModels();

        parent::tearDown();
    }

    private function enableTenancy(array $overrides = []): void
    {
        config(array_merge([
            'myra.tenancy.enabled' => true,
            'myra.tenancy.models' => [Article::class],
            'myra.tenancy.null_rows' => 'strict',
            'myra.tenancy.super_admin_bypass' => true,
        ], $overrides));

        Tenancy::flush();
        Model::clearBootedModels();
    }

    /** A non-super member of team A, acting with team A current. */
    private function actingAsMemberOfA(array $permissions = []): User
    {
        $user = $this->makeUser();
        $user->teams()->attach([$this->teamA->id, $this->teamB->id]);
        $user->forceFill(['current_team_id' => $this->teamA->id])->save();

        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        $this->actingAs($user);
        Tenancy::flush();

        return $user;
    }

    public function test_rows_from_another_tenant_are_invisible(): void
    {
        $user = $this->actingAsMemberOfA();

        $mine = Tenancy::for($this->teamA, fn () => Article::factory()->create(['title' => 'Alpha post']));
        $theirs = Tenancy::for($this->teamB, fn () => Article::factory()->create(['title' => 'Beta post']));

        $this->assertSame($this->teamA->id, $mine->team_id);
        $this->assertSame($this->teamB->id, $theirs->team_id);
        $this->assertSame($user->id, $mine->created_by);
        $this->assertSame($user->id, $theirs->created_by);

        $ids = Article::query()->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertSame(2, Article::withoutGlobalScopes()->count());
    }

    public function test_a_cross_tenant_record_404s_through_the_real_controller(): void
    {
        $this->actingAsMemberOfA(['articles.view', 'articles.edit']);

        $mine = Tenancy::for($this->teamA, fn () => Article::factory()->create());
        $theirs = Tenancy::for($this->teamB, fn () => Article::factory()->create());

        $this->get(route('admin.articles.edit', $mine))->assertOk();
        $this->get(route('admin.articles.edit', $theirs))->assertNotFound();
    }

    public function test_creating_through_the_real_controller_stamps_the_tenant(): void
    {
        $user = $this->actingAsMemberOfA(['articles.view', 'articles.create']);

        $this->post(route('admin.articles.store'), [
            'title' => 'Stamped',
            'slug' => 'stamped',
            'body_html' => '<p>hello</p>',
            'status' => 'draft',
            'is_public' => true,
        ])->assertRedirect(route('admin.articles.index'));

        $article = Article::withoutGlobalScopes()->firstOrFail();

        $this->assertSame($this->teamA->id, $article->team_id);
        $this->assertSame($user->id, $article->created_by);
    }

    public function test_the_tenant_column_is_never_mass_assignable(): void
    {
        $this->actingAsMemberOfA(['articles.view', 'articles.create']);

        $this->post(route('admin.articles.store'), [
            'title' => 'Repointed',
            'slug' => 'repointed',
            'body_html' => '<p>hello</p>',
            'status' => 'draft',
            'is_public' => true,
            'team_id' => $this->teamB->id,
        ])->assertRedirect();

        $this->assertSame(
            $this->teamA->id,
            Article::withoutGlobalScopes()->firstOrFail()->team_id,
        );
    }

    public function test_for_scopes_inside_and_restores_after_even_on_exception(): void
    {
        $this->actingAsMemberOfA();

        $this->assertSame($this->teamA->id, Tenancy::id());

        Tenancy::for($this->teamB, function () {
            $this->assertSame($this->teamB->id, Tenancy::id());
        });

        $this->assertSame($this->teamA->id, Tenancy::id());

        try {
            Tenancy::for($this->teamB, function () {
                throw new RuntimeException('boom');
            });
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame($this->teamA->id, Tenancy::id());
    }

    public function test_no_tenant_resolved_returns_zero_rows_not_all_rows(): void
    {
        $orphan = $this->makeUser();
        Tenancy::for($this->teamA, fn () => Article::factory()->create(['created_by' => $orphan->id]));
        Tenancy::for($this->teamB, fn () => Article::factory()->create(['created_by' => $orphan->id]));

        $this->actingAs($orphan);
        Tenancy::flush();

        $this->assertNull(Tenancy::id());
        $this->assertSame(0, Article::query()->count());
        $this->assertSame(2, Article::withoutGlobalScopes()->count());
    }

    public function test_membership_is_revalidated_not_trusted_from_the_column(): void
    {
        $user = $this->actingAsMemberOfA();
        $this->assertSame($this->teamA->id, Tenancy::id());

        $user->teams()->detach($this->teamA->id);
        Tenancy::flush();

        $this->assertNull(Tenancy::id(), 'A stale current_team_id must not survive losing membership.');
    }

    public function test_super_admin_bypass_is_configurable(): void
    {
        $super = $this->makeUser();
        $super->assignRole('super-admin');
        $super->teams()->attach($this->teamA->id);
        $super->forceFill(['current_team_id' => $this->teamA->id])->save();

        Tenancy::for($this->teamA, fn () => Article::factory()->create(['created_by' => $super->id]));
        Tenancy::for($this->teamB, fn () => Article::factory()->create(['created_by' => $super->id]));

        $this->actingAs($super);
        Tenancy::flush();

        $this->assertSame(2, Article::query()->count());

        $this->enableTenancy(['myra.tenancy.super_admin_bypass' => false]);
        $this->actingAs($super);
        Tenancy::flush();

        $this->assertSame(1, Article::query()->count());
    }

    public function test_strict_hides_legacy_null_tenant_rows(): void
    {
        $user = $this->actingAsMemberOfA();

        $legacy = Tenancy::for(null, fn () => Article::factory()->create(['created_by' => $user->id]));
        $this->assertNull($legacy->team_id);

        Tenancy::for($this->teamA, fn () => Article::factory()->create(['created_by' => $user->id]));

        $this->assertSame(1, Article::query()->count());
    }

    public function test_shared_shows_null_rows_and_wraps_the_or_in_a_closure(): void
    {
        $this->enableTenancy(['myra.tenancy.null_rows' => 'shared']);
        $user = $this->actingAsMemberOfA();

        Tenancy::for(null, fn () => Article::factory()->create(['created_by' => $user->id]));
        Tenancy::for($this->teamA, fn () => Article::factory()->create(['created_by' => $user->id]));
        Tenancy::for($this->teamB, fn () => Article::factory()->create(['created_by' => $user->id]));

        $this->assertSame(2, Article::query()->count());

        // An unwrapped orWhereNull would escape every predicate before it.
        $sql = Article::query()->toBase()->toSql();
        $this->assertStringContainsString('and (', $sql, "Tenant OR set is not nested: {$sql}");
    }

    public function test_without_lifts_the_predicate_and_restores_it(): void
    {
        $user = $this->actingAsMemberOfA();

        Tenancy::for($this->teamA, fn () => Article::factory()->create(['created_by' => $user->id]));
        Tenancy::for($this->teamB, fn () => Article::factory()->create(['created_by' => $user->id]));

        $this->assertSame(1, Article::query()->count());
        $this->assertSame(2, Tenancy::without(fn () => Article::query()->count()));
        $this->assertSame(1, Article::query()->count());
    }

    public function test_a_model_carrying_the_trait_is_unscoped_until_it_is_listed(): void
    {
        $this->enableTenancy(['myra.tenancy.models' => []]);
        $user = $this->actingAsMemberOfA();

        Tenancy::for($this->teamA, fn () => Article::factory()->create(['created_by' => $user->id]));
        Tenancy::for($this->teamB, fn () => Article::factory()->create(['created_by' => $user->id]));

        $this->assertArrayNotHasKey('tenant', (new Article)->getGlobalScopes());
        $this->assertSame(2, Article::query()->count());
    }
}
