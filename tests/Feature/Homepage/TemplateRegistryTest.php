<?php

namespace Tests\Feature\Homepage;

use App\Homepage\HomepageTemplate;
use App\Homepage\TemplateRegistry;
use App\Settings\HomepageSettings;
use Tests\TestCase;

class TemplateRegistryTest extends TestCase
{
    private const TEMPLATE_PAGES = 'resources/js/Pages/Public/Templates';

    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
    }

    private function locale(string $code): array
    {
        return json_decode(file_get_contents(base_path("resources/js/i18n/locales/{$code}.json")), true);
    }

    private function resolves(array $messages, string $key): bool
    {
        $node = $messages;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return false;
            }
            $node = $node[$segment];
        }

        return is_string($node) && $node !== '';
    }

    public function test_every_registered_template_names_a_page_that_exists(): void
    {
        foreach (TemplateRegistry::all() as $template) {
            $path = base_path(self::TEMPLATE_PAGES.'/'.basename($template->componentValue()).'.vue');

            $this->assertFileExists(
                $path,
                "Template [{$template->key()}] names component [{$template->componentValue()}], which has no Vue page.",
            );
        }
    }

    public function test_every_template_page_has_a_registry_entry(): void
    {
        $pages = array_map(
            fn (string $path) => basename($path, '.vue'),
            glob(base_path(self::TEMPLATE_PAGES.'/*.vue')) ?: [],
        );

        $components = array_map(
            fn (HomepageTemplate $t) => basename($t->componentValue()),
            TemplateRegistry::all(),
        );

        sort($pages);
        sort($components);

        $this->assertSame(
            $components,
            $pages,
            'resources/js/Pages/Public/Templates drifted from the registry. '
            .'Every page needs one entry, and every entry needs a page.',
        );
    }

    public function test_every_i18n_key_resolves_in_all_three_locales(): void
    {
        $locales = [
            'en' => $this->locale('en'),
            'ms' => $this->locale('ms'),
            'zh' => $this->locale('zh'),
        ];

        foreach (TemplateRegistry::all() as $template) {
            foreach ([$template->resolvedTitleKey(), $template->resolvedDescriptionKey()] as $key) {
                foreach ($locales as $code => $messages) {
                    $this->assertTrue(
                        $this->resolves($messages, $key),
                        "Key [{$key}] is missing from {$code}.json — locale parity is a merge gate.",
                    );
                }
            }
        }
    }

    public function test_every_section_key_resolves_in_all_three_locales(): void
    {
        foreach (['en', 'ms', 'zh'] as $code) {
            $messages = $this->locale($code);

            foreach (HomepageTemplate::SECTIONS as $section) {
                $this->assertTrue($this->resolves($messages, "landing.sections.{$section}"));
            }
        }
    }

    /** @return array<string,array{0:?string}> */
    public static function degradingKeys(): array
    {
        return [
            'unknown' => ['not-a-template'],
            'empty' => [''],
            'null' => [null],
            'traversal' => ['../../app/Http'],
            'renamed' => ['Classic'],
        ];
    }

    /** @dataProvider degradingKeys */
    public function test_an_unusable_stored_key_degrades_to_classic(?string $stored): void
    {
        $this->assertSame(TemplateRegistry::FALLBACK, TemplateRegistry::resolve($stored)->key());
    }

    /** @dataProvider degradingKeys */
    public function test_the_homepage_still_renders_for_an_unusable_stored_key(?string $stored): void
    {
        $settings = app(HomepageSettings::class);
        $settings->template = (string) $stored;
        $settings->save();

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame(TemplateRegistry::FALLBACK, $props['template']);
    }

    public function test_a_draft_preview_is_honoured_for_a_settings_editor_and_never_persisted(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->get('/?template=minimal')->assertOk()->viewData('page')['props'];

        $this->assertSame('minimal', $props['template']);
        $this->assertSame('classic', app(HomepageSettings::class)->template);
    }

    public function test_a_draft_preview_is_ignored_for_a_guest(): void
    {
        $props = $this->get('/?template=minimal')->assertOk()->viewData('page')['props'];

        $this->assertSame('classic', $props['template']);
    }

    public function test_a_draft_preview_is_ignored_for_a_user_without_the_ability(): void
    {
        $this->actingAsUser();

        $props = $this->get('/?template=minimal')->assertOk()->viewData('page')['props'];

        $this->assertSame('classic', $props['template']);
    }

    public function test_an_unknown_draft_preview_key_still_degrades(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->get('/?template=nope')->assertOk()->viewData('page')['props'];

        $this->assertSame('classic', $props['template']);
    }

    public function test_supports_filters_the_section_order_the_server_ships(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->template = 'minimal';
        $settings->save();

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $minimal = TemplateRegistry::get('minimal');

        $this->assertNotNull($minimal);
        $this->assertNotEmpty($props['sectionOrder']);

        foreach ($props['sectionOrder'] as $section) {
            $this->assertTrue(
                $minimal->supportsSection($section),
                "Section [{$section}] was shipped to a template that does not render it.",
            );
        }

        $this->assertNotContains('pricing', $props['sectionOrder']);
    }

    public function test_ids_cover_every_declaration_and_drive_the_validation_rule(): void
    {
        $ids = TemplateRegistry::ids();

        $this->assertContains('classic', $ids);
        $this->assertSame(count($ids), count(array_unique($ids)));
        $this->assertCount(count(config('myra.landing.templates')), $ids);
    }

    public function test_forget_and_flush_leave_the_registry_recoverable(): void
    {
        $this->assertTrue(TemplateRegistry::has('classic'));

        TemplateRegistry::forget('core');
        $this->assertFalse(TemplateRegistry::has('classic'));

        TemplateRegistry::flush();
        $this->assertTrue(TemplateRegistry::has('classic'));
        $this->assertNull(TemplateRegistry::get('not-a-template'));
    }

    public function test_a_contributed_template_defaults_its_i18n_keys_from_its_own_key(): void
    {
        $schema = HomepageTemplate::make('gallery')->toClientSchema();

        $this->assertSame('landing.templates.gallery.title', $schema['titleKey']);
        $this->assertSame('landing.templates.gallery.description', $schema['descriptionKey']);
        $this->assertSame(HomepageTemplate::FALLBACK_COMPONENT, $schema['component']);
    }

    public function test_every_thumbnail_the_registry_advertises_exists_on_disk(): void
    {
        foreach (TemplateRegistry::all() as $template) {
            $thumbnail = $template->thumbnailValue();

            $this->assertNotNull($thumbnail, "Template [{$template->key()}] has no thumbnail.");
            $this->assertFileExists(public_path(ltrim($thumbnail, '/')));
        }
    }
}
