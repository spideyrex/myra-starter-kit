<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\LegacyHomepageBlocks;
use App\Homepage\Sections\SectionRegistry;
use App\Homepage\TemplateRegistry;
use App\Settings\HomepageSettings;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The backward-compatibility contract.
 *
 * There is no SSR, so "renders equivalently" is proved where the two paths
 * actually differ: the payload each one hands the renderer. The visible text
 * of every section is reconstructed from the legacy props and from the block
 * props independently, and the two sequences must be identical.
 */
class LegacyBlockParityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
        SectionRegistry::flush();
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function rowText(array $rows, array $keys): array
    {
        $text = [];

        foreach ($rows as $row) {
            foreach ($keys as $key) {
                $text[] = (string) ($row[$key] ?? '');
            }
        }

        return $text;
    }

    /** The strings OrderedSections.vue puts on screen, in render order. */
    private function legacyText(array $settings, array $order): array
    {
        $enabled = [
            'hero' => true,
            'features' => (bool) $settings['features_enabled'],
            'testimonials' => (bool) $settings['testimonials_enabled'],
            'pricing' => (bool) $settings['pricing_enabled'],
            'cta' => (bool) $settings['cta_enabled'],
        ];

        $text = [];

        foreach ($order as $key) {
            if (! ($enabled[$key] ?? false)) {
                continue;
            }

            $text[] = $key;

            $text = [...$text, ...match ($key) {
                'hero' => [$settings['hero_title'], $settings['hero_subtitle'], $settings['hero_cta_text']],
                'features' => [
                    $settings['features_title'],
                    $settings['features_subtitle'],
                    ...$this->rowText($settings['features'], ['icon', 'title', 'description']),
                ],
                'testimonials' => [
                    $settings['testimonials_title'],
                    $settings['testimonials_subtitle'],
                    ...$this->rowText($settings['testimonials'], ['name', 'role', 'quote']),
                ],
                'pricing' => [
                    $settings['pricing_title'],
                    $settings['pricing_subtitle'],
                    ...$this->rowText($settings['pricing_plans'], ['name', 'price', 'period', 'features', 'cta_text']),
                ],
                'cta' => [$settings['cta_title'], $settings['cta_subtitle'], $settings['cta_button_text']],
                default => [],
            }];
        }

        return $text;
    }

    /** The same strings, read out of the block payload the server ships. */
    private function blockText(array $blocks): array
    {
        $text = [];

        foreach ($blocks as $block) {
            $data = $block['data'];
            $text[] = $block['type'];

            $text = [...$text, ...match ($block['type']) {
                'hero' => [$data['title'], $data['subtitle'], $data['cta_text']],
                'features' => [
                    $data['title'],
                    $data['subtitle'],
                    ...$this->rowText($data['items'], ['icon', 'title', 'description']),
                ],
                'testimonials' => [
                    $data['title'],
                    $data['subtitle'],
                    ...$this->rowText($data['items'], ['name', 'role', 'quote']),
                ],
                'pricing' => [
                    $data['title'],
                    $data['subtitle'],
                    ...$this->rowText($data['plans'], ['name', 'price', 'period', 'features', 'cta_text']),
                ],
                'cta' => [$data['title'], $data['subtitle'], $data['button_text']],
                default => [],
            }];
        }

        return $text;
    }

    public function test_the_pre_builder_homepage_renders_equivalently_after_migration(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->blocks = [];
        $settings->save();

        $before = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame([], $before['blocks'], 'An empty list must take the legacy path.');

        $legacy = $this->legacyText($before['settings'], $before['sectionOrder']);
        $this->assertNotEmpty($legacy);

        $settings = app(HomepageSettings::class);
        $settings->blocks = LegacyHomepageBlocks::fromSettings($settings);
        $settings->save();

        $after = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertNotEmpty($after['blocks'], 'A converted list must take the block path.');
        $this->assertSame(
            $legacy,
            $this->blockText($after['blocks']),
            'The converted page must put exactly the same words on screen, in the same order.',
        );
    }

    public function test_conversion_honours_the_stored_section_order(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->section_order = ['cta', 'pricing', 'hero'];
        $settings->save();

        $types = array_column(LegacyHomepageBlocks::fromSettings(app(HomepageSettings::class)), 'type');

        $this->assertSame(
            ['cta', 'pricing', 'hero', 'features', 'testimonials'],
            $types,
            'The stored order leads, and anything it forgot is appended rather than dropped.',
        );
    }

    public function test_a_disabled_section_converts_to_a_hidden_block_rather_than_disappearing(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->features_enabled = false;
        $settings->save();

        $blocks = LegacyHomepageBlocks::fromSettings(app(HomepageSettings::class));
        $features = collect($blocks)->firstWhere('type', 'features');

        $this->assertNotNull($features, 'A disabled section must survive as a hidden block the author can un-hide.');
        $this->assertFalse($features['enabled']);
        $this->assertNotSame('', $features['data']['title']);

        $settings = app(HomepageSettings::class);
        $settings->blocks = $blocks;
        $settings->save();

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertNotContains('features', array_column($props['blocks'], 'type'));
        $this->assertContains('hero', array_column($props['blocks'], 'type'));
    }

    public function test_the_seeder_and_the_migration_produce_the_same_block_list(): void
    {
        $rows = DB::table('settings')
            ->where('group', 'homepage')
            ->pluck('payload', 'name')
            ->map(fn ($payload) => json_decode((string) $payload, true))
            ->all();

        $seeded = $rows['blocks'];
        $converted = LegacyHomepageBlocks::fromRows($rows);

        $this->assertNotEmpty($seeded, 'The seeder must ship a converted block list.');
        $this->assertSame($this->withoutIds($converted), $this->withoutIds($seeded));
    }

    public function test_conversion_is_idempotent(): void
    {
        $settings = app(HomepageSettings::class);

        $this->assertSame(
            $this->withoutIds(LegacyHomepageBlocks::fromSettings($settings)),
            $this->withoutIds(LegacyHomepageBlocks::fromSettings($settings)),
        );
    }

    public function test_a_database_with_no_homepage_content_converts_to_nothing(): void
    {
        $this->assertSame([], LegacyHomepageBlocks::fromRows([]));
        $this->assertSame([], LegacyHomepageBlocks::fromRows(['template' => 'classic']));
    }

    public function test_every_id_the_conversion_assigns_is_unique(): void
    {
        $ids = array_column(LegacyHomepageBlocks::fromSettings(app(HomepageSettings::class)), 'id');

        $this->assertCount(5, $ids);
        $this->assertSame($ids, array_unique($ids));

        foreach ($ids as $id) {
            $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $id, 'Ids are ULIDs, never an index.');
        }
    }

    /** @return array<int,array<string,mixed>> */
    private function withoutIds(array $blocks): array
    {
        return array_map(function (array $block) {
            unset($block['id']);

            return $block;
        }, $blocks);
    }
}
