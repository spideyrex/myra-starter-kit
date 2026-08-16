<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\SectionRegistry;
use App\Homepage\Sections\SectionType;
use Tests\TestCase;

/**
 * The five starter types this bundle contributes, and the wiring that carries
 * them: a separate config key, a renderer per key, and a translated label in
 * every locale.
 */
class SectionLibraryTest extends TestCase
{
    private const TYPES = ['rich_text', 'image', 'stats', 'faq', 'divider'];

    /** @return array<int,class-string> */
    private function declaredClasses(): array
    {
        return (array) config('myra.pagebuilder_extra.extra_sections', []);
    }

    private function locale(string $locale): array
    {
        $path = resource_path("js/i18n/locales/{$locale}.json");

        $this->assertFileExists($path);

        return json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
    }

    private function message(array $catalogue, string $key): mixed
    {
        $node = $catalogue;

        foreach (explode('.', $key) as $segment) {
            if (! is_array($node) || ! array_key_exists($segment, $node)) {
                return null;
            }

            $node = $node[$segment];
        }

        return $node;
    }

    public function test_every_declared_class_defines_a_section_type(): void
    {
        $this->assertNotEmpty($this->declaredClasses());

        foreach ($this->declaredClasses() as $class) {
            $this->assertTrue(class_exists($class), "{$class} is registered but does not exist.");
            $this->assertTrue(method_exists($class, 'define'), "{$class} must expose define().");
            $this->assertInstanceOf(SectionType::class, $class::define());
        }
    }

    public function test_the_extra_config_key_is_separate_from_the_core_list(): void
    {
        $core = (array) config('myra.pagebuilder.sections', []);

        foreach ($this->declaredClasses() as $class) {
            $this->assertNotContains($class, $core, 'A bundle must not appear in the other bundle\'s array.');
        }
    }

    public function test_the_registry_carries_every_starter_type(): void
    {
        foreach (self::TYPES as $key) {
            $this->assertTrue(SectionRegistry::has($key), "{$key} must be registered.");
        }
    }

    public function test_every_starter_type_has_a_renderer_named_after_its_key(): void
    {
        foreach (self::TYPES as $key) {
            $this->assertFileExists(
                resource_path("js/Pages/Public/Templates/_shared/sections/{$key}.vue"),
                "The stored `type` must map straight to a component file for {$key}.",
            );
        }
    }

    public function test_a_select_field_never_defaults_to_an_empty_string(): void
    {
        foreach (self::TYPES as $key) {
            $schema = SectionRegistry::get($key)?->toClientSchema() ?? [];

            foreach ($schema['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') !== 'select') {
                    continue;
                }

                $this->assertNotSame('', $field['default'], "reka-ui rejects an empty value: {$key}.{$field['name']}");
                $this->assertContains($field['default'], $field['options'] ?? []);
            }
        }
    }

    public function test_every_label_and_description_resolves_in_all_three_locales(): void
    {
        foreach (['en', 'ms', 'zh'] as $locale) {
            $catalogue = $this->locale($locale);

            foreach (self::TYPES as $key) {
                foreach (['.label', '.description'] as $suffix) {
                    $path = "pageBuilder.sections.{$key}{$suffix}";
                    $value = $this->message($catalogue, $path);

                    $this->assertIsString($value, "{$locale} is missing {$path}");
                    $this->assertNotSame('', $value);
                    $this->assertStringNotContainsString('{', $value, "A literal brace throws at render: {$path}");
                }
            }
        }
    }

    public function test_the_four_catalogue_groups_are_translated_everywhere(): void
    {
        foreach (['en', 'ms', 'zh'] as $locale) {
            $catalogue = $this->locale($locale);

            foreach (['content', 'proof', 'conversion', 'layout'] as $group) {
                $this->assertIsString(
                    $this->message($catalogue, "pageBuilder.sections.groups.{$group}"),
                    "{$locale} is missing the {$group} group label.",
                );
            }
        }
    }

    public function test_the_image_type_requires_alternative_text(): void
    {
        $fields = SectionRegistry::get('image')?->toClientSchema()['fields'] ?? [];
        $alt = collect($fields)->firstWhere('name', 'alt');

        $this->assertNotNull($alt, 'The image type must declare alt text.');
        $this->assertTrue($alt['required'], 'Alt text is required, not optional.');
    }
}
