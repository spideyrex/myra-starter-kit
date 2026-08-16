<?php

namespace Tests\Feature\Homepage;

use App\Homepage\TemplateRegistry;
use App\Settings\HomepageSettings;
use Tests\TestCase;

class HomepageTemplateRenderTest extends TestCase
{
    use SyncsHomepageFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
    }

    /** @return array<int,array{0:string}> */
    public static function templateKeys(): array
    {
        return [['classic'], ['spotlight'], ['editorial'], ['saas'], ['minimal'], ['docs']];
    }

    /** @dataProvider templateKeys */
    public function test_each_registered_template_renders_the_public_homepage(string $key): void
    {
        $settings = app(HomepageSettings::class);
        $settings->template = $key;
        $settings->save();

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame($key, $props['template']);
        $this->assertSame('Home', $this->get('/')->viewData('page')['component']);
    }

    /**
     * The dispatcher payload is additive: the pre-migration keys are still
     * present and unchanged, so a rollback to the old Home.vue is a revert.
     */
    public function test_the_payload_is_additive_over_the_pre_migration_shape(): void
    {
        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertArrayHasKey('settings', $props);
        $this->assertArrayHasKey('authenticated', $props);
        $this->assertSame(app(HomepageSettings::class)->hero_title, $props['settings']['hero_title']);
        $this->assertArrayHasKey('hero_image_url', $props['settings']);

        foreach (['template', 'templateOptions', 'sectionOrder'] as $added) {
            $this->assertArrayHasKey($added, $props);
        }

        // >>> MYRA v2.7 [A] START
        // The raw list never ships inside settings — only the normalised one,
        // as its own prop. The fixture below deliberately does not capture it:
        // the ids are server-assigned ULIDs and would drift on every run.
        $this->assertArrayNotHasKey('blocks', $props['settings']);
        $this->assertArrayHasKey('blocks', $props);
        $this->assertIsArray($props['blocks']);
        // <<< MYRA v2.7 [A] END
    }

    /** A fresh install that never ran the chooser still lands on classic. */
    public function test_a_missing_stored_template_falls_back_without_failing(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->template = '';
        $settings->save();

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame('classic', $props['template']);
    }

    public function test_a_disabled_homepage_still_redirects_to_login(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->enabled = false;
        $settings->save();

        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_template_options_are_scoped_to_the_selected_template(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->template = 'spotlight';
        $settings->template_options = [
            'spotlight' => ['hero' => ['align' => 'left']],
            'classic' => ['hero' => ['align' => 'center']],
        ];
        $settings->save();

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame(['hero' => ['align' => 'left']], (array) $props['templateOptions']);
    }

    public function test_the_stored_section_order_drives_the_payload_order(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->template = 'classic';
        $settings->section_order = ['cta', 'pricing', 'hero'];
        $settings->save();

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame(
            ['cta', 'pricing', 'hero', 'features', 'testimonials'],
            $props['sectionOrder'],
            'The stored order leads, and sections it forgot are appended rather than dropped.',
        );
    }

    public function test_the_homepage_payload_matches_the_committed_fixture(): void
    {
        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->syncFixture('tests/js/fixtures/homepage-settings.json', [
            'settings' => $props['settings'],
            'template' => $props['template'],
            'templateOptions' => $props['templateOptions'],
            'sectionOrder' => $props['sectionOrder'],
        ]);
    }
}
