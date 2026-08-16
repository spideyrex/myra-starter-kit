<?php

namespace Tests\Feature\Examples;

use App\Admin\Examples\ExampleEntry;
use App\Admin\Examples\ExampleRegistry;
use App\Support\Myra;
use Tests\TestCase;

class ExampleRegistryTest extends TestCase
{
    use SyncsExampleFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        ExampleRegistry::flush();
    }

    /** @return array<int,array> */
    private function entries(): array
    {
        return ExampleRegistry::forUser($this->makeUser()->assignRole('super-admin'));
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

    private function dirFor(array $entry): string
    {
        return base_path(($entry['available']
            ? ExampleRegistry::ROOT
            : ExampleRegistry::QUARANTINE).'/'.$entry['key']);
    }

    public function test_every_entry_names_a_file_that_exists_on_disk(): void
    {
        $entries = $this->entries();

        $this->assertNotEmpty($entries, 'The manifest seeded no examples at all.');

        foreach ($entries as $entry) {
            if ($entry['available']) {
                $this->assertFileExists(
                    base_path(ExampleRegistry::ROOT.'/'.$entry['shell']),
                    "Example [{$entry['key']}] names shell [{$entry['shell']}], which is not on disk.",
                );

                continue;
            }

            $this->assertDirectoryExists(
                $this->dirFor($entry),
                "Quarantined example [{$entry['key']}] has no directory under _unavailable.",
            );
        }
    }

    /**
     * These files are machine-written by scripts/shadcn/fetch-examples.mjs.
     * Hand-editing them is precisely the defect this guard catches, so unlike a
     * file-count guard it does not self-invalidate when the bundle's own source
     * is edited — editing a vendored file is exactly what should go red here,
     * and the fix is to re-run the fetch script rather than to relax the test.
     */
    public function test_every_manifest_file_hash_matches_disk(): void
    {
        foreach (ExampleRegistry::manifest() as $key => $row) {
            $dir = base_path(($row['available']
                ? ExampleRegistry::ROOT
                : ExampleRegistry::QUARANTINE).'/'.$key);

            $this->assertNotEmpty($row['files'], "Example [{$key}] records no files.");

            foreach ($row['files'] as $file) {
                $path = $dir.'/'.$file['path'];

                $this->assertFileExists($path, "Manifest names {$key}/{$file['path']}, which is missing.");
                $this->assertSame(
                    $file['sha256'],
                    hash_file('sha256', $path),
                    "{$key}/{$file['path']} drifted from the manifest. Re-run node scripts/shadcn/fetch-examples.mjs.",
                );
            }
        }
    }

    public function test_registry_ids_equal_manifest_ids_equal_disk_dirs(): void
    {
        $registry = array_column($this->entries(), 'key');
        $manifest = array_keys(ExampleRegistry::manifest());

        $disk = [];
        foreach (glob(base_path(ExampleRegistry::ROOT.'/*'), GLOB_ONLYDIR) ?: [] as $path) {
            if (basename($path) !== '_unavailable') {
                $disk[] = basename($path);
            }
        }
        foreach (glob(base_path(ExampleRegistry::QUARANTINE.'/*'), GLOB_ONLYDIR) ?: [] as $path) {
            $disk[] = basename($path);
        }

        sort($registry);
        sort($manifest);
        sort($disk);

        $this->assertSame($manifest, $registry, 'The registry and the manifest name different examples.');
        $this->assertSame($manifest, $disk, 'The manifest and resources/js/examples name different examples.');
    }

    /**
     * Meta-guard for recurring mistake #3: a literal count would go red the
     * moment an example is added, which is drift the suite should welcome.
     */
    public function test_no_hardcoded_file_count_is_asserted_anywhere(): void
    {
        foreach (glob(__DIR__.'/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);

            $this->assertSame(
                0,
                preg_match('/assertCount\(\s*(?!0\b|1\b)\d+/', $source),
                basename($file).' asserts a hardcoded count against the registry.',
            );
        }
    }

    public function test_every_i18n_key_resolves_in_all_three_locales(): void
    {
        $locales = [
            'en' => $this->locale('en'),
            'ms' => $this->locale('ms'),
            'zh' => $this->locale('zh'),
        ];

        foreach ($this->entries() as $entry) {
            foreach ([$entry['titleKey'], $entry['descriptionKey']] as $key) {
                foreach ($locales as $code => $messages) {
                    $this->assertTrue(
                        $this->resolves($messages, $key),
                        "Key [{$key}] is missing from {$code}.json — locale parity is a merge gate.",
                    );
                }
            }

            foreach ($entry['surfaces'] as $surface) {
                foreach ($locales as $code => $messages) {
                    $this->assertTrue(
                        $this->resolves($messages, "examples.surfaces.{$surface}"),
                        "Key [examples.surfaces.{$surface}] is missing from {$code}.json.",
                    );
                }
            }
        }
    }

    public function test_every_route_renders_for_a_permitted_user(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('admin.examples.index'))->assertOk();

        foreach ($this->entries() as $entry) {
            $this->get(route('admin.examples.show', $entry['key']))->assertOk();
            $this->get(route('admin.examples.source', $entry['key']))->assertOk();

            $preview = $this->get(route('admin.examples.preview', $entry['key']));

            $entry['available'] ? $preview->assertOk() : $preview->assertNotFound();
        }
    }

    public function test_preview_response_does_not_set_the_sidebar_state_cookie(): void
    {
        $this->actingAsSuperAdmin();

        $available = array_values(array_filter($this->entries(), fn (array $e) => $e['available']));

        $this->assertNotEmpty($available, 'Expected at least one mountable example.');

        foreach ($available as $entry) {
            $response = $this->get(route('admin.examples.preview', $entry['key']))->assertOk();

            $names = array_map(
                fn ($cookie) => $cookie->getName(),
                $response->headers->getCookies(),
            );

            // The preview mounts no SidebarProvider, so it must leave the live
            // sidebar's persisted state alone — neither set nor cleared, since
            // a queued deletion cookie is still a Set-Cookie with a value.
            $this->assertNotContains(
                'sidebar_state',
                $names,
                "Preview for [{$entry['key']}] emitted a sidebar_state cookie, which would clobber the live sidebar.",
            );
        }
    }

    public function test_the_catalogue_is_gated_by_examples_view(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.examples.index'))->assertForbidden();
        $this->get(route('admin.examples.show', 'mail'))->assertForbidden();
        $this->get(route('admin.examples.preview', 'mail'))->assertForbidden();
        $this->get(route('admin.examples.source', 'mail'))->assertForbidden();
    }

    public function test_an_entry_the_actor_cannot_reach_is_not_disclosed(): void
    {
        $user = $this->makeUser();

        $this->assertSame([], ExampleRegistry::forUser($user));
        $this->assertSame([], ExampleRegistry::forUser(null));

        $user->givePermissionTo('examples.view');

        $this->assertNotEmpty(ExampleRegistry::forUser($user));
    }

    public function test_an_unknown_example_id_404s_and_never_touches_the_filesystem(): void
    {
        $this->actingAsSuperAdmin();

        $this->assertNull(ExampleRegistry::get('not-an-example'));

        $this->get(Myra::adminPath('examples/not-an-example'))->assertNotFound();
        $this->get(Myra::adminPath('examples/not-an-example/preview'))->assertNotFound();
        $this->get(Myra::adminPath('examples/not-an-example/source'))->assertNotFound();
    }

    public function test_source_is_served_from_the_manifest_not_the_url_param(): void
    {
        $this->actingAsSuperAdmin();

        foreach (['..%2F..%2Fapp', '..%2F..%2F..%2Fetc%2Fpasswd', 'mail%2F..%2F..%2Fapp'] as $traversal) {
            $this->get(Myra::adminPath("examples/{$traversal}/source"))->assertNotFound();
        }

        $payload = $this->get(route('admin.examples.source', 'mail'))->assertOk()->json();

        $manifest = ExampleRegistry::manifest()['mail'];
        $served = array_column($payload['files'], 'path');

        sort($served);
        $expected = array_column($manifest['files'], 'path');
        sort($expected);

        $this->assertSame($expected, $served, 'Source must be enumerated from the manifest, not from a directory walk.');
    }

    public function test_a_contributed_entry_defaults_its_i18n_keys_from_its_own_key(): void
    {
        $schema = ExampleEntry::make('customThing')->toClientSchema();

        $this->assertSame('examples.entries.customThing.title', $schema['titleKey']);
        $this->assertSame('examples.entries.customThing.description', $schema['descriptionKey']);
        $this->assertSame('fetched', $schema['origin']);
    }

    public function test_forget_and_flush_leave_the_registry_recoverable(): void
    {
        ExampleRegistry::seed();
        $this->assertTrue(ExampleRegistry::has('mail'));

        ExampleRegistry::forget('core');
        $this->assertFalse(ExampleRegistry::has('mail'));

        ExampleRegistry::flush();
        ExampleRegistry::seed();
        $this->assertTrue(ExampleRegistry::has('mail'));
        $this->assertNull(ExampleRegistry::get('not-an-example'));
    }

    public function test_the_catalogue_404s_when_the_feature_is_disabled(): void
    {
        config()->set('myra.examples.enabled', false);

        $this->actingAsSuperAdmin();

        $this->get(route('admin.examples.index'))->assertNotFound();
        $this->get(route('admin.examples.show', 'mail'))->assertNotFound();
    }

    public function test_the_index_props_match_the_committed_fixture(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->get(route('admin.examples.index'))->assertOk()->viewData('page')['props'];

        $this->syncFixture('tests/js/fixtures/examples-index.json', ['examples' => $props['examples']]);
    }

    public function test_forget_after_a_route_hit_is_still_recoverable(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('admin.examples.index'))->assertOk();

        ExampleRegistry::forget('core');

        $this->get(route('admin.examples.index'))->assertOk();
        $this->assertNotEmpty($this->entries());
    }
}
