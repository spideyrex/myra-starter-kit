<?php

namespace Tests\Feature\PageBuilder;

use App\Homepage\Sections\SectionRegistry;
use App\Http\Controllers\Admin\LandingPreviewController;
use App\Settings\HomepageSettings;
use Tests\TestCase;

/**
 * The preview slot (spec §5).
 *
 * One renderer for preview and production: the iframe loads the REAL public
 * `/`. These tests drive the actual endpoint and then feed the actual stored
 * slot back to the public controller — no hand-authored session literals.
 */
class LandingPreviewTest extends TestCase
{
    private const KEY = 'myra.pagebuilder.preview';

    /** A stored list that is visibly different from any draft. */
    private function storedBlocks(): array
    {
        return [$this->draftRow('rich_text', ['heading' => 'Saved heading', 'body' => '<p>Saved body</p>'])];
    }

    /** Built the way the editor builds one: server defaults, then overwrites. */
    private function draftRow(string $type, array $overrides = [], array $variant = []): array
    {
        $section = SectionRegistry::get($type);
        $data = $section !== null ? $section->defaults() : [];

        return [
            'type' => $type,
            'enabled' => true,
            'variant' => $variant,
            'data' => array_replace($data, $overrides),
        ];
    }

    private function seedStoredBlocks(): void
    {
        $settings = app(HomepageSettings::class);
        $settings->blocks = $this->storedBlocks();
        $settings->save();
    }

    /** POST the real draft and hand back the slot the server actually wrote. */
    private function publishDraft(array $rows): array
    {
        $response = $this->postJson(route('admin.landing.builder.preview'), ['blocks' => $rows]);
        $response->assertOk();

        $slot = $this->app['session.store']->get(self::KEY);

        $this->assertIsArray($slot);
        $this->assertSame($response->json('token'), $slot['token']);

        return $slot;
    }

    public function test_the_preview_slot_is_gated_by_settings_edit(): void
    {
        $this->postJson(route('admin.landing.builder.preview'), ['blocks' => []])
            ->assertStatus(401);

        $this->actingAsUser();

        $this->postJson(route('admin.landing.builder.preview'), ['blocks' => []])
            ->assertForbidden();
    }

    public function test_a_draft_is_written_to_the_session_and_never_persisted(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedStoredBlocks();

        $before = app(HomepageSettings::class)->blocks;

        $slot = $this->publishDraft([
            $this->draftRow('rich_text', ['heading' => 'Draft heading', 'body' => '<p>Draft body</p>']),
        ]);

        $this->assertNotSame('', $slot['token']);
        $this->assertGreaterThan(time(), $slot['expires_at']);
        $this->assertLessThanOrEqual(time() + LandingPreviewController::TTL_SECONDS, $slot['expires_at']);
        $this->assertCount(1, $slot['blocks']);
        $this->assertSame('Draft heading', $slot['blocks'][0]['data']['heading']);

        $this->app->forgetInstance(HomepageSettings::class);

        $this->assertSame($before, app(HomepageSettings::class)->blocks);
    }

    public function test_the_correct_token_and_ability_yields_the_draft(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedStoredBlocks();

        $slot = $this->publishDraft([
            $this->draftRow('rich_text', ['heading' => 'Draft heading', 'body' => '<p>Draft body</p>']),
        ]);

        $props = $this->withSession([self::KEY => $slot])
            ->get('/?preview='.$slot['token'])
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('Draft heading', $props['blocks'][0]['data']['heading']);

        // Storage is untouched: the stored list is still what the public page
        // gets on the very next unpreviewed request.
        $this->assertSame('Saved heading', $this->get('/')->viewData('page')['props']['blocks'][0]['data']['heading']);
    }

    public function test_a_guest_hitting_the_preview_url_gets_the_stored_blocks(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedStoredBlocks();

        $slot = $this->publishDraft([$this->draftRow('rich_text', ['heading' => 'Draft heading'])]);

        $this->app['auth']->forgetGuards();

        $props = $this->withSession([self::KEY => $slot])
            ->get('/?preview='.$slot['token'])
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('Saved heading', $props['blocks'][0]['data']['heading']);
    }

    public function test_a_user_without_settings_edit_gets_the_stored_blocks(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedStoredBlocks();

        $slot = $this->publishDraft([$this->draftRow('rich_text', ['heading' => 'Draft heading'])]);

        $this->app['auth']->forgetGuards();
        $this->actingAsUser();

        $props = $this->withSession([self::KEY => $slot])
            ->get('/?preview='.$slot['token'])
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('Saved heading', $props['blocks'][0]['data']['heading']);
    }

    public function test_a_wrong_token_is_ignored(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedStoredBlocks();

        $slot = $this->publishDraft([$this->draftRow('rich_text', ['heading' => 'Draft heading'])]);

        $props = $this->withSession([self::KEY => $slot])
            ->get('/?preview=not-the-token')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('Saved heading', $props['blocks'][0]['data']['heading']);
    }

    public function test_an_expired_slot_is_ignored(): void
    {
        $this->actingAsSuperAdmin();
        $this->seedStoredBlocks();

        $slot = $this->publishDraft([$this->draftRow('rich_text', ['heading' => 'Draft heading'])]);
        $slot['expires_at'] = time() - 1;

        $props = $this->withSession([self::KEY => $slot])
            ->get('/?preview='.$slot['token'])
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertSame('Saved heading', $props['blocks'][0]['data']['heading']);
    }

    public function test_a_malformed_draft_body_is_stored_as_an_empty_list(): void
    {
        $this->actingAsSuperAdmin();

        foreach (['nope', 42, null, ['keyed' => 'not a list']] as $garbage) {
            $response = $this->postJson(route('admin.landing.builder.preview'), ['blocks' => $garbage]);
            $response->assertOk();

            $this->assertSame([], $this->app['session.store']->get(self::KEY)['blocks']);
        }
    }

    public function test_a_draft_is_capped(): void
    {
        $this->actingAsSuperAdmin();

        $rows = array_fill(0, LandingPreviewController::MAX_BLOCKS + 50, $this->draftRow('divider'));

        $slot = $this->publishDraft($rows);

        $this->assertCount(LandingPreviewController::MAX_BLOCKS, $slot['blocks']);
    }

    public function test_a_new_post_overwrites_the_single_slot(): void
    {
        $this->actingAsSuperAdmin();

        $first = $this->publishDraft([$this->draftRow('rich_text', ['heading' => 'One'])]);
        $second = $this->publishDraft([$this->draftRow('rich_text', ['heading' => 'Two'])]);

        $this->assertNotSame($first['token'], $second['token']);
        $this->assertSame('Two', $this->app['session.store']->get(self::KEY)['blocks'][0]['data']['heading']);
    }
}
