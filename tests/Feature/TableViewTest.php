<?php

namespace Tests\Feature;

use App\Models\TableView;
use App\Models\Team;
use App\Models\User;
use Tests\TestCase;

class TableViewTest extends TestCase
{
    private const KEY = 'admin.users.index';

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'search' => 'ali',
            'sort' => 'name',
            'direction' => 'desc',
            'per_page' => 25,
            'filters' => ['status' => 'active'],
            'dateRanges' => ['created' => ['from' => '2026-01-01', 'to' => '2026-02-01']],
            'columns' => ['email' => false],
            'columnOrder' => ['id', 'name', 'email'],
        ], $overrides);
    }

    /** @return array<string, mixed> */
    private function body(array $overrides = []): array
    {
        return array_merge([
            'table_key' => self::KEY,
            'name' => 'My view',
            'visibility' => 'private',
            'is_default' => false,
            'payload' => $this->payload(),
        ], $overrides);
    }

    private function tree(int $depth = 1, int $rules = 1): array
    {
        $node = [
            'conjunction' => 'and',
            'rules' => array_map(fn ($i) => [
                'field' => 'name',
                'operator' => 'contains',
                'value' => 'x'.$i,
            ], range(1, max($rules, 1))),
            'groups' => [],
        ];

        for ($i = 1; $i < $depth; $i++) {
            $node = ['conjunction' => 'or', 'rules' => [], 'groups' => [$node]];
        }

        return $node;
    }

    public function test_store_persists_a_canonical_payload(): void
    {
        $user = $this->actingAsUser();

        $this->post(route('admin.table-views.store'), $this->body())->assertRedirect();

        $view = TableView::firstOrFail();
        $this->assertSame($user->id, $view->user_id);
        $this->assertSame(self::KEY, $view->table_key);
        $this->assertSame('private', $view->visibility);
        $this->assertSame(26, strlen((string) $view->slug));
        $this->assertEquals($this->payload(), $view->payload);
    }

    public function test_an_unlisted_top_level_payload_key_is_rejected(): void
    {
        $this->actingAsUser();

        $this->postJson(route('admin.table-views.store'), $this->body([
            'payload' => $this->payload(['rogue' => 'value']),
        ]))->assertStatus(422);

        $this->assertDatabaseCount('table_views', 0);
    }

    public function test_out_of_range_and_malformed_scalars_are_rejected(): void
    {
        $this->actingAsUser();

        $this->postJson(route('admin.table-views.store'), $this->body([
            'payload' => $this->payload(['per_page' => 500]),
        ]))->assertStatus(422);

        $this->postJson(route('admin.table-views.store'), $this->body([
            'payload' => $this->payload(['direction' => 'sideways']),
        ]))->assertStatus(422);

        $this->postJson(route('admin.table-views.store'), $this->body([
            'payload' => $this->payload(['sort' => 'name); DROP']),
        ]))->assertStatus(422);

        $this->assertDatabaseCount('table_views', 0);
    }

    public function test_a_query_tree_deeper_than_the_cap_is_rejected_and_not_truncated(): void
    {
        $this->actingAsUser();

        $this->postJson(route('admin.table-views.store'), $this->body([
            'payload' => $this->payload(['query' => ['q' => $this->tree(depth: 4)]]),
        ]))->assertStatus(422);

        $this->assertDatabaseCount('table_views', 0);
    }

    public function test_a_query_tree_with_too_many_rules_is_rejected(): void
    {
        $this->actingAsUser();

        $this->postJson(route('admin.table-views.store'), $this->body([
            'payload' => $this->payload(['query' => ['q' => $this->tree(rules: 26)]]),
        ]))->assertStatus(422);

        $this->assertDatabaseCount('table_views', 0);
    }

    public function test_an_oversized_query_tree_is_rejected(): void
    {
        $this->actingAsUser();

        $fat = [
            'conjunction' => 'and',
            'rules' => [['field' => 'name', 'operator' => 'eq', 'value' => str_repeat('a', 20000)]],
            'groups' => [],
        ];

        $this->postJson(route('admin.table-views.store'), $this->body([
            'payload' => $this->payload(['query' => ['q' => $fat]]),
        ]))->assertStatus(422);

        $this->assertDatabaseCount('table_views', 0);
    }

    public function test_a_well_formed_query_tree_is_accepted(): void
    {
        $this->actingAsUser();

        $this->post(route('admin.table-views.store'), $this->body([
            'payload' => $this->payload(['query' => ['q' => $this->tree(depth: 3, rules: 5)]]),
        ]))->assertRedirect();

        $this->assertDatabaseCount('table_views', 1);
    }

    public function test_the_per_user_per_key_cap_is_enforced(): void
    {
        $user = $this->actingAsUser();

        for ($i = 0; $i < 25; $i++) {
            TableView::forceCreate([
                'user_id' => $user->id,
                'table_key' => self::KEY,
                'name' => 'View '.$i,
                'slug' => (string) \Illuminate\Support\Str::ulid(),
                'visibility' => 'private',
                'is_default' => false,
                'payload' => [],
                'sort' => 0,
            ]);
        }

        $this->postJson(route('admin.table-views.store'), $this->body())->assertStatus(422);

        $this->assertDatabaseCount('table_views', 25);
    }

    public function test_team_visibility_requires_a_current_team(): void
    {
        $this->actingAsUser();

        $this->postJson(route('admin.table-views.store'), $this->body(['visibility' => 'team']))
            ->assertStatus(422);

        $this->assertDatabaseCount('table_views', 0);
    }

    public function test_a_private_view_of_another_user_is_invisible_and_returns_404(): void
    {
        $owner = $this->makeUser();
        $view = $this->makeView($owner, ['name' => 'Owner view']);

        $other = $this->actingAsUser();

        $this->getJson(route('admin.table-views.index', ['table_key' => self::KEY]))
            ->assertOk()
            ->assertJsonCount(0, 'views');

        $this->deleteJson(route('admin.table-views.destroy', $view))->assertStatus(404);
        $this->assertDatabaseHas('table_views', ['id' => $view->id]);
        $this->assertNotSame($owner->id, $other->id);
    }

    public function test_a_teammate_can_read_a_team_view_but_cannot_write_it(): void
    {
        [$team, $owner, $mate] = $this->team();
        $view = $this->makeView($owner, ['visibility' => 'team', 'team_id' => $team->id, 'name' => 'Team view']);

        $this->actingAs($mate);

        $this->getJson(route('admin.table-views.index', ['table_key' => self::KEY]))
            ->assertOk()
            ->assertJsonCount(1, 'views');

        $this->putJson(route('admin.table-views.update', $view), $this->body(['name' => 'Hijacked']))
            ->assertStatus(403);

        $this->assertDatabaseHas('table_views', ['id' => $view->id, 'name' => 'Team view']);
    }

    public function test_a_non_member_cannot_see_a_team_view(): void
    {
        [$team, $owner] = $this->team();
        $view = $this->makeView($owner, ['visibility' => 'team', 'team_id' => $team->id, 'name' => 'Team view']);

        $this->actingAsUser();

        $this->getJson(route('admin.table-views.index', ['table_key' => self::KEY]))
            ->assertOk()
            ->assertJsonCount(0, 'views');

        $this->deleteJson(route('admin.table-views.destroy', $view))->assertStatus(404);
    }

    public function test_make_default_is_exclusive_per_user_and_key(): void
    {
        $user = $this->actingAsUser();
        $other = $this->makeUser();

        $first = $this->makeView($user, ['name' => 'First', 'is_default' => true]);
        $second = $this->makeView($user, ['name' => 'Second']);
        $otherKey = $this->makeView($user, ['name' => 'Other key', 'table_key' => 'admin.pages.index', 'is_default' => true]);
        $foreign = $this->makeView($other, ['name' => 'Foreign', 'is_default' => true]);

        $this->post(route('admin.table-views.default', $second))->assertRedirect();

        $this->assertTrue($second->fresh()->is_default);
        $this->assertFalse($first->fresh()->is_default);
        $this->assertTrue($otherKey->fresh()->is_default);
        $this->assertTrue($foreign->fresh()->is_default);
    }

    public function test_the_reserved_columns_name_is_rejected_and_never_listed(): void
    {
        $user = $this->actingAsUser();

        $this->postJson(route('admin.table-views.store'), $this->body(['name' => TableView::COLUMNS_NAME]))
            ->assertStatus(422);

        $this->makeView($user, ['name' => TableView::COLUMNS_NAME]);

        $this->getJson(route('admin.table-views.index', ['table_key' => self::KEY]))
            ->assertOk()
            ->assertJsonCount(0, 'views');
    }

    private function makeView(User $user, array $attributes = []): TableView
    {
        return TableView::forceCreate(array_merge([
            'user_id' => $user->id,
            'table_key' => self::KEY,
            'name' => 'View '.uniqid(),
            'slug' => (string) \Illuminate\Support\Str::ulid(),
            'visibility' => 'private',
            'is_default' => false,
            'payload' => ['search' => 'x'],
            'sort' => 0,
        ], $attributes));
    }

    /** @return array{0: Team, 1: User, 2: User} */
    private function team(): array
    {
        $team = Team::create(['name' => 'Ops '.uniqid()]);
        $owner = $this->makeUser(['current_team_id' => $team->id]);
        $mate = $this->makeUser(['current_team_id' => $team->id]);
        $team->users()->attach([$owner->id, $mate->id]);

        return [$team, $owner, $mate];
    }
}
