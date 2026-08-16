<?php

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/**
 * The worker has two independent gates. Gate one (isCacheable) never sees these
 * URLs. This asserts gate two would refuse them anyway: every authenticated
 * response carries headers that cacheFirst() checks before cache.put.
 */
class ServiceWorkerCacheTest extends TestCase
{
    /** Mirrors the `isStorable()` check in public/myra-sw.js. */
    private function assertRefusedByTheResponseGate(\Illuminate\Testing\TestResponse $response, string $label): void
    {
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $vary = strtolower((string) $response->headers->get('Vary'));

        $refused = str_contains($cacheControl, 'private')
            || str_contains($cacheControl, 'no-store')
            || str_contains($vary, 'cookie');

        $this->assertTrue($refused, "{$label} must be refused by the response gate. Cache-Control: [{$cacheControl}] Vary: [{$vary}]");
    }

    public function test_the_dashboard_response_is_refused_by_the_response_gate(): void
    {
        $this->actingAsSuperAdmin();

        $this->assertRefusedByTheResponseGate($this->get(route('dashboard'))->assertOk(), 'admin.dashboard');
    }

    public function test_the_widget_batch_response_is_refused_by_the_response_gate(): void
    {
        $this->actingAsSuperAdmin();

        $response = $this->postJson(route('admin.reports.widgets'), ['widgets' => []])->assertOk();

        $this->assertRefusedByTheResponseGate($response, 'admin.reports.widgets');
    }

    public function test_the_users_index_response_is_refused_by_the_response_gate(): void
    {
        $this->actingAsSuperAdmin();

        $this->assertRefusedByTheResponseGate($this->get(route('admin.users.index'))->assertOk(), 'admin.users.index');
    }
}
