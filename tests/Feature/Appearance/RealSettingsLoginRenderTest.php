<?php

namespace Tests\Feature\Appearance;

use App\Appearance\AppearanceManager;
use App\Appearance\AuthLayoutRegistry;
use Tests\TestCase;

/**
 * MANDATORY 4 — real settings driven into the real render.
 *
 * Nothing here is hand-authored: the appearance is written into the REAL
 * settings table, the payload is parsed OUT of the response HTML, and the
 * expectation is whatever the manager itself produces.
 */
class RealSettingsLoginRenderTest extends TestCase
{
    use WritesAppearanceSettings;

    protected function setUp(): void
    {
        parent::setUp();

        AuthLayoutRegistry::flush();
    }

    public function test_the_emitted_meta_payload_is_exactly_what_the_manager_produces(): void
    {
        $this->writeAppearance([
            'auth_layout' => 'cover',
            'auth_flip' => true,
            'auth_show_tagline' => false,
            'auth_bg_type' => 'gradient',
            'auth_bg_recipe' => 'dusk',
            'auth_bg_scrim' => 'strong',
            'auth_bg_color' => '#221133',
        ]);

        $html = $this->get('/login')->assertOk()->getContent();

        $emitted = $this->appearanceMetaFrom($html);

        $expected = json_decode(
            (string) json_encode(app(AppearanceManager::class)->toInertiaProp()),
            true,
        );

        $this->assertSame($expected, $emitted);
    }

    public function test_the_inertia_shared_prop_agrees_with_the_meta_tag(): void
    {
        $this->writeAppearance([
            'auth_bg_type' => 'pattern',
            'auth_bg_recipe' => 'grid',
            'auth_bg_color' => '#0f172a',
        ]);

        $response = $this->get('/login')->assertOk();

        $shared = $response->viewData('page')['props']['appearance'];
        $shared = json_decode((string) json_encode($shared), true);

        $this->assertSame($this->appearanceMetaFrom($response->getContent()), $shared);
    }

    /**
     * The style tag and the payload come from the same cssVars() call, so they
     * cannot disagree — and the tag is what paints the colour pre-hydration.
     */
    public function test_the_style_tag_carries_the_same_variables_as_the_payload(): void
    {
        $this->writeAppearance([
            'auth_bg_type' => 'gradient',
            'auth_bg_recipe' => 'aurora',
            'auth_bg_color' => '#1d4ed8',
            'page_bg_type' => 'pattern',
            'page_bg_recipe' => 'dots',
            'page_bg_color' => '#f8fafc',
        ]);

        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('<style id="myra-surface">', $html);

        $payload = $this->appearanceMetaFrom($html);

        foreach ([$payload['auth']['surface'], $payload['page']['surface']] as $surface) {
            $this->assertNotSame([], (array) $surface['css_vars']);

            foreach ((array) $surface['css_vars'] as $prop => $value) {
                $this->assertStringContainsString($prop.':'.$value.';', $html, "Style tag lost [{$prop}].");
            }
        }

        // The allowlist strips `#` and `:`, so no hex and no url() can survive.
        preg_match('/<style id="myra-surface">(.*?)<\/style>/s', $html, $m);

        $this->assertStringNotContainsString('url(', $m[1]);
        $this->assertStringNotContainsString('#', $m[1]);
    }

    public function test_an_image_backed_surface_ships_a_url_in_the_payload_but_never_in_the_css(): void
    {
        $disk = \Illuminate\Support\Facades\Storage::fake('public');
        $disk->put('appearance/hero.jpg', 'not-really-a-jpeg');

        $this->writeAppearance([
            'auth_bg_type' => 'image',
            'auth_bg_image_path' => 'appearance/hero.jpg',
            'auth_bg_color' => '#0f172a',
        ]);

        $html = $this->get('/login')->assertOk()->getContent();

        $payload = $this->appearanceMetaFrom($html);

        $this->assertNotNull($payload['auth']['surface']['image_url']);
        $this->assertStringContainsString('appearance/hero.jpg', $payload['auth']['surface']['image_url']);

        preg_match('/<style id="myra-surface">(.*?)<\/style>/s', $html, $m);

        $this->assertStringNotContainsString('hero.jpg', $m[1] ?? '');
        $this->assertStringNotContainsString('url(', $m[1] ?? '');
    }
}
