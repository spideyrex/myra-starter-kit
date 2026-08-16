<?php

namespace Tests\Feature\Tenancy;

use App\Admin\Tenancy\Tenancy;
use App\Models\Article;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Tests\TestCase;

class TenancyAuditTest extends TestCase
{
    protected function tearDown(): void
    {
        config(['myra.tenancy.enabled' => false, 'myra.tenancy.models' => []]);
        Tenancy::flush();
        Model::clearBootedModels();

        parent::tearDown();
    }

    public function test_audit_refuses_readiness_while_null_tenant_rows_remain(): void
    {
        config(['myra.tenancy.models' => [Article::class], 'myra.tenancy.null_rows' => 'strict']);
        Tenancy::flush();
        Model::clearBootedModels();

        Article::factory()->create(['created_by' => User::factory()->create()->id]);

        $this->artisan('myra:tenancy-audit')
            ->expectsOutputToContain('articles')
            ->expectsOutputToContain('NOT READY')
            ->assertExitCode(1);
    }

    public function test_audit_passes_once_every_row_carries_a_tenant(): void
    {
        $team = Team::create(['name' => 'Alpha']);

        config([
            'myra.tenancy.enabled' => true,
            'myra.tenancy.models' => [Article::class],
            'myra.tenancy.null_rows' => 'strict',
        ]);
        Tenancy::flush();
        Model::clearBootedModels();

        Tenancy::for($team, fn () => Article::factory()->create([
            'created_by' => User::factory()->create()->id,
        ]));

        $this->artisan('myra:tenancy-audit')->assertExitCode(0);
    }

    public function test_audit_is_read_only(): void
    {
        config(['myra.tenancy.models' => [Article::class]]);
        Tenancy::flush();
        Model::clearBootedModels();

        Article::factory()->count(2)->create(['created_by' => User::factory()->create()->id]);

        $before = Article::withoutGlobalScopes()->get()->toArray();
        $this->artisan('myra:tenancy-audit');

        $this->assertEquals($before, Article::withoutGlobalScopes()->get()->toArray());
    }
}
