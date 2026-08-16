<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\SectionRegistry;
use App\Homepage\TemplateRegistry;
use App\Settings\HomepageSettings;
use App\Support\Myra;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/**
 * MYRA v2.7 [D] — the "ensure it works" contract.
 *
 * Real endpoint in, real page out. Nothing here is a hand-authored literal: the
 * rows are assembled the way the editor assembles them, from the defaults the
 * server declares in SectionRegistry::toClientSchema(), and the assertions read
 * the props the public homepage actually shipped.
 *
 * The same payload is then held against tests/js/fixtures/page-builder-blocks.json
 * (see syncFixture), which is what the vitest spec mounts — so the client-side
 * proof is anchored to this run rather than to a literal someone typed.
 */
class EndToEndPageBuilderTest extends TestCase
{
    use SyncsPageBuilderFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        TemplateRegistry::flush();
        SectionRegistry::flush();
        SectionRegistry::seed();
    }

    /** @return array<string,array<string,mixed>> keyed by type */
    private function sectionSchemas(): array
    {
        $out = [];

        foreach (SectionRegistry::toClientSchema() as $schema) {
            $out[$schema['key']] = $schema;
        }

        return $out;
    }

    /**
     * The PHP twin of useSectionList().defaultsFor(): every declared field, at
     * the type the server declared, never a blanket empty string.
     *
     * @return array<string,mixed>
     */
    private function defaultsFor(string $type): array
    {
        $schema = $this->sectionSchemas()[$type] ?? null;

        $this->assertNotNull($schema, "Section type [{$type}] is not registered.");

        $data = [];

        foreach ($schema['fields'] as $field) {
            $data[$field['name']] = $field['default'];
        }

        return $data;
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function newRow(string $type, array $data = [], bool $enabled = true): array
    {
        return [
            'type' => $type,
            'enabled' => $enabled,
            'variant' => [],
            'data' => array_replace($this->defaultsFor($type), $data),
        ];
    }

    /** @param array<int,array<string,mixed>> $blocks */
    private function saveBlocks(array $blocks): \Illuminate\Testing\TestResponse
    {
        return $this->put(route('admin.landing.builder.update'), ['blocks' => $blocks]);
    }

    /** @return array<int,array<string,mixed>> the rows the public homepage shipped */
    private function publishedBlocks(): array
    {
        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertArrayHasKey('blocks', $props, 'The public homepage stopped shipping a blocks prop.');

        return $props['blocks'];
    }

    /** @return array<int,array<string,mixed>> the rows that reached storage */
    private function storedBlocks(): array
    {
        return app(HomepageSettings::class)->blocks ?? [];
    }

    /**
     * Server-assigned ids are ULIDs: fresh on every save, so a payload carrying
     * them can never be compared against a committed file. Replace them with
     * their position — the ids themselves are asserted in PHP (non-empty,
     * unique per row, and moved rather than reissued by a reorder).
     *
     * @param  array<int,array<string,mixed>>  $rows
     * @return array<int,array<string,mixed>>
     */
    private function withStableIds(array $rows): array
    {
        foreach ($rows as $i => $row) {
            $rows[$i]['id'] = "row-{$i}";
        }

        return $rows;
    }

    public function test_a_block_list_saved_through_the_admin_endpoint_renders_on_the_public_page(): void
    {
        $this->actingAsSuperAdmin();

        $blocks = [
            $this->newRow('hero', [
                'title' => 'Ship faster',
                'subtitle' => 'Compose the page out of sections.',
                'cta_text' => 'Start now',
                'cta_url' => '/register',
            ]),
            $this->newRow('features', [
                'title' => 'Everything included',
                'subtitle' => 'Three reasons.',
                'items' => [
                    ['icon' => 'Zap', 'title' => 'Fast', 'description' => 'Renders in one pass.'],
                    ['icon' => 'Shield', 'title' => 'Safe', 'description' => 'Sanitised on write and on render.'],
                    ['icon' => 'Layers', 'title' => 'Composable', 'description' => 'Any order, any repeats.'],
                ],
            ]),
            $this->newRow('cta', [
                'title' => 'Ready when you are',
                'subtitle' => 'Open the builder.',
                'button_text' => 'Open builder',
                'button_url' => Myra::adminPath('landing/builder'),
            ]),
        ];

        $this->saveBlocks($blocks)
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $stored = $this->storedBlocks();
        $this->assertCount(3, $stored);

        foreach ($stored as $row) {
            $this->assertIsString($row['id']);
            $this->assertNotSame('', $row['id'], 'Every saved row gets a server-assigned id.');
        }

        $response = $this->get('/')->assertOk();

        // The authored strings must be in the bytes that leave the server, not
        // merely in an array the controller built.
        $response->assertSee('Ship faster')
            ->assertSee('Compose the page out of sections.')
            ->assertSee('Renders in one pass.')
            ->assertSee('Open builder');

        $props = $response->viewData('page')['props'];
        $published = $props['blocks'];

        $this->assertSame(['hero', 'features', 'cta'], array_column($published, 'type'));
        $this->assertSame(array_column($stored, 'id'), array_column($published, 'id'));

        $this->assertSame('Ship faster', $published[0]['data']['title']);
        $this->assertSame('/register', $published[0]['data']['cta_url']);
        $this->assertSame('Everything included', $published[1]['data']['title']);
        $this->assertCount(3, $published[1]['data']['items']);
        $this->assertSame('Composable', $published[1]['data']['items'][2]['title']);
        $this->assertSame('Open builder', $published[2]['data']['button_text']);

        // Bind the SAVED payload to the JS side. tests/js/pageBuilderPayload.spec.ts
        // mounts Home.vue against the committed copy of this file, and syncFixture
        // asserts that every value in that copy is what the server just shipped —
        // so the client is proven against the server's own output. The two CI jobs
        // run on separate runners, so this assertion is the only thing standing
        // between them; it must never silently rewrite the file.
        $this->syncFixture('tests/js/fixtures/page-builder-blocks.json', [
            'settings' => $props['settings'],
            'template' => $props['template'],
            'templateOptions' => $props['templateOptions'],
            'sectionOrder' => $props['sectionOrder'],
            'blocks' => $this->withStableIds($published),
        ]);
    }

    /**
     * Every other case here fetches `/` with the admin session still attached.
     * The page is served to the public, so prove it there too.
     */
    public function test_a_saved_block_reaches_an_anonymous_visitor(): void
    {
        $this->actingAsSuperAdmin();

        $this->saveBlocks([$this->newRow('hero', ['title' => 'Anonymous eyes'])])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Auth::logout();
        $this->flushSession();

        $props = $this->get('/')->assertOk()->assertSee('Anonymous eyes')->viewData('page')['props'];

        $this->assertFalse($props['authenticated']);
        $this->assertSame('Anonymous eyes', $props['blocks'][0]['data']['title']);
    }

    public function test_two_heroes_and_a_reordered_list_survive_the_round_trip(): void
    {
        $this->actingAsSuperAdmin();

        $this->saveBlocks([
            $this->newRow('cta', ['title' => 'Closing line']),
            $this->newRow('hero', ['title' => 'First hero']),
            $this->newRow('hero', ['title' => 'Second hero']),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $published = $this->publishedBlocks();

        $this->assertSame(['cta', 'hero', 'hero'], array_column($published, 'type'));
        $this->assertSame('First hero', $published[1]['data']['title']);
        $this->assertSame('Second hero', $published[2]['data']['title']);

        $ids = array_column($published, 'id');
        $this->assertCount(3, array_unique($ids), 'Two rows of the same type must not share an id.');

        // Reorder by resaving the stored rows in a new order, ids and all.
        $stored = $this->storedBlocks();
        $this->saveBlocks([$stored[2], $stored[0], $stored[1]])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $reordered = $this->publishedBlocks();

        $this->assertSame(['hero', 'cta', 'hero'], array_column($reordered, 'type'));
        $this->assertSame('Second hero', $reordered[0]['data']['title']);
        $this->assertSame(
            [$ids[2], $ids[0], $ids[1]],
            array_column($reordered, 'id'),
            'A reorder must move rows, not reissue them.',
        );
    }

    public function test_a_disabled_section_is_saved_but_absent_from_the_public_payload(): void
    {
        $this->actingAsSuperAdmin();

        $this->saveBlocks([
            $this->newRow('hero', ['title' => 'Visible hero']),
            $this->newRow('pricing', ['title' => 'Hidden pricing'], enabled: false),
        ])->assertSessionHasNoErrors()->assertRedirect();

        $stored = $this->storedBlocks();

        $this->assertCount(2, $stored, 'A hidden section stays in storage so it can be un-hidden.');
        $this->assertFalse($stored[1]['enabled']);
        $this->assertSame('Hidden pricing', $stored[1]['data']['title']);

        $published = $this->publishedBlocks();

        $this->assertSame(['hero'], array_column($published, 'type'));
    }

    public function test_an_unknown_type_survives_a_save_round_trip_and_is_absent_from_the_public_payload(): void
    {
        $this->actingAsSuperAdmin();

        $this->saveBlocks([
            $this->newRow('hero', ['title' => 'Still here']),
            [
                'type' => 'acme_banner_from_a_disabled_package',
                'enabled' => true,
                'variant' => [],
                'data' => ['headline' => 'Do not lose me', 'tone' => 'brand'],
            ],
        ])->assertSessionHasNoErrors()->assertRedirect();

        $stored = $this->storedBlocks();

        $this->assertCount(2, $stored, 'An unknown type is quarantined, never rejected and never dropped.');
        $this->assertSame('acme_banner_from_a_disabled_package', $stored[1]['type']);
        $this->assertSame('Do not lose me', $stored[1]['data']['headline']);

        $published = $this->publishedBlocks();

        $this->assertSame(['hero'], array_column($published, 'type'));
    }

    public function test_the_public_homepage_still_renders_when_the_stored_list_is_garbage(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->blocks = ['not-a-row', ['type' => 42], ['type' => 'nope_not_real']];
        $settings->save();

        $props = $this->get('/')->assertOk()->viewData('page')['props'];

        $this->assertSame([], $props['blocks'], 'Every unusable row is dropped, and the legacy path takes over.');
        $this->assertArrayHasKey('hero_title', $props['settings']);
    }
}
