<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\SectionField;
use App\Homepage\Sections\SectionNormalizer;
use App\Homepage\Sections\SectionRegistry;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The degradation matrix. normalize() is the load-bearing function of the
 * public front door: it must return a well-formed list and NEVER throw, for
 * any input whatsoever.
 */
class SectionNormalizerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        SectionRegistry::flush();
    }

    /** @return array<string,array{0:mixed}> */
    public static function hostileInput(): array
    {
        return [
            'null' => [null],
            'a string' => ['a string'],
            'an int' => [42],
            'a float' => [1.5],
            'a bool' => [true],
            'empty list' => [[]],
            'an assoc array' => [['hero' => ['type' => 'hero']]],
            'an object' => [(object) ['type' => 'hero']],
            'a list of nothing' => [[[]]],
            'a list of scalars' => [['hero', 7, null]],
            'a numeric type' => [[['type' => 123]]],
            'an empty type' => [[['type' => '']]],
            'an unknown type' => [[['type' => 'nope_not_real']]],
            'data is a string' => [[['type' => 'hero', 'data' => 'a string']]],
            'a field holding an array' => [[['type' => 'hero', 'data' => ['title' => ['a' => 1]]]]],
            'a list field holding a string' => [[['type' => 'features', 'data' => ['items' => 'x']]]],
            'a list field holding scalars' => [[['type' => 'features', 'data' => ['items' => [1, 2, 3]]]]],
            'a bogus variant' => [[['type' => 'hero', 'variant' => ['align' => 'sideways', 'bogus' => 1]]]],
            'a variant that is a string' => [[['type' => 'hero', 'variant' => 'center']]],
            'a disabled row' => [[['type' => 'hero', 'enabled' => false]]],
            'a NAN number' => [[['type' => 'hero', 'data' => ['title' => NAN]]]],
            'an INF number' => [[['type' => 'hero', 'data' => ['title' => INF]]]],
        ];
    }

    /** @dataProvider hostileInput */
    public function test_normalize_never_throws_and_always_returns_a_list(mixed $raw): void
    {
        $out = SectionNormalizer::normalize($raw);

        $this->assertIsArray($out);
        $this->assertTrue(array_is_list($out));

        foreach ($out as $row) {
            $this->assertIsArray($row);
            $this->assertIsString($row['id']);
            $this->assertNotSame('', $row['id']);
            $this->assertIsString($row['type']);
            $this->assertIsArray($row['data']);
            $this->assertIsArray($row['variant']);
            $this->assertArrayNotHasKey('enabled', $row, 'Disabled rows are filtered, not shipped.');
        }
    }

    public function test_a_deeply_nested_array_is_flattened_away_rather_than_recursed(): void
    {
        $nested = 'leaf';

        for ($i = 0; $i < 20; $i++) {
            $nested = [$nested];
        }

        $out = SectionNormalizer::normalize([['type' => 'hero', 'data' => ['title' => $nested]]]);

        $this->assertCount(1, $out);
        $this->assertSame('', $out[0]['data']['title']);
    }

    public function test_an_unknown_type_is_dropped_from_the_payload(): void
    {
        $out = SectionNormalizer::normalize([
            ['type' => 'hero', 'data' => ['title' => 'Kept']],
            ['type' => 'nope_not_real', 'data' => ['title' => 'Dropped']],
            ['type' => 'cta', 'data' => ['title' => 'Also kept']],
        ]);

        $this->assertSame(['hero', 'cta'], array_column($out, 'type'));
    }

    public function test_a_disabled_row_is_absent_and_a_missing_flag_means_enabled(): void
    {
        $out = SectionNormalizer::normalize([
            ['type' => 'hero', 'enabled' => false, 'data' => ['title' => 'Hidden']],
            ['type' => 'cta', 'data' => ['title' => 'Shown']],
        ]);

        $this->assertSame(['cta'], array_column($out, 'type'));
    }

    public function test_the_list_is_truncated_to_the_hard_cap(): void
    {
        $raw = array_fill(0, 500, ['type' => 'hero', 'data' => ['title' => 'x']]);

        $this->assertCount(SectionNormalizer::MAX_BLOCKS, SectionNormalizer::normalize($raw));
    }

    public function test_a_missing_id_is_synthesised_and_never_collides(): void
    {
        $out = SectionNormalizer::normalize([
            ['type' => 'hero'],
            ['type' => 'cta', 'id' => ''],
            ['type' => 'features', 'id' => '01JBQ8Z5X3K7WQ2M4N6P8R0T'],
        ]);

        $ids = array_column($out, 'id');

        $this->assertSame($ids, array_unique($ids));
        $this->assertSame('01JBQ8Z5X3K7WQ2M4N6P8R0T', $ids[2]);
    }

    public function test_every_declared_field_is_present_and_typed_even_when_the_author_sent_nothing(): void
    {
        $out = SectionNormalizer::normalize([['type' => 'pricing']]);

        $this->assertSame('', $out[0]['data']['title']);
        $this->assertSame([], $out[0]['data']['plans']);
    }

    public function test_undeclared_keys_are_dropped(): void
    {
        $out = SectionNormalizer::normalize([
            ['type' => 'hero', 'data' => ['title' => 'A', 'sneaky' => 'B', 'id' => 'C', 'enabled' => false]],
        ]);

        $this->assertArrayNotHasKey('sneaky', $out[0]['data']);
        $this->assertArrayNotHasKey('id', $out[0]['data']);
        $this->assertSame('A', $out[0]['data']['title']);
        $this->assertCount(1, $out, 'A data key must never shadow the envelope.');
    }

    public function test_a_hostile_url_is_neutralised_on_read_as_well_as_on_write(): void
    {
        $out = SectionNormalizer::normalize([
            ['type' => 'hero', 'data' => ['cta_url' => 'javascript:alert(1)']],
        ]);

        $this->assertSame('', $out[0]['data']['cta_url']);
    }

    public function test_list_rows_are_coerced_field_by_field_and_capped(): void
    {
        $out = SectionNormalizer::normalize([[
            'type' => 'pricing',
            'data' => ['plans' => array_fill(0, 20, ['name' => 'Pro', 'features' => ['not', 'a', 'string']])],
        ]]);

        $plans = $out[0]['data']['plans'];

        $this->assertCount(6, $plans, 'The declared max caps the list.');
        $this->assertSame('', $plans[0]['features'], 'PricingTable.vue calls split() on this — it must be a string.');
        $this->assertFalse($plans[0]['highlighted']);
        $this->assertSame('', $plans[0]['cta_url']);
    }

    public function test_an_unknown_variant_key_is_dropped_and_a_bad_value_falls_back(): void
    {
        $out = SectionNormalizer::normalize([[
            'type' => 'hero',
            'variant' => ['align' => 'sideways', 'bogus' => 1, 'compact' => 'yes'],
        ]]);

        $this->assertSame(['align' => 'center', 'compact' => true], $out[0]['variant']);
    }

    public function test_an_absent_variant_key_stays_absent_so_the_template_still_wins(): void
    {
        $out = SectionNormalizer::normalize([['type' => 'hero', 'variant' => ['compact' => true]]]);

        $this->assertSame(['compact' => true], $out[0]['variant']);
        $this->assertArrayNotHasKey('align', $out[0]['variant']);
    }

    public function test_an_image_path_that_is_not_on_disk_resolves_to_a_null_url(): void
    {
        Storage::fake('public');

        $out = SectionNormalizer::normalize([
            ['type' => 'hero', 'data' => ['image_path' => 'homepage/missing.webp']],
        ]);

        $this->assertSame('homepage/missing.webp', $out[0]['data']['image_path']);
        $this->assertNull($out[0]['data']['image_url'], 'Never a URL for a file that is not there.');
    }

    public function test_an_image_path_that_exists_resolves_to_a_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('homepage/hero.webp', 'not-really-an-image');

        $out = SectionNormalizer::normalize([
            ['type' => 'hero', 'data' => ['image_path' => 'homepage/hero.webp']],
        ]);

        $this->assertIsString($out[0]['data']['image_url']);
        $this->assertStringContainsString('homepage/hero.webp', $out[0]['data']['image_url']);
    }

    /**
     * No core type declares a number today, so the coercion is proved directly:
     * NAN and INF must never reach json_encode, which throws on them.
     */
    public function test_a_number_field_refuses_nan_and_infinity(): void
    {
        $field = SectionField::number('weight');

        $this->assertSame(0, $field->coerce(NAN));
        $this->assertSame(0, $field->coerce(INF));
        $this->assertSame(0, $field->coerce(-INF));
        $this->assertSame(0, $field->coerce('not a number'));
        $this->assertSame(0, $field->coerce(['a' => 1]));
        $this->assertSame(7, $field->coerce('7'));
        $this->assertSame(1.5, $field->coerce(1.5));
        $this->assertIsString(json_encode(['weight' => $field->coerce(NAN)]));
    }

    public function test_a_traversal_shaped_image_path_is_refused(): void
    {
        Storage::fake('public');

        $out = SectionNormalizer::normalize([
            ['type' => 'hero', 'data' => ['image_path' => '../../.env']],
        ]);

        $this->assertSame('', $out[0]['data']['image_path']);
        $this->assertNull($out[0]['data']['image_url']);
    }
}
