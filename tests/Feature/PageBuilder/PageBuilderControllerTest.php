<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\SectionRegistry;
use App\Http\Requests\Admin\UpdatePageBlocksRequest;
use App\Settings\HomepageSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PageBuilderControllerTest extends TestCase
{
    /** A real 1x1 PNG. getimagesize() must accept exactly these bytes. */
    private const PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(SectionRegistry::class)) {
            $this->markTestSkipped('The v2.7 section model is not present on this branch.');
        }

        SectionRegistry::flush();
        SectionRegistry::seed();
    }

    // --- helpers -----------------------------------------------------------

    /** @return array<string,mixed> */
    private function schemaOf(string $type): array
    {
        foreach (SectionRegistry::toClientSchema() as $schema) {
            if (($schema['key'] ?? null) === $type) {
                return $schema;
            }
        }

        $this->fail("The registry does not declare a '{$type}' section.");
    }

    /**
     * The editor's defaultsFor(), server-side: type-aware defaults straight off
     * the shipped schema, never a hand-authored literal.
     *
     * @return array<string,mixed>
     */
    private function defaultsFor(string $type): array
    {
        $data = [];

        foreach ($this->schemaOf($type)['fields'] ?? [] as $field) {
            $data[$field['name']] = match ($field['type']) {
                'bool' => is_bool($field['default']) ? $field['default'] : false,
                'number' => is_numeric($field['default']) ? $field['default'] : 0,
                'list' => [],
                'select' => is_string($field['default']) && $field['default'] !== ''
                    ? $field['default']
                    : ($field['options'][0] ?? ''),
                default => is_string($field['default']) ? $field['default'] : '',
            };
        }

        return $data;
    }

    /** One row shaped exactly as useSectionList::add() shapes it. */
    private function blockOf(string $type, array $overrides = [], bool $enabled = true): array
    {
        return [
            'id' => strtoupper(str_pad(dechex(random_int(0, PHP_INT_MAX)), 26, '0', STR_PAD_LEFT)),
            'type' => $type,
            'enabled' => $enabled,
            'variant' => [],
            'data' => [...$this->defaultsFor($type), ...$overrides],
        ];
    }

    private function saveBlocks(array $blocks): TestResponse
    {
        return $this->put(route('admin.landing.builder.update'), ['blocks' => $blocks]);
    }

    /** @return array<int,array<string,mixed>> */
    private function storedBlocks(): array
    {
        $blocks = app(HomepageSettings::class)->toArray()['blocks'] ?? [];

        return is_array($blocks) ? $blocks : [];
    }

    private function uploadImage(UploadedFile $file): TestResponse
    {
        return $this->post(
            route('admin.landing.builder.image'),
            ['file' => $file],
            ['Accept' => 'application/json'],
        );
    }

    // --- authorisation -----------------------------------------------------

    public function test_every_builder_endpoint_is_gated_by_settings_edit(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.landing.builder.index'))->assertForbidden();
        $this->put(route('admin.landing.builder.update'), ['blocks' => []])->assertForbidden();
        $this->post(route('admin.landing.builder.convert'))->assertForbidden();
        $this->uploadImage(UploadedFile::fake()->createWithContent('a.png', base64_decode(self::PNG_1X1)))
            ->assertForbidden();
    }

    // --- index -------------------------------------------------------------

    public function test_the_editor_ships_the_real_registry_schema(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->get(route('admin.landing.builder.index'))->assertOk()->viewData('page')['props'];

        $this->assertSame(SectionRegistry::ids(), array_column($props['schemas'], 'key'));
        $this->assertSame(UpdatePageBlocksRequest::MAX_BLOCKS, $props['maxBlocks']);
        $this->assertIsArray($props['blocks']);

        foreach ($props['schemas'] as $schema) {
            foreach (['key', 'labelKey', 'descriptionKey', 'icon', 'group', 'fields'] as $key) {
                $this->assertArrayHasKey($key, $schema);
            }
        }
    }

    // --- update ------------------------------------------------------------

    public function test_a_list_built_the_way_the_editor_builds_it_round_trips(): void
    {
        $this->actingAsSuperAdmin();

        $hero = $this->blockOf('hero', ['title' => 'Ship faster', 'subtitle' => 'From the builder']);
        $cta = $this->blockOf('cta', ['title' => 'Start today']);

        $this->saveBlocks([$hero, $cta])->assertRedirect()->assertSessionHasNoErrors();

        $stored = $this->storedBlocks();

        $this->assertCount(2, $stored);
        $this->assertSame(['hero', 'cta'], array_column($stored, 'type'));
        $this->assertSame([$hero['id'], $cta['id']], array_column($stored, 'id'));
        $this->assertSame('Ship faster', $stored[0]['data']['title']);
        $this->assertSame('Start today', $stored[1]['data']['title']);

        // The editor reloads the list it just saved.
        $props = $this->get(route('admin.landing.builder.index'))->assertOk()->viewData('page')['props'];

        $this->assertSame([$hero['id'], $cta['id']], array_column($props['blocks'], 'id'));
    }

    public function test_a_reordered_and_hidden_list_survives_the_round_trip(): void
    {
        $this->actingAsSuperAdmin();

        $hero = $this->blockOf('hero', ['title' => 'Only hero']);
        $cta = $this->blockOf('cta', ['title' => 'Closing'], enabled: false);

        $this->saveBlocks([$cta, $hero])->assertRedirect()->assertSessionHasNoErrors();

        $stored = $this->storedBlocks();

        $this->assertSame(['cta', 'hero'], array_column($stored, 'type'));
        $this->assertFalse($stored[0]['enabled']);
        $this->assertTrue($stored[1]['enabled']);
    }

    public function test_more_than_one_hundred_blocks_is_rejected(): void
    {
        $this->actingAsSuperAdmin();

        $before = $this->storedBlocks();

        $blocks = [];
        for ($i = 0; $i <= UpdatePageBlocksRequest::MAX_BLOCKS; $i++) {
            $blocks[] = $this->blockOf('cta');
        }

        $this->saveBlocks($blocks)->assertSessionHasErrors('blocks');
        $this->assertSame($before, $this->storedBlocks());
    }

    public function test_a_row_without_a_type_is_rejected(): void
    {
        $this->actingAsSuperAdmin();

        $this->saveBlocks([['id' => 'X', 'data' => []]])->assertSessionHasErrors('blocks.0.type');
    }

    /** The editor addresses its cards by index, so the bag must too. */
    public function test_a_field_level_failure_addresses_the_offending_card(): void
    {
        $this->actingAsSuperAdmin();

        [$type, $field] = $this->firstRequiredField();

        if ($type === null) {
            $this->markTestSkipped('No registered section declares a required field.');
        }

        $before = $this->storedBlocks();

        $blocks = [
            $this->blockOf('cta'),
            $this->blockOf('cta'),
            $this->blockOf('cta'),
            $this->blockOf($type, [$field => '']),
        ];

        $this->saveBlocks($blocks)->assertSessionHasErrors("blocks.3.data.{$field}");
        $this->assertSame($before, $this->storedBlocks());
    }

    public function test_an_unknown_type_is_quarantined_rather_than_rejected(): void
    {
        $this->actingAsSuperAdmin();

        $row = [
            'id' => 'FROMAPACKAGE0000000000000A',
            'type' => 'from_a_package',
            'enabled' => true,
            'variant' => [],
            'data' => ['anything' => ['nested' => 1]],
        ];

        $this->saveBlocks([$this->blockOf('hero'), $row])->assertRedirect()->assertSessionHasNoErrors();

        $stored = $this->storedBlocks();

        $this->assertCount(2, $stored);
        $this->assertSame('from_a_package', $stored[1]['type']);
        $this->assertSame(['nested' => 1], $stored[1]['data']['anything']);
    }

    public function test_an_empty_list_is_accepted_so_the_author_can_revert(): void
    {
        $this->actingAsSuperAdmin();

        $this->saveBlocks([$this->blockOf('hero')])->assertRedirect();
        $this->assertCount(1, $this->storedBlocks());

        $this->saveBlocks([])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], $this->storedBlocks());
    }

    // --- convert -----------------------------------------------------------

    public function test_convert_returns_a_block_list_without_saving_anything(): void
    {
        $this->actingAsSuperAdmin();

        $before = $this->storedBlocks();

        $response = $this->postJson(route('admin.landing.builder.convert'))->assertOk();

        $blocks = $response->json('blocks');

        $this->assertIsArray($blocks);
        $this->assertNotEmpty($blocks, 'The seeded homepage must convert to at least one block.');
        $this->assertSame('hero', $blocks[0]['type'] ?? null);

        // Nothing was persisted: the author reviews it first.
        $this->assertSame($before, $this->storedBlocks());
    }

    // --- image -------------------------------------------------------------

    public function test_an_image_lands_on_the_public_disk_under_a_ulid_name(): void
    {
        Storage::fake('public');
        $this->actingAsSuperAdmin();

        // Named .jpg, but the BYTES are a PNG. The stored extension must follow
        // the bytes, never the client filename.
        $file = UploadedFile::fake()->createWithContent('portrait.jpg', base64_decode(self::PNG_1X1));

        $response = $this->uploadImage($file)->assertOk();

        $path = (string) $response->json('path');

        $this->assertMatchesRegularExpression('#^homepage/[0-9A-HJKMNP-TV-Z]{26}\.png$#', $path);
        $this->assertTrue(Storage::disk('public')->exists($path));
        $this->assertStringContainsString($path, (string) $response->json('url'));
        $this->assertStringNotContainsString('portrait', $path);
    }

    public function test_a_text_file_named_png_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsSuperAdmin();

        $this->uploadImage(UploadedFile::fake()->createWithContent('evil.png', 'not an image at all'))
            ->assertStatus(422);

        $this->assertEmpty(Storage::disk('public')->allFiles('homepage'));
    }

    public function test_a_file_that_only_claims_an_image_mime_is_rejected(): void
    {
        Storage::fake('public');
        $this->actingAsSuperAdmin();

        $this->uploadImage(UploadedFile::fake()->create('evil.png', 4, 'image/png'))->assertStatus(422);

        $this->assertEmpty(Storage::disk('public')->allFiles('homepage'));
    }

    /**
     * The first declared required field across the registry, or [null, null].
     *
     * @return array{0:?string,1:?string}
     */
    private function firstRequiredField(): array
    {
        foreach (SectionRegistry::toClientSchema() as $schema) {
            foreach ($schema['fields'] ?? [] as $field) {
                if (($field['required'] ?? false) === true && ($field['type'] ?? '') !== 'list') {
                    return [$schema['key'], $field['name']];
                }
            }
        }

        return [null, null];
    }
}
