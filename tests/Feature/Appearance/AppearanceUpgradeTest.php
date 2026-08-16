<?php

namespace Tests\Feature\Appearance;

use App\Appearance\AppearanceManager;
use App\Appearance\AuthLayoutRegistry;
use App\Appearance\Background;
use Tests\TestCase;

/**
 * MANDATORY 1 — upgrading to v2.8 changes NOTHING until someone deliberately
 * changes it. The migration has run; nothing else has been touched.
 *
 * NOTE ON MARKUP ASSERTIONS: this install renders Inertia without SSR, so the
 * guest shell's DOM is never in the HTTP response. The markup half of this
 * guarantee is therefore asserted against the shell SOURCE (which is what the
 * browser mounts) plus the payload the server really emitted; the mounted-DOM
 * half is tests/js/guestLayoutDefault.spec.ts.
 */
class AppearanceUpgradeTest extends TestCase
{
    use WritesAppearanceSettings;

    protected function setUp(): void
    {
        parent::setUp();

        AuthLayoutRegistry::flush();
    }

    public function test_default_settings_emit_no_surface_css(): void
    {
        $this->assertSame([], Background::default('auth')->cssVars());
        $this->assertSame([], Background::default('page')->cssVars());

        $this->assertSame('', (string) app(AppearanceManager::class)->styleTag());
    }

    public function test_the_shipped_rows_resolve_to_the_stock_appearance(): void
    {
        $payload = app(AppearanceManager::class)->toInertiaProp();

        $this->assertSame('split', $payload['auth']['layout']);
        $this->assertSame('SplitLayout', $payload['auth']['component']);
        $this->assertFalse($payload['auth']['flip']);
        $this->assertTrue($payload['auth']['show_tagline']);
        $this->assertSame('brand', $payload['auth']['surface']['type']);
        $this->assertSame('none', $payload['page']['surface']['type']);
        $this->assertFalse($payload['page']['navbar_translucent']);

        // The proof that the upgrade is a no-op: no custom properties at all.
        $this->assertSame([], (array) $payload['auth']['surface']['css_vars']);
        $this->assertSame([], (array) $payload['page']['surface']['css_vars']);
    }

    public function test_the_shell_still_emits_no_surface_style_tag_on_a_real_page(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('<style id="myra-surface">', $html);
        $this->assertStringContainsString('<meta name="myra-appearance"', $html);
    }

    public function test_default_settings_render_todays_login_markup(): void
    {
        $this->get('/login')->assertOk();

        $source = $this->guestShellSource();

        foreach ([
            'hidden lg:flex lg:w-1/2',
            'bg-primary',
            'text-primary-foreground',
            'w-full max-w-md border-0 shadow-none lg:border lg:shadow-sm',
            'px-4 py-8',
            'p-6',
            'mb-6 lg:hidden',
            'min-h-screen',
        ] as $literal) {
            $this->assertStringContainsString($literal, $source, "The guest shell lost [{$literal}].");
        }
    }

    public function test_no_guest_shell_root_uses_a_fixed_viewport_height(): void
    {
        $source = $this->guestShellSource();

        $this->assertDoesNotMatchRegularExpression('/(?<!min-)h-screen/', $source);
        $this->assertStringNotContainsString('min-h-screen overflow-hidden', $source);
        $this->assertStringNotContainsString('background-attachment', $source);
    }
}
