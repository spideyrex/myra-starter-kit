<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\LegacyHomepageBlocks;
use App\Homepage\Sections\SectionRegistry;
use App\Homepage\TemplateRegistry;
use App\Settings\HomepageSettings;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Ensure it work": the homepage editor a user actually reaches must change
 * what the public page renders — never two editors where one silently does
 * nothing.
 *
 * The rule this pins down: the block list is EMPTY until the author adopts the
 * builder, so the shipped Settings > Homepage form stays authoritative; and the
 * moment the builder does own the page, the legacy form says so out loud
 * instead of reporting a success the public page will not show.
 */
class LegacyHomepageEditorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
        SectionRegistry::flush();
    }

    private function homepageForm(array $overrides = []): array
    {
        return array_merge([
            'enabled' => '1',
            'features_enabled' => '1',
            'testimonials_enabled' => '1',
            'pricing_enabled' => '1',
            'cta_enabled' => '1',
            'hero_title' => 'Original hero',
            'hero_subtitle' => 'Original subtitle',
            'hero_cta_text' => 'Go',
            'hero_cta_url' => '/go',
            'features_title' => 'Original features',
            'cta_title' => 'Original cta',
        ], $overrides);
    }

    private function publicProps(): array
    {
        return $this->get('/')->assertOk()->viewData('page')['props'];
    }

    public function test_a_fresh_install_leaves_the_block_list_empty(): void
    {
        $seeded = DB::table('settings')->where('group', 'homepage')->where('name', 'blocks')->value('payload');

        $this->assertSame([], json_decode((string) $seeded, true));
        $this->assertSame([], app(HomepageSettings::class)->blocks);
        $this->assertSame([], $this->publicProps()['blocks'], 'The legacy path drives a fresh install.');
    }

    public function test_the_shipped_homepage_editor_changes_what_the_public_page_renders(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.settings.update-homepage'), $this->homepageForm([
            'hero_title' => 'Edited through the settings form',
            'features_title' => 'Edited features heading',
        ]))->assertRedirect()->assertSessionHas('success');

        $props = $this->publicProps();

        $this->assertSame([], $props['blocks'], 'Nothing adopted the builder, so the legacy path still runs.');
        $this->assertSame('Edited through the settings form', $props['settings']['hero_title']);
        $this->assertSame('Edited features heading', $props['settings']['features_title']);
    }

    public function test_disabling_a_section_through_the_shipped_editor_removes_it_from_the_public_page(): void
    {
        $this->actingAsSuperAdmin();

        $this->post(route('admin.settings.update-homepage'), $this->homepageForm(['pricing_enabled' => '0']))
            ->assertRedirect();

        $this->assertFalse($this->publicProps()['settings']['pricing_enabled']);
    }

    public function test_the_settings_page_reports_which_editor_owns_the_page(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->get(route('admin.settings.index'))->assertOk()->viewData('page')['props'];

        $this->assertFalse($props['homepage']['page_builder_active']);
        $this->assertArrayNotHasKey('blocks', $props['homepage'], 'The raw list is not this form\'s business.');

        $settings = app(HomepageSettings::class);
        $settings->blocks = LegacyHomepageBlocks::fromSettings($settings);
        $settings->save();

        $props = $this->get(route('admin.settings.index'))->assertOk()->viewData('page')['props'];

        $this->assertTrue(
            $props['homepage']['page_builder_active'],
            'Once the builder owns the page the legacy form has to say so.',
        );
    }

    public function test_a_legacy_save_while_the_builder_owns_the_page_warns_instead_of_claiming_success(): void
    {
        $this->actingAsSuperAdmin();

        $settings = app(HomepageSettings::class);
        $settings->blocks = LegacyHomepageBlocks::fromSettings($settings);
        $settings->save();

        $response = $this->post(route('admin.settings.update-homepage'), $this->homepageForm([
            'hero_title' => 'This will not reach the public page',
        ]));

        $response->assertRedirect()->assertSessionMissing('success');
        $this->assertStringContainsString('page builder', (string) session('warning'));

        // The chrome fields on that same form DO still apply.
        $this->assertSame('Original cta', app(HomepageSettings::class)->cta_title, 'The rollback copy is still written.');
    }

    public function test_emptying_the_block_list_hands_the_page_back_to_the_legacy_editor(): void
    {
        $this->actingAsSuperAdmin();

        $settings = app(HomepageSettings::class);
        $settings->blocks = LegacyHomepageBlocks::fromSettings($settings);
        $settings->save();

        $this->assertNotEmpty($this->publicProps()['blocks']);

        $settings = app(HomepageSettings::class);
        $settings->blocks = [];
        $settings->save();

        $this->post(route('admin.settings.update-homepage'), $this->homepageForm(['hero_title' => 'Back in charge']))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('Back in charge', $this->publicProps()['settings']['hero_title']);
    }

    public function test_the_landing_page_order_form_warns_once_the_builder_owns_the_order(): void
    {
        $this->actingAsSuperAdmin();

        $payload = ['template' => 'classic', 'section_order' => ['cta', 'hero']];

        $this->put(route('admin.landing.update'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success');

        $settings = app(HomepageSettings::class);
        $settings->blocks = LegacyHomepageBlocks::fromSettings($settings);
        $settings->save();

        $this->put(route('admin.landing.update'), $payload)
            ->assertRedirect()
            ->assertSessionMissing('success');

        $this->assertStringContainsString('page builder', (string) session('warning'));
    }
}
