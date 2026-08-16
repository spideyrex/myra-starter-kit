<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionRegistry;
use App\Homepage\Sections\SectionType;
use Tests\TestCase;

class SectionRegistryTest extends TestCase
{
    private const SECTION_COMPONENTS = 'resources/js/Pages/Public/Templates/_shared/sections';

    protected function setUp(): void
    {
        parent::setUp();

        SectionRegistry::flush();
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

    public function test_every_registered_type_has_a_component_named_after_its_key(): void
    {
        foreach (SectionRegistry::all() as $type) {
            $this->assertFileExists(
                base_path(self::SECTION_COMPONENTS."/{$type->key()}.vue"),
                "Section [{$type->key()}] has no renderer. The filename IS the type key.",
            );
        }
    }

    public function test_every_component_file_has_a_registry_entry(): void
    {
        $files = array_map(
            fn (string $path) => basename($path, '.vue'),
            glob(base_path(self::SECTION_COMPONENTS.'/*.vue')) ?: [],
        );

        $ids = SectionRegistry::ids();

        sort($files);
        sort($ids);

        $this->assertSame(
            $ids,
            $files,
            'The section renderers drifted from the registry. Every file needs an entry, and every entry a file.',
        );
    }

    public function test_ids_cover_every_declaration(): void
    {
        $ids = SectionRegistry::ids();

        $this->assertContains('hero', $ids);
        $this->assertSame(count($ids), count(array_unique($ids)));
        $this->assertCount(
            count((array) config('myra.pagebuilder.sections')) + count((array) config('myra.pagebuilder_extra.extra_sections')),
            $ids,
        );
    }

    public function test_the_client_schema_carries_everything_the_editor_needs(): void
    {
        foreach (SectionRegistry::toClientSchema() as $row) {
            foreach (['key', 'labelKey', 'descriptionKey', 'icon', 'group', 'fields', 'variants', 'maxPerPage'] as $key) {
                $this->assertArrayHasKey($key, $row);
            }

            $this->assertContains($row['group'], SectionType::GROUPS);
            $this->assertNotSame('', $row['icon']);
            $this->assertIsArray($row['fields']);

            foreach ($row['fields'] as $field) {
                $this->assertArrayHasKey('name', $field);
                $this->assertArrayHasKey('type', $field);
                $this->assertArrayHasKey('labelKey', $field);
                $this->assertArrayHasKey('default', $field);
            }
        }
    }

    public function test_a_title_field_names_a_field_that_exists(): void
    {
        foreach (SectionRegistry::all() as $type) {
            $titleField = $type->titleFieldValue();

            if ($titleField === null) {
                continue;
            }

            $this->assertNotNull(
                $type->field($titleField),
                "Section [{$type->key()}] derives its card title from [{$titleField}], which it does not declare.",
            );
        }
    }

    /**
     * Bundle C owns pageBuilder.sections.*; this bundle only ships the keys.
     * Skipping until that namespace lands keeps the seam honest rather than
     * silently asserting nothing.
     */
    public function test_every_label_and_description_key_resolves_in_all_three_locales(): void
    {
        $locales = ['en' => $this->locale('en'), 'ms' => $this->locale('ms'), 'zh' => $this->locale('zh')];

        foreach ($locales as $code => $messages) {
            if (! isset($messages['pageBuilder']['sections'])) {
                $this->markTestSkipped("pageBuilder.sections.* is not in {$code}.json yet (owned by the section-library bundle).");
            }
        }

        foreach (SectionRegistry::all() as $type) {
            foreach ([$type->resolvedLabelKey(), $type->resolvedDescriptionKey()] as $key) {
                foreach ($locales as $code => $messages) {
                    $this->assertTrue(
                        $this->resolves($messages, $key),
                        "Key [{$key}] is missing from {$code}.json — locale parity is a merge gate.",
                    );
                }
            }
        }
    }

    public function test_the_render_namespace_this_bundle_owns_resolves_everywhere(): void
    {
        foreach (['en', 'ms', 'zh'] as $code) {
            $this->assertTrue(
                $this->resolves($this->locale($code), 'pageBuilder.render.unavailable'),
                "pageBuilder.render.unavailable is missing from {$code}.json.",
            );
        }
    }

    public function test_defaults_are_type_aware_and_never_an_empty_select(): void
    {
        foreach (SectionRegistry::all() as $type) {
            $defaults = $type->defaults();

            foreach ($type->fieldList() as $field) {
                $default = $defaults[$field->name()];

                match ($field->typeValue()) {
                    'bool' => $this->assertIsBool($default),
                    'list' => $this->assertSame([], $default),
                    'number' => $this->assertIsNumeric($default),
                    // reka-ui rejects <SelectItem value="">.
                    'select' => $this->assertNotSame('', $default),
                    default => $this->assertIsString($default),
                };
            }
        }
    }

    public function test_a_type_declaring_a_select_defaults_to_its_first_option(): void
    {
        $field = SectionField::select('style', ['rule', 'space']);

        $this->assertSame('rule', $field->defaultValue());
        $this->assertSame('space', $field->coerce('space'));
        $this->assertSame('rule', $field->coerce('nonsense'));
        $this->assertSame('rule', $field->coerce(null));
    }

    public function test_forget_and_flush_leave_the_registry_recoverable(): void
    {
        $this->assertTrue(SectionRegistry::has('hero'));

        SectionRegistry::forget('core');
        $this->assertFalse(SectionRegistry::has('hero'));

        SectionRegistry::flush();
        $this->assertTrue(SectionRegistry::has('hero'));
        $this->assertNull(SectionRegistry::get('not-a-section'));
    }

    public function test_seed_is_idempotent(): void
    {
        SectionRegistry::seed();
        $first = SectionRegistry::ids();

        SectionRegistry::seed();
        SectionRegistry::seed();

        $this->assertSame($first, SectionRegistry::ids());
    }

    public function test_a_contributed_type_derives_its_i18n_keys_from_its_own_key(): void
    {
        $schema = SectionType::make('gallery')->toClientSchema();

        $this->assertSame('pageBuilder.sections.gallery.label', $schema['labelKey']);
        $this->assertSame('pageBuilder.sections.gallery.description', $schema['descriptionKey']);
        $this->assertSame([], $schema['fields']);
    }

    public function test_a_field_label_key_is_derived_from_the_section_key(): void
    {
        $schema = SectionType::make('gallery')
            ->fields(SectionField::text('caption'), SectionField::list('items', SectionField::text('alt')))
            ->toClientSchema();

        $this->assertSame('pageBuilder.sections.gallery.fields.caption', $schema['fields'][0]['labelKey']);
        $this->assertSame('pageBuilder.sections.gallery.fields.items.alt', $schema['fields'][1]['of'][0]['labelKey']);
    }

    public function test_the_icon_allowlist_matches_what_the_public_renderer_imports(): void
    {
        $source = file_get_contents(base_path('resources/js/Pages/Public/Templates/_shared/FeatureGrid.vue'));

        foreach (SectionField::ICON_ALLOWLIST as $icon) {
            $this->assertStringContainsString(
                $icon,
                $source,
                "Icon [{$icon}] is offered to authors but FeatureGrid.vue does not import it.",
            );
        }
    }
}
