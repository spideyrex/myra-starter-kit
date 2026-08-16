<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionNormalizer;
use App\Homepage\Sections\SectionRegistry;
use App\Homepage\Sections\SectionType;
use App\Homepage\Sections\SectionWriter;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * The write path's contract, exercised directly.
 *
 * SectionWriter::prepare() is what an editor's update endpoint calls, so every
 * promise it makes about authored input is asserted here rather than inferred
 * from the editor that happens to call it.
 */
class SectionWriterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SectionRegistry::flush();
        SectionRegistry::seed();
    }

    protected function tearDown(): void
    {
        SectionRegistry::flush();

        parent::tearDown();
    }

    /** A type that exercises html, url, required and required sub-fields. */
    private function registerProbeType(): void
    {
        SectionRegistry::add(
            SectionType::make('probe')
                ->fields(
                    SectionField::text('headline')->required(),
                    SectionField::html('body'),
                    SectionField::url('link'),
                    SectionField::list(
                        'rows',
                        SectionField::text('label')->required(),
                        SectionField::html('note'),
                    )->max(2),
                )
                ->variants(['tone' => ['light', 'dark'], 'boxed' => 'bool']),
            'test',
        );
    }

    private function errorsOf(callable $fn): array
    {
        try {
            $fn();
        } catch (ValidationException $e) {
            return $e->errors();
        }

        $this->fail('Expected a ValidationException.');
    }

    public function test_an_empty_payload_is_an_empty_page(): void
    {
        $this->assertSame([], SectionWriter::prepare(null));
        $this->assertSame([], SectionWriter::prepare(''));
        $this->assertSame([], SectionWriter::prepare([]));
    }

    public function test_a_payload_that_is_not_an_ordered_list_is_rejected(): void
    {
        $this->assertArrayHasKey('blocks', $this->errorsOf(fn () => SectionWriter::prepare('nope')));
        $this->assertArrayHasKey('blocks', $this->errorsOf(fn () => SectionWriter::prepare(['a' => ['type' => 'hero']])));
    }

    public function test_more_blocks_than_a_page_may_hold_is_rejected(): void
    {
        $rows = array_fill(0, SectionNormalizer::MAX_BLOCKS + 1, ['type' => 'hero', 'data' => []]);

        $this->assertArrayHasKey('blocks', $this->errorsOf(fn () => SectionWriter::prepare($rows)));
        $this->assertCount(
            SectionNormalizer::MAX_BLOCKS,
            SectionWriter::prepare(array_slice($rows, 0, SectionNormalizer::MAX_BLOCKS)),
        );
    }

    public function test_a_missing_id_becomes_a_ulid_and_an_authored_id_survives(): void
    {
        $out = SectionWriter::prepare([
            ['type' => 'hero', 'data' => ['title' => 'A']],
            ['id' => '  keep-me  ', 'type' => 'cta', 'data' => []],
        ]);

        $this->assertMatchesRegularExpression('/^[0-9A-Z]{26}$/', $out[0]['id']);
        $this->assertSame('keep-me', $out[1]['id'], 'An authored id is trimmed, never replaced.');
    }

    public function test_an_undeclared_key_is_dropped_and_a_missing_one_gets_its_default(): void
    {
        $out = SectionWriter::prepare([[
            'type' => 'hero',
            'data' => ['title' => 'Kept', 'sneaky' => 'dropped'],
        ]]);

        $this->assertSame('Kept', $out[0]['data']['title']);
        $this->assertArrayNotHasKey('sneaky', $out[0]['data']);
        $this->assertSame('', $out[0]['data']['cta_url'], 'Every declared field is present after a write.');
    }

    public function test_a_list_is_capped_at_the_declared_row_count(): void
    {
        $items = array_map(fn (int $n) => ['title' => "Item {$n}"], range(1, 30));

        $out = SectionWriter::prepare([['type' => 'features', 'data' => ['items' => $items]]]);

        $this->assertCount(12, $out[0]['data']['items'], 'FeaturesSectionType declares max(12).');
        $this->assertSame('Item 1', $out[0]['data']['items'][0]['title']);
    }

    public function test_max_per_page_is_advisory_so_two_heroes_survive_a_round_trip(): void
    {
        $out = SectionWriter::prepare([
            ['type' => 'hero', 'data' => ['title' => 'First']],
            ['type' => 'hero', 'data' => ['title' => 'Second']],
        ]);

        $this->assertCount(2, $out);
        $this->assertSame(1, SectionRegistry::get('hero')->maxPerPageValue());
        $this->assertSame(['First', 'Second'], array_column(array_column($out, 'data'), 'title'));
    }

    public function test_an_unknown_type_is_quarantined_verbatim_rather_than_dropped(): void
    {
        $out = SectionWriter::prepare([[
            'id' => 'from-a-package',
            'type' => 'countdown',
            'enabled' => false,
            'data' => ['ends_at' => '2030-01-01', 'nested' => ['deep' => true]],
        ]]);

        $this->assertCount(1, $out, 'A disabled package is not a reason to destroy content.');
        $this->assertSame('countdown', $out[0]['type']);
        $this->assertSame('from-a-package', $out[0]['id']);
        $this->assertFalse($out[0]['enabled']);
        $this->assertSame(['ends_at' => '2030-01-01', 'nested' => ['deep' => true]], $out[0]['data']);

        $this->assertSame(
            [],
            SectionNormalizer::normalize($out),
            'Quarantined in storage, absent from the public payload.',
        );
    }

    public function test_a_malformed_row_and_a_typeless_row_are_addressed_by_index(): void
    {
        $errors = $this->errorsOf(fn () => SectionWriter::prepare([
            ['type' => 'hero', 'data' => []],
            'not-a-row',
            ['data' => []],
            ['type' => 42],
        ]));

        $this->assertArrayHasKey('blocks.1', $errors);
        $this->assertArrayHasKey('blocks.2.type', $errors);
        $this->assertArrayHasKey('blocks.3.type', $errors);
        $this->assertArrayNotHasKey('blocks.0', $errors);
    }

    public function test_a_required_field_is_keyed_so_the_editor_can_scroll_to_the_card(): void
    {
        $this->registerProbeType();

        $errors = $this->errorsOf(fn () => SectionWriter::prepare([
            ['type' => 'hero', 'data' => []],
            ['type' => 'probe', 'data' => ['headline' => '', 'rows' => [['label' => 'ok'], ['label' => '']]]],
        ]));

        $this->assertSame(
            ['blocks.1.data.headline', 'blocks.1.data.rows.1.label'],
            array_keys($errors),
        );
    }

    public function test_html_fields_are_sanitised_on_the_way_in(): void
    {
        $this->registerProbeType();

        $out = SectionWriter::prepare([[
            'type' => 'probe',
            'data' => [
                'headline' => 'Fine',
                'body' => '<p onclick="steal()">Hello<script>alert(1)</script></p>',
                'rows' => [['label' => 'x', 'note' => '<b>bold</b><iframe src="http://evil"></iframe>']],
            ],
        ]]);

        $body = $out[0]['data']['body'];
        $note = $out[0]['data']['rows'][0]['note'];

        $this->assertStringNotContainsString('<script', $body);
        $this->assertStringNotContainsString('onclick', $body);
        $this->assertStringContainsString('Hello', $body);

        $this->assertStringNotContainsString('<iframe', $note);
        $this->assertStringContainsString('bold', $note);
    }

    public function test_a_hostile_url_never_reaches_storage(): void
    {
        $this->registerProbeType();

        $out = SectionWriter::prepare([
            ['type' => 'probe', 'data' => ['headline' => 'x', 'link' => 'javascript:alert(document.cookie)']],
            ['type' => 'hero', 'data' => ['title' => 'x', 'cta_url' => "java\nscript:alert(1)"]],
            ['type' => 'cta', 'data' => ['button_url' => 'https://example.test/pricing']],
        ]);

        $this->assertSame('', $out[0]['data']['link']);
        $this->assertSame('', $out[1]['data']['cta_url']);
        $this->assertSame('https://example.test/pricing', $out[2]['data']['button_url']);
    }

    public function test_an_image_field_never_stores_a_scheme_or_a_traversal(): void
    {
        $out = SectionWriter::prepare([
            ['type' => 'hero', 'data' => ['image_path' => '../../.env']],
            ['type' => 'hero', 'data' => ['image_path' => 'https://evil.test/x.png']],
            ['type' => 'hero', 'data' => ['image_path' => '/homepage/ok.webp']],
        ]);

        $this->assertSame('', $out[0]['data']['image_path']);
        $this->assertSame('', $out[1]['data']['image_path']);
        $this->assertSame('homepage/ok.webp', $out[2]['data']['image_path']);
    }

    public function test_a_variant_is_restricted_to_the_declared_spec(): void
    {
        $this->registerProbeType();

        $out = SectionWriter::prepare([[
            'type' => 'probe',
            'data' => ['headline' => 'x'],
            'variant' => ['tone' => 'neon', 'boxed' => 'yes', 'undeclared' => 1],
        ]]);

        $this->assertSame(
            ['tone' => 'light', 'boxed' => true],
            $out[0]['variant'],
            'An unknown value falls back to the first option; an undeclared key is dropped.',
        );
    }

    public function test_enabled_defaults_to_true_and_is_coerced(): void
    {
        $out = SectionWriter::prepare([
            ['type' => 'hero', 'data' => []],
            ['type' => 'hero', 'enabled' => '0', 'data' => []],
            ['type' => 'hero', 'enabled' => 'true', 'data' => []],
            ['type' => 'hero', 'enabled' => ['nonsense'], 'data' => []],
        ]);

        $this->assertSame([true, false, true, false], array_column($out, 'enabled'));
    }

    public function test_what_the_writer_stores_is_what_the_normalizer_publishes(): void
    {
        $stored = SectionWriter::prepare([
            ['type' => 'hero', 'data' => ['title' => 'Round trip', 'cta_url' => '/go']],
            ['type' => 'cta', 'enabled' => false, 'data' => ['title' => 'Hidden']],
        ]);

        $published = SectionNormalizer::normalize($stored);

        $this->assertSame(['hero'], array_column($published, 'type'), 'A disabled block is stored but not published.');
        $this->assertSame('Round trip', $published[0]['data']['title']);
        $this->assertSame('/go', $published[0]['data']['cta_url']);
        $this->assertSame($stored[0]['id'], $published[0]['id']);
    }
}
