<?php

namespace Tests\Feature\Navigation;

use App\Admin\Clusters\LearningCluster;
use App\Admin\Navigation\NavRegistry;
use Tests\TestCase;

/**
 * REAL PAYLOAD. The JSON the vitest nav specs mount against is written from the
 * prop this request actually produced — never hand-authored.
 */
class NavPayloadTest extends TestCase
{
    use SyncsNavFixture;

    protected function setUp(): void
    {
        parent::setUp();
        // The payload is read out of the rendered root view, which pulls in @vite.
        $this->withoutVite();
        NavRegistry::flush();
        config(['myra.clusters' => [LearningCluster::class]]);
    }

    protected function tearDown(): void
    {
        NavRegistry::flush();
        parent::tearDown();
    }

    public function test_the_shared_prop_matches_the_committed_fixture(): void
    {
        $user = $this->actingAsUser();
        $user->givePermissionTo('demo.view');
        $this->actingAs($user->fresh());

        $response = $this->get(route('admin.demo.index'));
        $response->assertOk();

        $payload = $response->viewData('page')['props']['myraNav'];

        $this->assertNotSame([], $payload, 'The demo cluster must contribute nav for a demo.view holder.');

        $this->syncFixture('tests/js/fixtures/myra-nav.json', $payload);
    }

    public function test_every_label_is_an_i18n_key_and_exists_in_all_three_locales(): void
    {
        $user = $this->actingAsUser();
        $user->givePermissionTo('demo.view');
        $this->actingAs($user->fresh());

        $payload = $this->get(route('admin.demo.index'))->viewData('page')['props']['myraNav'];

        $keys = [];
        $collect = function (array $items) use (&$collect, &$keys) {
            foreach ($items as $item) {
                $keys[] = $item['labelKey'];
                $collect($item['items'] ?? []);
            }
        };

        foreach ($payload as $group) {
            $keys[] = $group['labelKey'];
            $collect($group['items']);
        }

        foreach (['en', 'ms', 'zh'] as $locale) {
            $messages = json_decode(file_get_contents(base_path("resources/js/i18n/locales/{$locale}.json")), true);

            foreach ($keys as $key) {
                $this->assertIsString(
                    data_get($messages, $key),
                    "Missing i18n key [{$key}] in {$locale}.json — the sidebar would render a raw key.",
                );
            }
        }
    }
}
