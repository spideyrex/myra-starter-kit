<?php

namespace Tests\Unit\Dashboard;

use App\Admin\Dashboard\CatalogueWidget;
use App\Admin\Dashboard\WidgetCatalogue;
use App\Admin\Dashboard\WidgetInstance;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class WidgetInstanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        WidgetCatalogue::flush();
        config(['myra.dashboard.catalogue' => []]);

        WidgetCatalogue::add(CatalogueWidget::chart('trend')
            ->titleKey('dashboardLayout.demo.trendTitle')
            ->report('users')
            ->dimensions(['created_at', 'status'])
            ->measures(['signups', 'emails'])
            ->chartTypes(['area', 'bar'])
            ->defaults(['dimension' => 'created_at', 'measures' => ['signups'], 'limit' => 12])
            ->maxInstances(2));

        WidgetCatalogue::add(CatalogueWidget::stat('gated')
            ->titleKey('dashboardLayout.demo.countTitle')
            ->permission('nobody.holds.this')
            ->measures(['signups'])
            ->defaults(['measures' => ['signups']]));

        WidgetCatalogue::add(CatalogueWidget::stat('missing_report')
            ->titleKey('dashboardLayout.demo.countTitle')
            ->report('does-not-exist')
            ->measures(['signups'])
            ->defaults(['measures' => ['signups']]));
    }

    protected function tearDown(): void
    {
        WidgetCatalogue::flush();

        parent::tearDown();
    }

    private function reader(): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo('reports.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function rawInstance(array $overrides = []): array
    {
        return array_merge([
            'key' => 'trend#aa11bb',
            'catalogue' => 'trend',
            'binding' => ['dimension' => 'created_at', 'measures' => ['signups']],
        ], $overrides);
    }

    public function test_a_valid_instance_resolves_with_only_declared_binding_keys(): void
    {
        $resolved = WidgetInstance::resolve($this->rawInstance(), $this->reader());

        $this->assertNotNull($resolved);
        $this->assertSame('chart', $resolved['type']);
        $this->assertSame('trend', $resolved['catalogue']);
        $this->assertSame(
            ['dimension', 'measures', 'limit', 'report'],
            array_keys($resolved['binding']),
        );
        $this->assertSame('users', $resolved['binding']['report']);
    }

    public function test_an_undeclared_binding_key_is_dropped(): void
    {
        $resolved = WidgetInstance::resolve($this->rawInstance([
            'binding' => ['dimension' => 'status', 'measures' => ['signups'], 'table' => 'users', 'raw' => '1=1'],
        ]), $this->reader());

        $this->assertNotNull($resolved);
        $this->assertArrayNotHasKey('table', $resolved['binding']);
        $this->assertArrayNotHasKey('raw', $resolved['binding']);
    }

    public function test_an_unknown_catalogue_key_resolves_to_null(): void
    {
        $this->assertNull(WidgetInstance::resolve($this->rawInstance(['catalogue' => 'nope']), $this->reader()));
    }

    public function test_an_entry_whose_permission_the_actor_lacks_resolves_to_null(): void
    {
        Permission::findOrCreate('nobody.holds.this');
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assertNull(WidgetInstance::resolve([
            'key' => 'gated#1', 'catalogue' => 'gated', 'binding' => [],
        ], $this->reader()));
    }

    public function test_a_report_absent_from_the_registry_resolves_to_null(): void
    {
        $this->assertNull(WidgetInstance::resolve([
            'key' => 'missing_report#1', 'catalogue' => 'missing_report', 'binding' => [],
        ], $this->reader()));
    }

    public function test_a_report_ability_the_actor_lacks_resolves_to_null(): void
    {
        $this->assertNull(WidgetInstance::resolve($this->rawInstance(), $this->makeUser()));
    }

    public function test_a_dimension_outside_the_vocabulary_resolves_to_null(): void
    {
        $this->assertNull(WidgetInstance::resolve($this->rawInstance([
            'binding' => ['dimension' => 'password', 'measures' => ['signups']],
        ]), $this->reader()));
    }

    public function test_a_measure_outside_the_vocabulary_resolves_to_null(): void
    {
        $this->assertNull(WidgetInstance::resolve($this->rawInstance([
            'binding' => ['dimension' => 'status', 'measures' => ['password']],
        ]), $this->reader()));
    }

    public function test_a_chart_type_outside_the_declared_set_resolves_to_null(): void
    {
        $this->assertNull(WidgetInstance::resolve($this->rawInstance(['chartType' => 'raw']), $this->reader()));
    }

    public function test_chart_types_narrow_and_never_widen(): void
    {
        $widget = CatalogueWidget::chart('narrow')->chartTypes(['area', 'not-a-chart-type']);

        $this->assertSame(['area'], $widget->chartTypeNames());
    }

    public function test_the_limit_is_clamped_to_the_report_ceiling(): void
    {
        config(['myra.reports.max_groups' => 50]);

        $resolved = WidgetInstance::resolve($this->rawInstance([
            'binding' => ['dimension' => 'status', 'measures' => ['signups'], 'limit' => 10000],
        ]), $this->reader());

        $this->assertSame(50, $resolved['binding']['limit']);
    }

    public function test_resolve_all_enforces_the_per_catalogue_cap(): void
    {
        $user = $this->reader();

        $resolved = WidgetInstance::resolveAll([
            $this->rawInstance(['key' => 'trend#1']),
            $this->rawInstance(['key' => 'trend#2']),
            $this->rawInstance(['key' => 'trend#3']),
        ], $user);

        $this->assertCount(2, $resolved);
    }

    public function test_resolve_all_enforces_the_global_cap(): void
    {
        config(['myra.dashboard.max_instances' => 1]);

        $resolved = WidgetInstance::resolveAll([
            $this->rawInstance(['key' => 'trend#1']),
            $this->rawInstance(['key' => 'trend#2']),
        ], $this->reader());

        $this->assertCount(1, $resolved);
    }

    public function test_the_client_schema_never_leaks_a_column_or_a_table(): void
    {
        $schema = WidgetCatalogue::get('trend')->toClientSchema();
        $json = (string) json_encode($schema);

        $this->assertStringNotContainsString('users.', $json);
        $this->assertArrayNotHasKey('column', $schema);
        $this->assertArrayNotHasKey('model', $schema);
    }
}
