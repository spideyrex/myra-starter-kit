<?php

namespace Tests\Feature\Appearance;

use App\Appearance\Admin\AppearanceWriter;
use App\Http\Requests\Admin\UpdateSurfaceAppearanceRequest;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * 7.10 — server-emitted in, server-emitted out.
 *
 * The posted body is built from the props the editor page actually receives and
 * the field list the request object actually validates, so nothing here is a
 * hand-authored payload literal that can drift from the real form.
 */
class AdminRoundTripTest extends TestCase
{
    private function props(): array
    {
        return $this->get(route('admin.appearance.index'))->assertOk()->viewData('page')['props'];
    }

    /** Exactly the fields the Vue form binds, seeded from what the server emitted. */
    private function formPayload(array $mutations = []): array
    {
        $settings = $this->props()['appearanceSettings'];
        $payload = [];

        foreach (array_keys(UpdateSurfaceAppearanceRequest::ruleSet()) as $field) {
            $payload[$field] = $settings[$field] ?? null;
        }

        return array_merge($payload, $mutations);
    }

    private function stored(string $name): mixed
    {
        $row = DB::table('settings')
            ->where('group', AppearanceWriter::GROUP)
            ->where('name', $name)
            ->value('payload');

        return $row === null ? null : json_decode((string) $row, true);
    }

    public function test_the_editor_emits_every_field_the_form_posts_back(): void
    {
        $this->actingAsSuperAdmin();

        $settings = $this->props()['appearanceSettings'];

        foreach (array_keys(UpdateSurfaceAppearanceRequest::ruleSet()) as $field) {
            $this->assertArrayHasKey($field, $settings, $field.' is posted but never emitted');
        }
    }

    public function test_the_posted_form_round_trips_through_the_settings_table(): void
    {
        $this->actingAsSuperAdmin();

        $payload = $this->formPayload([
            'auth_layout' => 'cover',
            'auth_flip' => true,
            'auth_show_tagline' => false,
            'auth_bg_type' => 'gradient',
            'auth_bg_recipe' => 'dusk',
            'auth_bg_scrim' => 'strong',
        ]);

        $this->put(route('admin.appearance.update'), $payload)->assertRedirect();

        foreach (['auth_layout' => 'cover', 'auth_bg_type' => 'gradient', 'auth_bg_recipe' => 'dusk', 'auth_bg_scrim' => 'strong'] as $name => $value) {
            $this->assertSame($value, $this->stored($name), $name);
        }

        $this->assertTrue($this->stored('auth_flip'));
        $this->assertFalse($this->stored('auth_show_tagline'));

        // Server-emitted out: the reloaded editor resolves what was posted.
        $props = $this->props();

        $this->assertSame('cover', $props['appearanceSettings']['auth_layout']);
        $this->assertSame('cover', $props['preview']['layout']);
        $this->assertSame('gradient', $props['preview']['surface']['type']);
        $this->assertSame('dusk', $props['preview']['surface']['recipe']);
        $this->assertSame('strong', $props['preview']['surface']['scrim']);
        $this->assertNotSame([], $props['preview']['surface']['css_vars']);
    }

    public function test_switching_back_to_the_brand_surface_emits_no_css_variables(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(route('admin.appearance.update'), $this->formPayload([
            'auth_bg_type' => 'gradient',
            'auth_bg_recipe' => 'aurora',
        ]))->assertRedirect();

        $this->put(route('admin.appearance.update'), $this->formPayload([
            'auth_bg_type' => 'brand',
            'auth_bg_recipe' => null,
        ]))->assertRedirect();

        $props = $this->props();

        $this->assertSame('brand', $props['preview']['surface']['type']);
        $this->assertSame([], $props['preview']['surface']['css_vars']);
        $this->assertNull($props['preview']['surface']['recipe']);
    }

    /**
     * The engine ships the meta tag and the shared Inertia prop. Post-merge this
     * is the end-to-end assertion; in an isolated worktree there is no emitter.
     */
    public function test_the_saved_appearance_reaches_the_login_page(): void
    {
        if (! class_exists(\App\Appearance\AppearanceManager::class)) {
            $this->markTestSkipped('AppearanceManager ships with the engine bundle.');
        }

        $this->actingAsSuperAdmin();

        $this->put(route('admin.appearance.update'), $this->formPayload([
            'auth_layout' => 'cover',
            'auth_bg_type' => 'gradient',
            'auth_bg_recipe' => 'dusk',
            'auth_bg_scrim' => 'strong',
        ]))->assertRedirect();

        $this->post(route('logout'));

        $html = $this->get(route('login'))->assertOk()->getContent();

        preg_match('/<meta name="myra-appearance" content="([^"]*)"/', $html, $m);
        $emitted = json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true);

        $this->assertIsArray($emitted);
        $this->assertEquals(app(\App\Appearance\AppearanceManager::class)->toInertiaProp(), $emitted);
        $this->assertSame('cover', $emitted['auth']['layout']);
        $this->assertSame('dusk', $emitted['auth']['surface']['recipe']);
    }
}
