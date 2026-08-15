<?php

namespace Tests\Feature;

use App\Admin\Search\GlobalSearch;
use App\Admin\Search\MissingSearchScopeException;
use App\Admin\Search\SearchSource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Tests\TestCase;

class GlobalSearchTest extends TestCase
{
    private function registry(): GlobalSearch
    {
        $search = app(GlobalSearch::class);
        $search->flush();

        return $search;
    }

    private function userSource(bool $contains = false): SearchSource
    {
        $source = SearchSource::make('users')
            ->model(User::class)
            ->permission('users.view')
            ->attributes(['name' => 3.0, 'email' => 2.0])
            ->scope(fn (Builder $q, $actor) => $q->when(
                ! ($actor?->hasRole('super-admin') ?? false),
                fn ($q) => $q->where('created_by', $actor?->getAuthIdentifier()),
            ))
            ->titleUsing(fn (User $u) => (string) $u->name)
            ->descriptionUsing(fn (User $u) => (string) $u->email)
            ->urlUsing(fn (User $u) => route('admin.users.edit', $u))
            ->limit(8);

        return $contains ? $source->contains() : $source;
    }

    public function test_an_exact_match_outranks_a_lower_id_prefix_match(): void
    {
        $actor = $this->actingAsSuperAdmin(['name' => 'Root Operator']);

        $prefix = User::factory()->create(['name' => 'Alice Anderson']);   // lower id
        $exact = User::factory()->create(['name' => 'Alice']);             // higher id

        $search = $this->registry();
        $search->register($this->userSource());

        $items = $search->search('Alice', $actor)[0]['items'];

        $this->assertSame($exact->id, $items[0]['id']);
        $this->assertSame($prefix->id, $items[1]['id']);
        $this->assertGreaterThan($items[1]['score'], $items[0]['score']);
    }

    public function test_a_user_without_the_permission_gets_no_users_group(): void
    {
        $actor = $this->actingAsUser(['name' => 'Root Operator']);
        User::factory()->create(['name' => 'Alice Visible']);

        $search = $this->registry();
        $search->register($this->userSource());

        $this->assertSame([], $search->search('Alice', $actor));
    }

    public function test_results_are_ownership_scoped_for_a_non_super_admin(): void
    {
        $actor = $this->actingAsAdmin(['name' => 'Root Operator']);

        $mine = User::factory()->create(['name' => 'Alice Mine']);
        $mine->forceFill(['created_by' => $actor->id])->save();

        $theirs = User::factory()->create(['name' => 'Alice Theirs']);

        $search = $this->registry();
        $search->register($this->userSource());

        $ids = collect($search->search('Alice', $actor)[0]['items'])->pluck('id')->all();

        $this->assertSame([$mine->id], $ids);
        $this->assertNotContains($theirs->id, $ids);
    }

    public function test_a_wildcard_term_is_escaped_and_returns_a_bounded_set(): void
    {
        $actor = $this->actingAsSuperAdmin(['name' => 'Root Operator']);
        User::factory()->count(5)->create();

        $search = $this->registry();
        $search->register($this->userSource(contains: true));

        $this->assertSame([], $search->search('%', $actor));
    }

    public function test_total_results_never_exceed_the_configured_cap(): void
    {
        config(['myra.search.max_results' => 3]);

        $actor = $this->actingAsSuperAdmin(['name' => 'Root Operator']);
        User::factory()->count(10)->create(['name' => 'Alice']);

        $search = $this->registry();
        $search->register(
            $this->userSource(),
            SearchSource::make('users_b')
                ->model(User::class)
                ->attributes(['name' => 1.0])
                ->scope(fn (Builder $q) => $q)
                ->titleUsing(fn (User $u) => (string) $u->name)
                ->urlUsing(fn (User $u) => route('admin.users.edit', $u))
                ->limit(8),
        );

        $total = collect($search->search('Alice', $actor))->sum(fn ($g) => count($g['items']));

        $this->assertLessThanOrEqual(3, $total);
    }

    public function test_a_source_on_an_owned_model_without_a_scope_throws_outside_production(): void
    {
        $search = $this->registry();

        $this->expectException(MissingSearchScopeException::class);

        $search->register(
            SearchSource::make('unscoped')
                ->model(User::class)
                ->attributes(['name' => 1.0])
                ->titleUsing(fn (User $u) => (string) $u->name)
                ->urlUsing(fn () => '/'),
        );
    }

    public function test_match_offsets_index_into_the_returned_title_for_a_multibyte_term(): void
    {
        $actor = $this->actingAsSuperAdmin(['name' => 'Root Operator']);
        User::factory()->create(['name' => 'Zhang 北京 Li']);

        $search = $this->registry();
        $search->register($this->userSource(contains: true));

        $item = $search->search('北京', $actor)[0]['items'][0];
        $match = collect($item['matches'])->firstWhere('field', 'title');

        $this->assertSame(6, $match['start']);
        $this->assertSame(2, $match['length']);
        $this->assertSame('北京', mb_substr($item['title'], $match['start'], $match['length']));
    }

    public function test_the_endpoint_requires_two_characters(): void
    {
        $this->actingAsSuperAdmin(['name' => 'Root Operator']);

        $this->getJson(route('admin.search', ['q' => 'a']))
            ->assertOk()
            ->assertExactJson(['results' => []]);
    }

    public function test_the_endpoint_returns_grouped_results(): void
    {
        $this->actingAsSuperAdmin(['name' => 'Root Operator']);
        User::factory()->create(['name' => 'Alice Zephyr']);

        $response = $this->getJson(route('admin.search', ['q' => 'Alice']))->assertOk();

        $groups = $response->json('results');
        $this->assertNotEmpty($groups);
        $this->assertSame('search.groups.users', $groups[0]['labelKey']);
        $this->assertArrayHasKey('matches', $groups[0]['items'][0]);
        $this->assertArrayHasKey('score', $groups[0]['items'][0]);
    }

    public function test_a_user_without_search_permission_is_blocked(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.search', ['q' => 'Alice']))->assertForbidden();
    }
}
