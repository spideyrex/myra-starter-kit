<?php

namespace Tests\Feature\Tenancy;

use App\Admin\Tenancy\Concerns\TenantAware;
use App\Admin\Tenancy\Middleware\BindsTenant;
use App\Admin\Tenancy\Tenancy;
use App\Jobs\SendScheduledReport;
use App\Models\Article;
use App\Models\Team;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/** Counts the rows a tenant-aware job can see when it actually runs. */
class CountsVisibleArticles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TenantAware;

    public static ?int $seen = null;

    public function __construct()
    {
        $this->captureTenant();
    }

    public function handle(): void
    {
        static::$seen = Article::query()->count();
    }
}

class TenantQueueTest extends TestCase
{
    private Team $teamA;

    private Team $teamB;

    protected function setUp(): void
    {
        parent::setUp();

        CountsVisibleArticles::$seen = null;

        $this->teamA = Team::create(['name' => 'Alpha']);
        $this->teamB = Team::create(['name' => 'Beta']);

        config([
            'myra.tenancy.enabled' => true,
            'myra.tenancy.models' => [Article::class],
        ]);
        Tenancy::flush();
        Model::clearBootedModels();
    }

    protected function tearDown(): void
    {
        config(['myra.tenancy.enabled' => false, 'myra.tenancy.models' => []]);
        Tenancy::flush();
        Model::clearBootedModels();

        parent::tearDown();
    }

    public function test_a_job_dispatched_inside_a_tenant_still_sees_it_on_the_worker(): void
    {
        $owner = $this->makeUser();

        Tenancy::for($this->teamA, fn () => Article::factory()->count(2)->create(['created_by' => $owner->id]));
        Tenancy::for($this->teamB, fn () => Article::factory()->count(3)->create(['created_by' => $owner->id]));

        $job = Tenancy::for($this->teamA, function () {
            $job = new CountsVisibleArticles;
            $this->assertSame($this->teamA->id, $job->myraTenantId);

            return $job;
        });

        // The worker context: no session, no acting user, no bound tenant.
        Auth::forgetUser();
        Tenancy::flush();
        $this->assertNull(Tenancy::id());

        Bus::dispatch($job);

        $this->assertSame(2, CountsVisibleArticles::$seen);
        $this->assertNull(Tenancy::id(), 'The job leaked its tenant into the surrounding context.');
    }

    public function test_the_job_middleware_is_declared(): void
    {
        $job = new CountsVisibleArticles;

        $this->assertInstanceOf(BindsTenant::class, $job->middleware()[0]);
    }

    public function test_the_scheduled_report_job_captures_its_tenant(): void
    {
        $job = Tenancy::for($this->teamB, fn () => new SendScheduledReport(1));

        $this->assertSame($this->teamB->id, $job->myraTenantId);
        $this->assertInstanceOf(BindsTenant::class, $job->middleware()[0]);
    }

    public function test_a_job_with_no_captured_tenant_does_not_pin_the_context(): void
    {
        $owner = $this->makeUser();
        $owner->teams()->attach($this->teamA->id);
        $owner->forceFill(['current_team_id' => $this->teamA->id])->save();

        Tenancy::for($this->teamA, fn () => Article::factory()->create(['created_by' => $owner->id]));
        Tenancy::for($this->teamB, fn () => Article::factory()->create(['created_by' => $owner->id]));

        $job = new CountsVisibleArticles;
        $this->assertNull($job->myraTenantId);

        // The worker sets its own actor; the middleware must not override it.
        $this->actingAs($owner);
        Tenancy::flush();

        Bus::dispatch($job);

        $this->assertSame(1, CountsVisibleArticles::$seen);
    }
}
