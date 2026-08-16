<?php

namespace Tests\Feature\Tenancy;

use App\Admin\Tenancy\BaselineProbe;
use App\Admin\Tenancy\Tenancy;
use App\Models\Article;
use App\Models\Category;
use App\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * REAL PAYLOAD. The committed JSON is the prop the server actually renders, and
 * tests/js/tenancyStatus.spec.ts mounts the real page against that same file.
 */
class TenancyStatusPayloadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'myra.tenancy.enabled' => false,
            'myra.tenancy.models' => [Article::class, Page::class, Category::class],
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

    public function test_the_demo_page_publishes_the_status_payload(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo('demo.view');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->actingAs($user);

        $response = $this->get(route('admin.demo.tenancy'));
        $response->assertOk();

        $status = $this->prop($response, 'status');

        $this->assertFalse($status['enabled']);
        $this->assertSame('team_id', $status['column']);
        $this->assertCount(3, $status['models']);

        foreach ($status['models'] as $model) {
            $this->assertTrue($model['usesTrait'], $model['class'].' lost BelongsToTenant.');
            $this->assertTrue($model['hasColumn'], $model['class'].' has no tenant column.');
            $this->assertFalse($model['scoped'], $model['class'].' registered a scope while tenancy is off.');
        }

        $this->syncFixture('tests/js/fixtures/tenancy-status.json', $status);
    }

    public function test_the_route_is_gated_by_demo_view(): void
    {
        $this->actingAs($this->makeUser());

        $this->get(route('admin.demo.tenancy'))->assertForbidden();
    }

    private function prop(TestResponse $response, string $key): array
    {
        $props = $response->viewData('page')['props'] ?? [];

        $this->assertArrayHasKey($key, $props, "Inertia prop [{$key}] is missing.");

        return json_decode(json_encode($props[$key]), true);
    }

    /**
     * MYRA_WRITE_FIXTURES=1 rewrites the committed file; otherwise the fresh
     * payload must match it byte for byte.
     */
    private function syncFixture(string $relativePath, array $payload): void
    {
        $path = base_path($relativePath);
        $encoded = BaselineProbe::encode($payload);

        if (env('MYRA_WRITE_FIXTURES') === '1' || env('MYRA_WRITE_FIXTURES') === true) {
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, $encoded);

            return;
        }

        $this->assertFileExists($path, "Missing fixture. Regenerate with MYRA_WRITE_FIXTURES=1 php artisan test --filter=".static::class);
        $this->assertSame(
            (string) file_get_contents($path),
            $encoded,
            "The committed fixture no longer matches the payload the server produces ({$relativePath}).",
        );
    }
}
