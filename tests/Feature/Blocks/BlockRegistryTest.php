<?php

namespace Tests\Feature\Blocks;

use App\Admin\Blocks\BlockEntry;
use App\Admin\Blocks\BlockRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BlockRegistryTest extends TestCase
{
    use SyncsBlockFixtures;

    private const BLOCKS_DIR = 'resources/js/blocks';

    /** @return array<int,array> */
    private function entries(): array
    {
        BlockRegistry::flush();
        BlockRegistry::seed();

        return BlockRegistry::forUser($this->makeUser()->assignRole('super-admin'));
    }

    /** @return array<string,array> */
    private function manifest(): array
    {
        return BlockRegistry::manifest();
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

    public function test_the_registry_is_not_empty(): void
    {
        $this->assertNotEmpty($this->entries(), 'The block manifest seeded nothing — run node scripts/shadcn/fetch-blocks.mjs.');
    }

    public function test_every_entry_names_a_file_that_exists_on_disk(): void
    {
        foreach ($this->entries() as $entry) {
            $path = $entry['available']
                ? base_path(self::BLOCKS_DIR."/{$entry['entryFile']}")
                : base_path(self::BLOCKS_DIR."/_unavailable/{$entry['entryFile']}.txt");

            $this->assertFileExists($path, "Block [{$entry['key']}] names an entry file that is not on disk.");
        }
    }

    /**
     * These files are machine-written by scripts/shadcn/fetch-blocks.mjs.
     * Hand-editing them is precisely the defect this guard catches, so unlike
     * a file-count guard it does not self-invalidate when the bundle's own
     * source is edited.
     */
    public function test_every_manifest_file_hash_matches_disk(): void
    {
        foreach ($this->manifest() as $key => $block) {
            foreach ($block['files'] as $file) {
                $path = base_path($file['path']);

                $this->assertFileExists($path, "Block [{$key}] lists {$file['path']}, which is missing.");
                $this->assertSame(
                    $file['sha256'],
                    hash('sha256', (string) file_get_contents($path)),
                    "Block [{$key}] file {$file['path']} drifted from the vendored bytes.",
                );
            }
        }
    }

    public function test_registry_ids_equal_manifest_ids_equal_disk_dirs(): void
    {
        $registryIds = array_column($this->entries(), 'key');
        $manifestIds = array_keys($this->manifest());

        sort($registryIds);
        sort($manifestIds);
        $this->assertSame($manifestIds, $registryIds);

        $available = array_values(array_map(
            fn (array $e) => $e['key'],
            array_filter($this->entries(), fn (array $e) => $e['available']),
        ));

        $dirs = array_values(array_filter(
            array_map('basename', glob(base_path(self::BLOCKS_DIR.'/*'), GLOB_ONLYDIR) ?: []),
            fn (string $name) => $name !== '_unavailable',
        ));

        sort($available);
        sort($dirs);
        $this->assertSame($available, $dirs, 'The available blocks and the directories on disk disagree.');
    }

    /**
     * Meta-guard for recurring mistake #3: a test asserting an exact file count
     * fails the moment the bundle legitimately grows. The manifest IS the count,
     * so no PHP test in this bundle may assert a literal count above one.
     */
    public function test_no_hardcoded_file_count_is_asserted_anywhere(): void
    {
        $files = glob(base_path('tests/Feature/Blocks/*.php')) ?: [];

        $this->assertNotEmpty($files, 'The meta-guard scanned nothing; it has to read this bundle\'s own tests.');

        foreach ($files as $file) {
            preg_match_all('/assertCount\(\s*(\d+)/', (string) file_get_contents($file), $matches);

            foreach ($matches[1] as $literal) {
                $this->assertLessThanOrEqual(
                    1,
                    (int) $literal,
                    basename($file).' asserts a hardcoded count; assert against the manifest instead.',
                );
            }
        }
    }

    public function test_every_i18n_key_resolves_in_all_three_locales(): void
    {
        $locales = [
            'en' => $this->locale('en'),
            'ms' => $this->locale('ms'),
            'zh' => $this->locale('zh'),
        ];

        $keys = ['blocks.title', 'blocks.description', 'blocks.gaps.calendar', 'blocks.gaps.sourceOnly'];

        foreach ($this->entries() as $entry) {
            $keys[] = $entry['titleKey'];
            $keys[] = $entry['descriptionKey'];
            $keys[] = "blocks.categories.{$entry['category']}";
        }

        foreach (array_unique($keys) as $key) {
            foreach ($locales as $code => $messages) {
                $this->assertTrue(
                    $this->resolves($messages, $key),
                    "Key [{$key}] is missing from {$code}.json — locale parity is a merge gate.",
                );
            }
        }
    }

    public function test_every_route_renders_for_a_permitted_user(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('admin.blocks.index'))->assertOk();

        foreach ($this->entries() as $entry) {
            $this->get(route('admin.blocks.show', $entry['key']))->assertOk();
            $this->get(route('admin.blocks.preview', $entry['key']))->assertOk();
            $this->get(route('admin.blocks.source', $entry['key']))->assertOk();
        }
    }

    public function test_the_source_endpoint_returns_the_vendored_bytes(): void
    {
        $this->actingAsSuperAdmin();

        $entry = $this->entries()[0];
        $payload = $this->get(route('admin.blocks.source', $entry['key']))->assertOk()->json();

        $this->assertSame($entry['key'], $payload['key']);
        $this->assertNotEmpty($payload['files']);

        $first = $payload['files'][0];
        $this->assertSame(
            file_get_contents(base_path($first['path'])),
            $first['content'],
            'The source endpoint must serve the file the manifest names, byte for byte.',
        );
    }

    /**
     * Neither set nor cleared. withoutCookie() would emit a past-dated
     * sidebar_state, which the browser honours by DELETING the admin's own
     * sidebar preference on every preview open.
     */
    public function test_the_preview_response_leaves_the_sidebar_state_cookie_alone(): void
    {
        $this->actingAsSuperAdmin();

        $key = $this->entries()[0]['key'];
        $response = $this->get(route('admin.blocks.preview', $key))->assertOk();

        $this->assertNull(
            collect($response->headers->getCookies())->firstWhere('name', 'sidebar_state'),
            'The preview must not touch sidebar_state: setting it hijacks the admin shell, clearing it wipes the user preference.',
        );
    }

    public function test_the_catalogue_is_gated_by_blocks_view(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.blocks.index'))->assertForbidden();
        $this->get(route('admin.blocks.show', 'sidebar-01'))->assertForbidden();
        $this->get(route('admin.blocks.preview', 'sidebar-01'))->assertForbidden();
        $this->get(route('admin.blocks.source', 'sidebar-01'))->assertForbidden();
    }

    public function test_an_entry_the_actor_cannot_reach_is_not_disclosed(): void
    {
        BlockRegistry::flush();
        BlockRegistry::seed();

        $this->assertSame([], BlockRegistry::forUser($this->makeUser()));

        $permitted = $this->makeUser();
        $permitted->givePermissionTo('blocks.view');

        $this->assertNotEmpty(BlockRegistry::forUser($permitted));
    }

    public function test_an_unknown_block_id_404s(): void
    {
        $this->actingAsSuperAdmin();

        $this->get('/admin/blocks/not-a-block')->assertNotFound();
        $this->get('/admin/blocks/not-a-block/preview')->assertNotFound();
        $this->get('/admin/blocks/not-a-block/source')->assertNotFound();
    }

    public function test_source_is_served_from_the_manifest_not_the_url_param(): void
    {
        $this->actingAsSuperAdmin();

        $this->get('/admin/blocks/..%2F..%2Fapp/source')->assertNotFound();
        $this->get('/admin/blocks/'.urlencode('../../composer.json'))->assertNotFound();
    }

    public function test_a_contributed_entry_defaults_its_i18n_keys_from_its_own_key(): void
    {
        $schema = BlockEntry::make('customBlock')->toClientSchema();

        $this->assertSame('blocks.entries.customBlock.title', $schema['titleKey']);
        $this->assertSame('blocks.entries.customBlock.description', $schema['descriptionKey']);
        $this->assertSame('2.6.0', $schema['since']);
    }

    public function test_forget_and_flush_leave_the_registry_recoverable(): void
    {
        BlockRegistry::flush();
        BlockRegistry::seed();
        $this->assertTrue(BlockRegistry::has('sidebar-01'));

        BlockRegistry::forget('core');
        $this->assertFalse(BlockRegistry::has('sidebar-01'));

        BlockRegistry::flush();
        BlockRegistry::seed();

        $this->assertTrue(BlockRegistry::has('sidebar-01'));
        $this->assertNull(BlockRegistry::get('not-a-block'));
    }

    public function test_the_catalogue_404s_when_the_feature_is_disabled(): void
    {
        config()->set('myra.blocks.enabled', false);

        $this->actingAsSuperAdmin();

        $this->get(route('admin.blocks.index'))->assertNotFound();
    }

    public function test_the_index_props_match_the_committed_fixture(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->get(route('admin.blocks.index'))->assertOk()->viewData('page')['props'];

        $this->syncFixture('tests/js/fixtures/blocks-index.json', [
            'blocks' => $props['blocks'],
            'categories' => $props['categories'],
        ]);
    }
}
