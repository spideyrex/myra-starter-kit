<?php

namespace Tests\Feature\Report;

use App\Admin\Report\ReportRegistry;
use Tests\TestCase;

class ReportPermissionTest extends TestCase
{
    public function test_a_user_without_reports_view_is_refused_everywhere(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.reports.index'))->assertForbidden();
        $this->get(route('admin.reports.show', 'users'))->assertForbidden();
        $this->postJson(route('admin.reports.data', 'users'), ['state' => []])->assertForbidden();
        $this->postJson(route('admin.reports.widgets'), ['widgets' => []])->assertForbidden();
    }

    public function test_export_needs_its_own_ability(): void
    {
        $user = $this->actingAsUser();
        $user->givePermissionTo('reports.view');

        $this->postJson(route('admin.reports.data', 'users'), ['state' => []])->assertOk();
        $this->get(route('admin.reports.export', 'users'))->assertForbidden();

        $user->givePermissionTo('reports.export');
        $this->get(route('admin.reports.export', 'users'))->assertOk();
    }

    public function test_an_unmapped_report_is_a_404(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('admin.reports.show', 'nope'))->assertNotFound();
        $this->assertFalse(ReportRegistry::has('nope'));
    }

    public function test_the_catalogue_only_lists_reports_the_actor_may_see(): void
    {
        $user = $this->actingAsUser();

        $this->assertSame([], ReportRegistry::visibleTo($user));

        $user->givePermissionTo('reports.view');
        $user->forgetCachedPermissions();

        $this->assertArrayHasKey('users', ReportRegistry::visibleTo($user->fresh()));
    }
}
