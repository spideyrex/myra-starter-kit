<?php

namespace Tests\Feature\Tenancy;

use App\Admin\Tenancy\Tenancy;
use App\Models\Article;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

/** The single predicate, examined directly. */
class TenancyPredicateTest extends TestCase
{
    protected function tearDown(): void
    {
        config(['myra.tenancy.enabled' => false, 'myra.tenancy.models' => []]);
        Tenancy::flush();
        Model::clearBootedModels();

        parent::tearDown();
    }

    private function enable(array $overrides = []): Team
    {
        $team = Team::create(['name' => 'Alpha']);

        config(array_merge([
            'myra.tenancy.enabled' => true,
            'myra.tenancy.models' => [Article::class],
            'myra.tenancy.null_rows' => 'strict',
        ], $overrides));

        Tenancy::flush();
        Model::clearBootedModels();

        return $team;
    }

    private function actAsMember(Team $team): User
    {
        $user = $this->makeUser();
        $user->teams()->attach($team->id);
        $user->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($user);
        Tenancy::flush();

        return $user;
    }

    public function test_no_tenant_fails_closed_with_a_false_predicate(): void
    {
        $this->enable();
        $this->actingAs($this->makeUser());
        Tenancy::flush();

        $query = Article::query()->withoutGlobalScopes();
        Tenancy::apply($query, 'articles');

        $this->assertStringContainsString('1 = 0', $query->toBase()->toSql());
    }

    public function test_strict_mode_emits_a_plain_equality(): void
    {
        $team = $this->enable();
        $this->actAsMember($team);

        $query = Article::query()->withoutGlobalScopes();
        Tenancy::apply($query, 'articles');

        $sql = $query->toBase()->toSql();

        $this->assertStringContainsString('"articles"."team_id" = ?', $sql);
        $this->assertStringNotContainsString(' or ', $sql);
        $this->assertSame([$team->id], $query->toBase()->getBindings());
    }

    public function test_shared_mode_nests_the_or_set(): void
    {
        $team = $this->enable(['myra.tenancy.null_rows' => 'shared']);
        $this->actAsMember($team);

        $query = Article::query()->withoutGlobalScopes()->where('status', 'draft');
        Tenancy::apply($query, 'articles');

        $sql = $query->toBase()->toSql();

        // Unwrapped, the `or` would escape the status predicate entirely.
        $this->assertStringContainsString('and ("articles"."team_id" = ? or "articles"."team_id" is null)', $sql);
    }

    public function test_membership_scoping_uses_an_exists_subquery(): void
    {
        $team = $this->enable();
        $this->actAsMember($team);

        $query = User::query();
        Tenancy::applyMembership($query, 'users');

        $sql = $query->toBase()->toSql();

        $this->assertStringContainsString('exists', $sql);
        $this->assertStringContainsString('team_user', $sql);
    }

    public function test_an_unknown_table_fails_closed_rather_than_leaking(): void
    {
        $team = $this->enable();
        $this->actAsMember($team);

        $query = Article::query()->withoutGlobalScopes();
        Tenancy::applyForModel(\Spatie\Activitylog\Models\Activity::class, $query, 'activity_log');

        $this->assertStringContainsString('1 = 0', $query->toBase()->toSql());
    }

    public function test_a_table_declared_shared_is_left_alone(): void
    {
        $team = $this->enable(['myra.tenancy.shared_tables' => ['activity_log']]);
        $this->actAsMember($team);

        $query = Article::query()->withoutGlobalScopes();
        $before = $query->toBase()->toSql();
        Tenancy::applyForModel(\Spatie\Activitylog\Models\Activity::class, $query, 'activity_log');

        $this->assertSame($before, $query->toBase()->toSql());
    }

    public function test_a_model_with_an_active_scope_is_not_scoped_twice(): void
    {
        $team = $this->enable();
        $this->actAsMember($team);

        $query = Article::query()->withoutGlobalScopes();
        $before = $query->toBase()->toSql();
        Tenancy::applyForModel(Article::class, $query, 'articles');

        $this->assertSame($before, $query->toBase()->toSql());
    }

    public function test_super_admin_bypasses_the_predicate(): void
    {
        $team = $this->enable();

        $super = $this->makeUser();
        $super->assignRole('super-admin');
        $super->teams()->attach($team->id);
        $super->forceFill(['current_team_id' => $team->id])->save();
        $this->actingAs($super);
        Tenancy::flush();

        $query = Article::query()->withoutGlobalScopes();
        $before = $query->toBase()->toSql();
        Tenancy::apply($query, 'articles');

        $this->assertSame($before, $query->toBase()->toSql());
    }
}
