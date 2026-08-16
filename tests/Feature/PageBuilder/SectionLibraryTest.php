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

    /**
     * The library is DECLARED in its own key and MERGED into the one the
     * registry reads. Declaring it somewhere nobody reads is not registration.
     */
    public function test_every_declared_class_actually_reaches_the_registry(): void
    {
        // Asserts the OUTCOME, not the mechanism. This previously required the
        // starter key to be merged into myra.pagebuilder.sections by a service
        // provider; SectionRegistry::seed() already spreads both keys, so that
        // merge counted every extra twice. What matters is that a declared
        // class is registered — not which array carried it there.
        $ids = SectionRegistry::ids();

        $this->assertNotEmpty($ids, 'The registry must carry the starter library.');

        foreach ($this->declaredClasses() as $class) {
            $this->assertContains(
                $class::define()->key(),
                $ids,
                "{$class} is declared but never reaches the registry.",
            );
        }

        $this->assertSame(
            count($ids),
            count(array_unique($ids)),
            'Seeding must be idempotent — a section must not be registered twice.',
        );
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

    private function renderer(string $key): string
    {
        $path = resource_path("js/Pages/Public/Templates/_shared/sections/{$key}.vue");

        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    /**
     * A renderer reading a key the declaration does not have (image_url for a
     * declared image_path) is invisible to a suite that hand-authors payloads.
     */
    public function test_every_declared_field_name_is_read_by_its_renderer(): void
    {
        foreach (self::TYPES as $key) {
            $source = $this->renderer($key);

            foreach (SectionRegistry::get($key)?->toClientSchema()['fields'] ?? [] as $field) {
                $name = (string) ($field['name'] ?? '');

                if ($name === '') {
                    continue;
                }

                $this->assertStringContainsString(
                    $name,
                    $source,
                    "{$key}.vue never reads the declared field `{$name}`.",
                );
            }
        }
    }

    /** Vue does not sanitise attributes: an authored URL is untrusted input. */
    public function test_a_renderer_that_binds_a_url_routes_it_through_the_scheme_allowlist(): void
    {
        foreach (self::TYPES as $key) {
            $source = $this->renderer($key);

            if (! str_contains($source, ':href') && ! str_contains($source, ':src')) {
                continue;
            }

            $this->assertStringContainsString(
                'useSafeUrl',
                $source,
                "{$key}.vue binds a URL attribute without the scheme allowlist.",
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
