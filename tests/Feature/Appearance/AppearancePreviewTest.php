<?php

namespace Tests\Feature\Appearance;

use App\Appearance\Admin\AppearanceWriter;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * 7.12 — the preview endpoint's permission posture, and the proof that preview
 * and save resolve through the SAME code path rather than two look-alikes.
 */
class AppearancePreviewTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'auth_layout' => 'cover',
            'auth_flip' => false,
            'auth_show_tagline' => true,
            'auth_bg_type' => 'gradient',
            'auth_bg_color' => '#123456',
            'auth_bg_recipe' => 'dusk',
            'auth_bg_scrim' => 'strong',
        ], $overrides);
    }

    public function test_the_preview_endpoint_requires_brand_update(): void
    {
        $this->postJson(route('admin.appearance.preview'), $this->payload())->assertUnauthorized();

        $this->actingAsUser();
        $this->postJson(route('admin.appearance.preview'), $this->payload())->assertForbidden();
    }

    public function test_a_viewer_without_brand_update_cannot_preview(): void
    {
        Permission::findOrCreate('brand.view');

        $user = $this->actingAsUser();
        $user->givePermissionTo('brand.view');

        $this->get(route('admin.appearance.index'))->assertOk();
        $this->postJson(route('admin.appearance.preview'), $this->payload())->assertForbidden();
    }

    public function test_the_preview_persists_nothing(): void
    {
        $this->actingAsSuperAdmin();

        $rows = fn () => DB::table('settings')
            ->where('group', AppearanceWriter::GROUP)
            ->orderBy('name')
            ->pluck('payload', 'name')
            ->all();

        $before = $rows();

        // Anti-vacuity: the previewed layout must differ from the stored one,
        // or "nothing changed" proves nothing.
        $this->assertSame('"split"', $before['auth_layout'] ?? null);

        $this->postJson(route('admin.appearance.preview'), $this->payload())
            ->assertOk()
            ->assertJsonPath('auth.layout', 'cover')
            ->assertJsonPath('auth.surface.recipe', 'dusk');

        // Every row byte-identical. The migration SEEDS all fourteen keys, so
        // asserting the row is absent would only be testing that fact.
        $this->assertSame($before, $rows());
    }

    /** Preview and save cannot diverge: the previewed payload IS the saved payload. */
    public function test_the_previewed_payload_equals_the_saved_payload(): void
    {
        $this->actingAsSuperAdmin();

        $input = $this->payload();

        $previewed = $this->postJson(route('admin.appearance.preview'), $input)
            ->assertOk()
            ->json('auth');

        $this->putJson(route('admin.appearance.update'), $input)->assertRedirect();

        // Through JSON on BOTH sides. viewData() hands back raw PHP (css_vars is
        // a stdClass so `{}` survives encoding) while ->json() has already
        // decoded to arrays; comparing the two shapes tests the assertion
        // helpers, not the payload the browser receives.
        $saved = json_decode(json_encode(
            $this->get(route('admin.appearance.index'))->assertOk()->viewData('page')['props']['preview'],
        ), true);

        $this->assertEquals($previewed, $saved);
    }

    public function test_an_out_of_allowlist_preview_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        $this->postJson(route('admin.appearance.preview'), $this->payload(['auth_bg_recipe' => 'evil']))
            ->assertStatus(422);
    }

    /**
     * The ?authLayout= draft posture mirrors ?template= exactly. The resolver
     * ships in the engine bundle, so in an isolated worktree there is nothing to
     * assert against — this becomes live at merge.
     */
    public function test_the_draft_layout_query_is_honoured_only_for_an_editor(): void
    {
        if (! class_exists(\App\Appearance\AuthLayoutRegistry::class)) {
            $this->markTestSkipped('AuthLayoutRegistry ships with the engine bundle.');
        }

        // A guest sees the stored layout, never the draft.
        $guest = $this->authPayload($this->get('/login?authLayout=cover')->assertOk()->getContent());
        $this->assertSame('split', $guest['layout']);

        // /login is behind the guest middleware, so the authenticated cases use
        // confirm-password — one of the three guest-layout pages served to a
        // logged-in user.
        $plain = $this->actingAsUser();
        $this->assertFalse($plain->can('settings.edit'));
        $withoutPermission = $this->authPayload($this->get(route('password.confirm').'?authLayout=cover')->getContent());
        $this->assertSame('split', $withoutPermission['layout']);

        Permission::findOrCreate('settings.edit');
        $editor = $this->actingAsUser();
        $editor->givePermissionTo('settings.edit');

        $withPermission = $this->authPayload($this->get(route('password.confirm').'?authLayout=cover')->getContent());
        $this->assertSame('cover', $withPermission['layout']);
    }

    /** Parsed straight out of the emitted HTML — never a hand-authored literal. */
    private function authPayload(string $html): array
    {
        $this->assertMatchesRegularExpression('/name="myra-appearance"/', $html);

        preg_match('/<meta name="myra-appearance" content="([^"]*)"/', $html, $m);

        return json_decode(html_entity_decode($m[1] ?? '', ENT_QUOTES), true)['auth'] ?? [];
    }
}
