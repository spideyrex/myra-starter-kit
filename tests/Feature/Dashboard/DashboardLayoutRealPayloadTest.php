<?php

namespace Tests\Feature\Dashboard;

use App\Admin\Dashboard\CatalogueWidget;
use App\Admin\Dashboard\WidgetCatalogue;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * REAL PAYLOAD. The PUT body is built from the catalogue prop the server
 * actually rendered — never a literal — and the committed fixture is the prop
 * the server actually returns. tests/js/applyLayout.spec.ts reads the same file.
 */
class DashboardLayoutRealPayloadTest extends TestCase
{
    private const FIXTURE = 'tests/js/fixtures/dashboard-layout.json';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'myra.dashboard.catalogue' => [],
            'myra.dashboard.editable' => true,
            'myra.reports.max_groups' => 200,
        ]);
        WidgetCatalogue::flush();

        WidgetCatalogue::add(CatalogueWidget::chart('trend')
            ->titleKey('dashboardLayout.demo.trendTitle')
            ->descriptionKey('dashboardLayout.demo.trendDescription')
            ->categoryKey('dashboardLayout.categories.charts')
            ->icon('ChartArea')
            ->report('users')
            ->dimensions(['created_at', 'status'])
            ->measures(['signups', 'emails'])
            ->chartTypes(['area', 'bar', 'doughnut'])
            ->defaults(['dimension' => 'created_at', 'measures' => ['signups'], 'limit' => 12])
            ->defaultColSpan(['sm' => 2, 'lg' => 2])
            ->maxInstances(2)
            ->tags(['chart', 'trend']));
    }

    protected function tearDown(): void
    {
        WidgetCatalogue::flush();

        parent::tearDown();
    }

    private function editor(): User
    {
        $user = $this->makeUser();
        $user->givePermissionTo(['dashboard.customise', 'reports.view']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function prop(TestResponse $response, string $key): mixed
    {
        $props = $response->viewData('page')['props'] ?? [];

        $this->assertArrayHasKey($key, $props, "Inertia prop [{$key}] is missing.");

        return json_decode(json_encode($props[$key]), true);
    }

    public function test_the_round_trip_is_stable_and_matches_the_committed_fixture(): void
    {
        $this->actingAs($this->editor());

        // 1. The ACTUAL props the dashboard renders.
        $first = $this->get(route('dashboard'));
        $first->assertOk();

        $catalogue = $this->prop($first, 'dashboardCatalogue');
        $this->assertNull($this->prop($first, 'dashboardLayout'));
        $this->assertCount(1, $catalogue);

        // 2. A PUT body derived from that prop. Nothing here is hand-authored.
        $entry = $catalogue[0];
        $instanceKey = $entry['key'].'#fixture';

        $payload = [
            'version' => 1,
            'entries' => [
                ['key' => 'total_users', 'order' => 0, 'colSpan' => 1, 'rowSpan' => 1, 'hidden' => false],
                ['key' => $instanceKey, 'order' => 1, 'colSpan' => $entry['defaultColSpan'], 'rowSpan' => 1, 'hidden' => false],
                ['key' => 'pending_verifications', 'order' => 2, 'colSpan' => 1, 'rowSpan' => 1, 'hidden' => true],
            ],
            'instances' => [[
                'key' => $instanceKey,
                'catalogue' => $entry['key'],
                'binding' => $entry['defaults'],
                'title' => 'Fixture widget',
                'chartType' => $entry['chartTypes'][0],
                'colSpan' => $entry['defaultColSpan'],
                'order' => 1,
            ]],
        ];

        $this->put(route('admin.dashboard-layouts.update', 'admin.dashboard'), [
            'dashboard_key' => 'admin.dashboard',
            'payload' => $payload,
        ])->assertRedirect();

        $second = $this->get(route('dashboard'));
        $layout = $this->prop($second, 'dashboardLayout');

        $this->assertSame(1, $layout['version']);
        $this->assertCount(3, $layout['entries']);
        $this->assertCount(1, $layout['instances']);
        $this->assertSame($instanceKey, $layout['instances'][0]['key']);
        $this->assertSame('users', $layout['instances'][0]['binding']['report']);

        // 3. Idempotent: PUTting the same body again returns the same prop.
        $this->put(route('admin.dashboard-layouts.update', 'admin.dashboard'), [
            'dashboard_key' => 'admin.dashboard',
            'payload' => $payload,
        ])->assertRedirect();

        $this->assertSame($layout, $this->prop($this->get(route('dashboard')), 'dashboardLayout'));

        $this->syncFixture(self::FIXTURE, [
            'catalogue' => $this->prop($second, 'dashboardCatalogue'),
            'layout' => $layout,
        ]);
    }

    /** §0.6 fixture bridge. Local copy on purpose — bundles do not share one. */
    private function syncFixture(string $relativePath, array $payload): void
    {
        $path = base_path($relativePath);
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";

        if (getenv('MYRA_WRITE_FIXTURES')) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $encoded);

            return;
        }

        $this->assertFileExists($path, 'Missing fixture. Re-run with MYRA_WRITE_FIXTURES=1.');
        $this->assertSame(
            (string) file_get_contents($path),
            $encoded,
            'Fixture drift. Re-run with MYRA_WRITE_FIXTURES=1 and review the diff.',
        );
    }
}
