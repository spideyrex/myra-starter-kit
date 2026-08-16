<?php

namespace Tests\Feature\Dashboard;

use App\Admin\Dashboard\LayoutShape;
use App\Models\Role;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RoleDashboardSeeder;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RoleDashboardSeederTest extends TestCase
{
    private const KEY = 'admin.dashboard';

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(\App\Models\RoleDashboard::class)) {
            $this->markTestSkipped('bundle A not merged');
        }
    }

    /** Never `run` — narrowing PHPUnit's inherited run() is fatal at class load. */
    private function seedStarters(): RoleDashboardSeeder
    {
        $seeder = new RoleDashboardSeeder;
        $seeder->setContainer($this->app);
        $seeder->run();

        return $seeder;
    }

    private function rowFor(string $role): ?\App\Models\RoleDashboard
    {
        $id = Role::query()->where('name', $role)->value('id');

        return \App\Models\RoleDashboard::query()
            ->where('role_id', $id)
            ->where('dashboard_key', self::KEY)
            ->first();
    }

    public function test_nothing_is_configured_until_the_seeder_is_run(): void
    {
        $this->assertTrue(Schema::hasTable('role_dashboards'));
        $this->assertSame(0, \App\Models\RoleDashboard::query()->count());

        $user = $this->actingAsRole('manager');

        $props = $this->get(route('dashboard'))->assertOk()->viewData('page')['props'];

        $this->assertNull($props['dashboardLayout'] ?? null);
        $this->assertSame(
            ['source' => 'none', 'role' => null, 'hasRoleDefault' => false],
            $props['dashboardLayoutSource'],
        );
        $this->assertTrue($user->hasRole('manager'));
    }

    public function test_the_database_seeder_never_references_the_role_dashboard_seeder(): void
    {
        $source = file_get_contents((new \ReflectionClass(DatabaseSeeder::class))->getFileName());

        $this->assertStringNotContainsString(
            'RoleDashboardSeeder',
            $source,
            'Starter role dashboards must never run on an upgrade — inert by default is the whole contract.',
        );
    }

    public function test_every_starter_is_written_for_every_built_in_role(): void
    {
        $summary = $this->seedStarters()->summary;

        $this->assertSame(
            ['super-admin', 'admin', 'manager', 'editor', 'viewer'],
            array_column($summary, 'role'),
        );

        foreach ($summary as $row) {
            $this->assertSame('created', $row['status']);
            $this->assertSame(6, $row['widgets']);
            $this->assertNotNull($this->rowFor($row['role']));
        }
    }

    public function test_every_seeded_payload_passes_the_write_path_shape_check(): void
    {
        $this->seedStarters();

        foreach (array_keys(RoleDashboardSeeder::starters()) as $role) {
            $payload = $this->rowFor($role)->payload;

            $this->assertSame($payload, LayoutShape::assert($payload, 'payload'));
            $this->assertSame([], $payload['instances']);
        }
    }

    /** THE REAL PAYLOAD: the exact bytes the seeder persisted, not a literal. */
    public function test_every_seeded_payload_resolves_non_empty_for_its_own_role(): void
    {
        $this->seedStarters();

        foreach (array_keys(RoleDashboardSeeder::starters()) as $name) {
            $role = Role::query()->where('name', $name)->firstOrFail();
            $stored = json_decode($this->rowFor($name)->getRawOriginal('payload'), true);

            $resolved = \App\Admin\Dashboard\LayoutResolver::fromPayload(
                $stored,
                new \App\Admin\Dashboard\RolePrincipal($role),
            );

            $this->assertNotNull($resolved, "Starter for [{$name}] resolved to nothing for its own role.");
            $this->assertNotEmpty($resolved['entries']);
            $this->assertSame(
                range(0, count($resolved['entries']) - 1),
                array_column($resolved['entries'], 'order'),
            );
        }
    }

    public function test_a_seeded_starter_reaches_a_member_of_that_role_as_a_role_default(): void
    {
        $this->seedStarters();

        $this->actingAsRole('viewer');

        $props = $this->get(route('dashboard'))->assertOk()->viewData('page')['props'];

        $this->assertSame('role', $props['dashboardLayoutSource']['source']);
        $this->assertSame('viewer', $props['dashboardLayoutSource']['role']);
        $this->assertTrue($props['dashboardLayoutSource']['hasRoleDefault']);
        $this->assertNotEmpty($props['dashboardLayout']['entries']);
    }

    public function test_running_twice_does_not_stomp_an_edited_row(): void
    {
        $this->seedStarters();

        $row = $this->rowFor('manager');
        $row->payload = ['version' => 1, 'entries' => [['key' => 'total_users', 'order' => 0]], 'instances' => []];
        $row->save();

        $summary = $this->seedStarters()->summary;

        $this->assertSame(
            ['kept', 'kept', 'kept', 'kept', 'kept'],
            array_column($summary, 'status'),
        );
        $this->assertSame(5, \App\Models\RoleDashboard::query()->count());
        $this->assertSame(
            [['key' => 'total_users', 'order' => 0]],
            $this->rowFor('manager')->payload['entries'],
        );
    }

    public function test_a_missing_role_is_reported_rather_than_failing(): void
    {
        Role::query()->where('name', 'editor')->delete();

        $summary = $this->seedStarters()->summary;
        $statuses = array_column($summary, 'status', 'role');

        $this->assertSame('missing', $statuses['editor']);
        $this->assertSame('created', $statuses['viewer']);
    }

    public function test_the_console_command_seeds_and_is_idempotent(): void
    {
        $this->artisan('myra:role-dashboards:seed')->assertSuccessful();
        $this->assertSame(5, \App\Models\RoleDashboard::query()->count());

        $this->artisan('myra:role-dashboards:seed')->assertSuccessful();
        $this->assertSame(5, \App\Models\RoleDashboard::query()->count());
    }

    public function test_hidden_entries_are_tidying_and_never_widen_anything(): void
    {
        $this->seedStarters();

        $entries = $this->rowFor('viewer')->payload['entries'];
        $hidden = array_column(array_filter($entries, fn (array $e) => $e['hidden'] ?? false), 'key');

        $this->assertNotEmpty($hidden);

        $allowed = \App\Admin\Dashboard\StaticWidgetRegistry::visibleTo(
            new \App\Admin\Dashboard\RolePrincipal(Role::query()->where('name', 'viewer')->firstOrFail()),
        );

        foreach ($hidden as $key) {
            $this->assertContains($key, $allowed, "Starter hid [{$key}], which the role could not see anyway.");
        }
    }
}
