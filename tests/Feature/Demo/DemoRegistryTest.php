<?php

namespace Tests\Feature\Demo;

use App\Admin\Demo\DemoEntry;
use App\Admin\Demo\DemoRegistry;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DemoRegistryTest extends TestCase
{
    use SyncsDemoFixtures;

    private const DEMO_PAGES = 'resources/js/Pages/Admin/Demo';

    /** @return array<int,array> */
    private function entries(): array
    {
        DemoRegistry::seed();

        return DemoRegistry::forUser($this->makeUser()->assignRole('super-admin'));
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

    public function test_every_registered_entry_names_a_real_route(): void
    {
        foreach ($this->entries() as $entry) {
            $this->assertTrue(
                Route::has($entry['route']),
                "Gallery entry [{$entry['key']}] names route [{$entry['route']}], which is not registered.",
            );
        }
    }

    public function test_every_registered_route_renders(): void
    {
        $this->actingAsSuperAdmin();

        foreach ($this->entries() as $entry) {
            $this->get(route($entry['route']))
                ->assertOk();
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
            foreach (array_filter([$entry['titleKey'], $entry['descriptionKey'], $entry['badgeKey']]) as $key) {
                foreach ($locales as $code => $messages) {
                    $this->assertTrue(
                        $this->resolves($messages, $key),
                        "Key [{$key}] is missing from {$code}.json — locale parity is a merge gate.",
                    );
                }
            }
        }
    }

    public function test_the_registry_covers_every_demo_page_exactly_once(): void
    {
        $pages = array_values(array_filter(
            glob(base_path(self::DEMO_PAGES.'/*.vue')) ?: [],
            fn (string $path) => basename($path) !== 'Index.vue',
        ));

        $this->assertCount(
            count($pages),
            $this->entries(),
            'The gallery registry drifted from resources/js/Pages/Admin/Demo/. '
            .'Every page needs exactly one DemoEntry, and every DemoEntry needs a page.',
        );
    }

    public function test_the_index_props_match_the_committed_fixture(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->get(route('admin.demo.index'))->assertOk()->viewData('page')['props'];

        $this->syncFixture('tests/js/fixtures/demo-registry.json', ['demos' => $props['demos']]);
    }

    public function test_a_gated_entry_is_not_disclosed_to_a_user_without_the_ability(): void
    {
        $gated = array_values(array_filter(
            $this->entries(),
            fn (array $entry) => in_array($entry['key'], ['reports', 'reportDelivery'], true),
        ));

        $this->assertNotEmpty($gated, 'Expected at least one permission-gated gallery entry.');

        $user = $this->makeUser();
        $keys = array_column(DemoRegistry::forUser($user), 'key');

        $this->assertNotContains('reports', $keys);
        $this->assertNotContains('reportDelivery', $keys);
    }

    public function test_an_unpermitted_entry_is_filtered_but_a_permitted_one_is_not(): void
    {
        $user = $this->makeUser();
        $user->givePermissionTo('reports.view');

        $keys = array_column(DemoRegistry::forUser($user), 'key');

        $this->assertContains('reports', $keys);
        $this->assertNotContains('reportDelivery', $keys);
    }

    public function test_forget_and_flush_leave_the_registry_recoverable(): void
    {
        DemoRegistry::seed();
        $this->assertTrue(DemoRegistry::has('playground'));

        DemoRegistry::forget('core');
        $this->assertFalse(DemoRegistry::has('playground'));

        DemoRegistry::flush();
        DemoRegistry::seed();
        $this->assertTrue(DemoRegistry::has('playground'));
        $this->assertNull(DemoRegistry::get('not-a-demo'));
    }

    public function test_a_contributed_entry_defaults_its_i18n_keys_from_its_own_key(): void
    {
        $schema = DemoEntry::make('customThing')->routeName('admin.demo.index')->toClientSchema();

        $this->assertSame('gallery.demos.customThing.title', $schema['titleKey']);
        $this->assertSame('gallery.demos.customThing.description', $schema['descriptionKey']);
        $this->assertSame('gallery.demos.customThing.badge', $schema['badgeKey']);
        $this->assertSame('LayoutGrid', $schema['icon']);
    }

    public function test_the_gallery_is_gated_by_the_demo_permission(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.demo.index'))->assertForbidden();
        $this->get(route('admin.demo.playground'))->assertForbidden();
        $this->get(route('admin.demo.report-delivery'))->assertForbidden();
    }
}
