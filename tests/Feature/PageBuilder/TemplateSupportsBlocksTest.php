<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\LegacyHomepageBlocks;
use App\Homepage\Sections\SectionRegistry;
use App\Homepage\TemplateRegistry;
use App\Settings\HomepageSettings;
use Tests\TestCase;

/**
 * A template's `supports` list restricts BOTH render paths, not just the legacy
 * one — otherwise switching to the block model would make a template that never
 * showed pricing start showing it.
 *
 * The restriction covers the five LEGACY keys only: a package-contributed type
 * must never be hidden by a template declared before it existed.
 */
class TemplateSupportsBlocksTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
        SectionRegistry::flush();
    }

    protected function tearDown(): void
    {
        SectionRegistry::flush();

        parent::tearDown();
    }

    private function publish(string $template, array $blocks): array
    {
        $settings = app(HomepageSettings::class);
        $settings->template = $template;
        $settings->blocks = $blocks;
        $settings->save();

        return $this->get('/')->assertOk()->viewData('page')['props'];
    }

    private function legacyBlocks(): array
    {
        return LegacyHomepageBlocks::fromSettings(app(HomepageSettings::class));
    }

    public function test_classic_publishes_every_legacy_section(): void
    {
        $props = $this->publish('classic', $this->legacyBlocks());

        $this->assertSame(
            ['hero', 'features', 'testimonials', 'pricing', 'cta'],
            array_column($props['blocks'], 'type'),
        );
    }

    public function test_a_template_never_publishes_a_legacy_section_it_does_not_support(): void
    {
        // MinimalTemplate declares supports('hero', 'features', 'cta').
        $props = $this->publish('minimal', $this->legacyBlocks());

        $this->assertSame(['hero', 'features', 'cta'], array_column($props['blocks'], 'type'));
        $this->assertSame(
            $props['sectionOrder'],
            array_column($props['blocks'], 'type'),
            'Both render paths obey exactly the same template restriction.',
        );
    }

    public function test_a_type_the_template_never_heard_of_is_published_anyway(): void
    {
        SectionRegistry::seed();
        SectionRegistry::add(
            \App\Homepage\Sections\SectionType::make('marquee')->fields(
                \App\Homepage\Sections\SectionField::text('title'),
            ),
            'test',
        );

        $props = $this->publish('minimal', [
            ['id' => 'a', 'type' => 'pricing', 'data' => []],
            ['id' => 'b', 'type' => 'marquee', 'data' => ['title' => 'From a package']],
        ]);

        $this->assertSame(['marquee'], array_column($props['blocks'], 'type'));

        SectionRegistry::flush();
    }

    public function test_filtering_everything_out_hands_the_page_back_to_the_legacy_path(): void
    {
        $blocks = array_values(array_filter(
            $this->legacyBlocks(),
            fn (array $b) => in_array($b['type'], ['pricing', 'testimonials'], true),
        ));

        $this->assertCount(2, $blocks);

        $props = $this->publish('minimal', $blocks);

        $this->assertSame([], $props['blocks'], 'An empty list is the legacy path — never a blank page.');
        $this->assertNotEmpty($props['sectionOrder']);
    }
}
