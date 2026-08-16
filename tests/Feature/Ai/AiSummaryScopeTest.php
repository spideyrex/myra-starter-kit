<?php

namespace Tests\Feature\Ai;

use App\Models\User;
use App\Services\Ai\AiService;
use Tests\Support\FakeAiService;
use Tests\TestCase;

/**
 * A summary narrates what the acting user can already see. The controller
 * re-runs the batch under that user's own ownership scope, so a forged POST
 * cannot get somebody else's figures read back to it.
 */
class AiSummaryScopeTest extends TestCase
{
    private function enable(): FakeAiService
    {
        config()->set('myra.ai.features.summarise', true);

        $fake = new FakeAiService(['Signups held steady.'], true);
        $this->app->instance(AiService::class, $fake);

        return $fake;
    }

    private function widgets(): array
    {
        return ['signups' => [
            'report' => 'users',
            'period' => ['preset' => 'last_30_days'],
            'dimension' => 'status',
            'measures' => ['signups'],
        ]];
    }

    public function test_the_provider_never_sees_another_owners_rows_or_a_drill_url(): void
    {
        $mine = $this->actingAsRole('admin');
        $theirs = User::factory()->create();

        User::factory()->count(2)->create(['status' => 'suspended', 'created_by' => $mine->id]);
        User::factory()->count(5)->create(['status' => 'suspended', 'created_by' => $theirs->id, 'name' => 'Trillian Astra']);

        $fake = $this->enable();

        $this->postJson(route('admin.ai.summarise'), ['widgets' => $this->widgets(), 'locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('summary', 'Signups held steady.');

        $payload = $fake->userPrompts[0];

        $this->assertStringNotContainsString('Trillian', $payload, 'No row data may reach the provider.');
        $this->assertStringNotContainsString('drill', $payload, 'Drill URLs carry filter params — they are dropped.');
        $this->assertStringNotContainsString('created_by', $payload);

        $decoded = json_decode($payload, true);

        $this->assertArrayHasKey('signups', $decoded);
        $this->assertSame(2, (int) ($decoded['signups']['totals']['signups'] ?? -1),
            'Only the acting user\'s own rows may be counted.');
    }

    public function test_a_super_admin_sees_the_unscoped_total(): void
    {
        $mine = $this->actingAsSuperAdmin();

        User::factory()->count(2)->create(['status' => 'suspended', 'created_by' => $mine->id]);
        User::factory()->count(5)->create(['status' => 'suspended']);

        $fake = $this->enable();

        $this->postJson(route('admin.ai.summarise'), ['widgets' => $this->widgets()])->assertOk();

        $decoded = json_decode($fake->userPrompts[0], true);

        $this->assertGreaterThanOrEqual(7, (int) ($decoded['signups']['totals']['signups'] ?? 0));
    }

    public function test_it_404s_when_the_feature_flag_is_off(): void
    {
        $this->actingAsSuperAdmin();
        $this->enable();
        config()->set('myra.ai.features.summarise', false);

        $this->postJson(route('admin.ai.summarise'), ['widgets' => $this->widgets()])->assertNotFound();
    }

    public function test_it_403s_without_the_ability(): void
    {
        $this->actingAsUser();
        $this->enable();

        $this->postJson(route('admin.ai.summarise'), ['widgets' => $this->widgets()])->assertForbidden();
    }
}
