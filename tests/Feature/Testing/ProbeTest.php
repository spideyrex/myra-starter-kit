<?php

namespace Tests\Feature\Testing;

use App\Admin\Testing\InteractsWithMyra;
use App\Models\User;
use PHPUnit\Framework\AssertionFailedError;
use Tests\TestCase;

/** The probes are exercised against REAL admin endpoints, never a stub payload. */
class ProbeTest extends TestCase
{
    use InteractsWithMyra;

    public function test_table_probe_reads_the_real_users_listing(): void
    {
        $this->actingAsSuperAdmin();
        User::factory()->count(3)->create();

        $this->myraTable($this->get(route('admin.users.index')), 'users')
            ->assertColumnExists('email')
            ->assertColumnDoesNotLeak('password')
            ->assertPaginationMode('length-aware')
            ->assertSortRejected('password')
            ->assertPerPageCapped(999);
    }

    public function test_table_probe_sees_and_hides_specific_records(): void
    {
        $actor = $this->actingAsSuperAdmin();
        $mine = User::factory()->create(['created_by' => $actor->id]);

        $probe = $this->myraTable($this->get(route('admin.users.index')), 'users');
        $probe->assertCanSeeRecords([$mine]);

        $stranger = User::factory()->create();
        $this->actingAsWithPermissions(['users.view']);

        $this->myraTable($this->get(route('admin.users.index')), 'users')
            ->assertCanNotSeeRecords([$stranger]);
    }

    /**
     * Ownership scoping is only provable from props when the payload actually
     * exposes the owner — the advanced-filters demo does, the users listing does
     * not, and the probe says so instead of passing vacuously.
     */
    public function test_scoped_to_actor_reads_a_real_ownership_column(): void
    {
        $actor = $this->actingAsWithPermissions(['demo.view']);
        User::factory()->count(2)->create(['created_by' => $actor->id]);
        User::factory()->count(2)->create();

        $this->myraTable($this->get(route('admin.demo.advanced-filters')), 'records')
            ->assertScopedToActor();
    }

    public function test_scoped_to_actor_refuses_a_payload_with_no_owner_column(): void
    {
        $this->actingAsSuperAdmin();

        $this->expectException(AssertionFailedError::class);

        $this->myraTable($this->get(route('admin.users.index')), 'users')->assertScopedToActor();
    }

    public function test_saved_views_assertion_is_not_vacuous(): void
    {
        $this->actingAsWithPermissions(['demo.view']);

        $this->myraTable($this->get(route('admin.demo.saved-views')), 'products')
            ->assertSavedViewsEnabled();

        $this->actingAsSuperAdmin();

        $this->expectException(AssertionFailedError::class);
        $this->myraTable($this->get(route('admin.users.index')), 'users')->assertSavedViewsEnabled();
    }

    public function test_action_probe_enforces_the_route_gate(): void
    {
        $this->myraAction('admin.demo.bulk-action')
            ->bulk([1, 2])
            ->withPayload(['action' => 'delete'])
            ->assertRequiresPermission('demo.view');
    }

    public function test_action_probe_reads_the_flash_it_triggered(): void
    {
        $this->actingAsWithPermissions(['demo.view']);

        $this->myraAction('admin.demo.bulk-action')
            ->bulk([1, 2, 3])
            ->withPayload(['action' => 'archive'])
            ->assertFlashes('success', 'Bulk archive performed on 3 item(s).');
    }

    public function test_schema_probe_reads_a_real_server_declared_field_set(): void
    {
        $this->actingAsWithPermissions(['demo.view']);

        $this->myraSchema($this->get(route('admin.demo.advanced-filters')), 'constraints')
            ->assertFieldExists('name')
            ->assertFieldExists('status')
            ->assertFieldDoesNotExist('password');
    }

    public function test_schema_probe_drives_a_real_form_request(): void
    {
        $this->actingAsSuperAdmin();

        $probe = $this->myraSchema($this->get(route('admin.demo.advanced-filters')), 'constraints')
            ->to('admin.users.store')
            ->fill(['name' => '', 'email' => 'not-an-email'])
            ->assertState(['email' => 'not-an-email']);

        $probe->submit();
        $probe->assertHasErrors(['name', 'email']);
    }

    public function test_table_probe_counts_the_queries_a_listing_issues(): void
    {
        $this->actingAsSuperAdmin();
        User::factory()->count(5)->create();

        $this->myraTable($this->get(route('admin.users.index')), 'users')
            ->assertQueryCount(60);
    }
}
