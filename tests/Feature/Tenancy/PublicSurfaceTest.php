<?php

namespace Tests\Feature\Tenancy;

use App\Admin\Tenancy\Tenancy;
use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/**
 * The public, unauthenticated surface. A guest resolves no tenant and the
 * tenant predicate fails closed, so listing Article/Page/Category for scoping
 * would blank the public site. Tenancy::publicQuery() lifts the scope there
 * unless the operator opts in with myra.tenancy.scope_public.
 */
class PublicSurfaceTest extends TestCase
{
    private Team $teamA;

    private Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teamA = Team::create(['name' => 'Alpha']);
        $this->teamB = Team::create(['name' => 'Beta']);
    }

    protected function tearDown(): void
    {
        config(['myra.tenancy.enabled' => false, 'myra.tenancy.models' => [], 'myra.tenancy.scope_public' => false]);
        Tenancy::flush();
        Model::clearBootedModels();

        parent::tearDown();
    }

    private function enable(array $overrides = []): void
    {
        config(array_merge([
            'myra.tenancy.enabled' => true,
            'myra.tenancy.models' => [Article::class, Page::class, Category::class],
            'myra.tenancy.scope_public' => false,
        ], $overrides));

        Tenancy::flush();
        Model::clearBootedModels();
    }

    private function seedTwoTenants(): User
    {
        $owner = User::factory()->create();

        Tenancy::for($this->teamA, fn () => Article::factory()->published()->create([
            'created_by' => $owner->id, 'is_public' => true, 'title' => 'Alpha Public Post',
        ]));

        Tenancy::for($this->teamB, fn () => Article::factory()->published()->create([
            'created_by' => $owner->id, 'is_public' => true, 'title' => 'Beta Public Post',
        ]));

        return $owner;
    }

    public function test_a_guest_still_sees_every_public_article_when_tenancy_is_on(): void
    {
        $this->enable();
        $this->seedTwoTenants();

        Tenancy::flush();
        $this->assertNull(Tenancy::id(), 'A guest must resolve no tenant — otherwise this proves nothing.');

        $this->get(route('articles.index'))
            ->assertOk()
            ->assertSee('Alpha Public Post')
            ->assertSee('Beta Public Post');
    }

    public function test_a_guest_can_still_open_a_cross_tenant_public_article_and_page(): void
    {
        $this->enable();
        $owner = User::factory()->create();

        $article = Tenancy::for($this->teamB, fn () => Article::factory()->published()->create([
            'created_by' => $owner->id, 'is_public' => true, 'title' => 'Beta Detail',
        ]));

        $page = Tenancy::for($this->teamB, fn () => Page::factory()->published()->create([
            'created_by' => $owner->id, 'is_public' => true, 'title' => 'Beta Page',
        ]));

        $this->get(route('articles.show', $article->slug))->assertOk();
        $this->get(route('pages.show', $page->slug))->assertOk();
    }

    /** The lift is a decision, not a blanket bypass: an operator can turn it off. */
    public function test_scope_public_opts_the_public_surface_back_into_scoping(): void
    {
        $this->enable(['myra.tenancy.scope_public' => true]);
        $this->seedTwoTenants();

        $this->get(route('articles.index'))
            ->assertOk()
            ->assertDontSee('Alpha Public Post')
            ->assertDontSee('Beta Public Post');
    }

    public function test_public_query_is_a_literal_no_op_while_tenancy_is_disabled(): void
    {
        config(['myra.tenancy.enabled' => false]);
        Tenancy::flush();
        Model::clearBootedModels();

        $before = Article::query();
        $after = Tenancy::publicQuery(Article::query());

        $this->assertSame($before->toBase()->toSql(), $after->toBase()->toSql());
        $this->assertSame([], $after->getQuery()->getBindings());
        $this->assertSame([], $after->removedScopes());
    }
}
