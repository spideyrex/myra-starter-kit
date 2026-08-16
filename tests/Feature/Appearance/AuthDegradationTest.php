<?php

namespace Tests\Feature\Appearance;

use App\Appearance\AppearanceManager;
use App\Appearance\AuthLayoutRegistry;
use App\Appearance\Background;
use App\Appearance\Scrim;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MANDATORY 2 — every failure mode degrades to today's layout.
 *
 * The login page is unauthenticated and it is the only way in. If it throws,
 * nobody can sign in, including the admin who would fix it.
 */
class AuthDegradationTest extends TestCase
{
    use WritesAppearanceSettings;

    protected function setUp(): void
    {
        parent::setUp();

        AuthLayoutRegistry::flush();
    }

    /** @return array<string,array{0:mixed}> */
    public static function hostileLayoutKeys(): array
    {
        return [
            'unknown key' => ['nope'],
            'renamed key' => ['split-v2'],
            'traversal shaped' => ['../../etc/passwd'],
            'null' => [null],
            'int' => [123],
            'array' => [['a']],
            'empty string' => [''],
            'object' => [['nested' => ['deep' => 1]]],
        ];
    }

    /** @dataProvider hostileLayoutKeys */
    public function test_a_hostile_layout_key_still_serves_the_split_login(mixed $stored): void
    {
        $this->writeAppearance(['auth_layout' => $stored]);

        $html = $this->get('/login')->assertOk()->getContent();

        $payload = $this->appearanceMetaFrom($html);

        $this->assertSame('split', $payload['auth']['layout']);
        $this->assertSame('SplitLayout', $payload['auth']['component']);
        $this->assertStringContainsString('max-w-md', $this->guestShellSource());
    }

    public function test_every_surface_row_deleted_still_serves_the_split_login(): void
    {
        $this->deleteAppearance($this->surfaceRowNames());

        $payload = $this->appearanceMetaFrom($this->get('/login')->assertOk()->getContent());

        $this->assertSame('split', $payload['auth']['layout']);
        $this->assertSame('brand', $payload['auth']['surface']['type']);
        $this->assertSame('none', $payload['page']['surface']['type']);
    }

    public function test_every_surface_row_nulled_still_serves_the_split_login(): void
    {
        $this->writeAppearance(array_fill_keys($this->surfaceRowNames(), null));

        $payload = $this->appearanceMetaFrom($this->get('/login')->assertOk()->getContent());

        $this->assertSame('split', $payload['auth']['layout']);
        $this->assertSame('brand', $payload['auth']['surface']['type']);
        $this->assertSame('none', $payload['page']['surface']['type']);
        $this->assertFalse($payload['auth']['flip']);
        $this->assertTrue($payload['auth']['show_tagline']);
    }

    /**
     * REAL in this worktree: centered/cover/card are registered in config but
     * their .vue shells ship in bundle D. The server resolves them happily; the
     * client's BY_NAME[component] ?? Split guard is what saves the page.
     */
    public function test_a_registered_layout_with_no_client_component_is_caught_by_the_client_guard(): void
    {
        $this->writeAppearance(['auth_layout' => 'cover']);

        $payload = $this->appearanceMetaFrom($this->get('/login')->assertOk()->getContent());

        $this->assertSame('cover', $payload['auth']['layout']);
        $this->assertSame('CoverLayout', $payload['auth']['component']);

        $shipped = array_map(
            static fn (string $p) => basename($p, '.vue'),
            glob(resource_path('js/Layouts/Guest/*.vue')) ?: [],
        );

        $this->assertContains('SplitLayout', $shipped, 'The fallback shell must always ship.');

        if (! in_array($payload['auth']['component'], $shipped, true)) {
            // The dispatcher's second guard is the only thing standing here.
            $this->assertStringContainsString(
                'BY_NAME[appearance.value.auth.component] ?? Split',
                (string) file_get_contents(resource_path('js/Layouts/GuestLayout.vue')),
            );
        }
    }

    public function test_an_image_pointing_at_a_deleted_file_renders_the_base_colour(): void
    {
        Storage::fake('public');

        $this->writeAppearance([
            'auth_bg_type' => 'image',
            'auth_bg_image_path' => 'appearance/deleted.jpg',
            'auth_bg_color' => '#123456',
        ]);

        $background = app(AppearanceManager::class)->auth()->background;

        $this->assertNull($background->imageUrl());

        $vars = app(AppearanceManager::class)->toInertiaProp()['auth']['surface']['css_vars'];
        $vars = (array) $vars;

        $this->assertArrayHasKey('--myra-auth-bg', $vars);
        $this->assertNotSame('', $vars['--myra-auth-bg']);

        $payload = $this->appearanceMetaFrom($this->get('/login')->assertOk()->getContent());

        $this->assertNull($payload['auth']['surface']['image_url']);
        $this->assertNotSame('', $payload['auth']['surface']['base']);
    }

    public function test_an_image_can_never_be_left_unscrimmed(): void
    {
        $this->writeAppearance(['auth_bg_type' => 'image', 'auth_bg_scrim' => 'none']);

        $this->assertSame(Scrim::Light, app(AppearanceManager::class)->auth()->background->scrim);

        $payload = $this->appearanceMetaFrom($this->get('/login')->assertOk()->getContent());

        $this->assertSame('light', $payload['auth']['surface']['scrim']);
    }

    public function test_an_unknown_recipe_degrades_to_the_base_colour(): void
    {
        $this->writeAppearance([
            'auth_bg_type' => 'gradient',
            'auth_bg_recipe' => 'not-a-recipe',
            'auth_bg_color' => '#123456',
        ]);

        $background = app(AppearanceManager::class)->auth()->background;

        $this->assertNull($background->recipe);

        $vars = (array) app(AppearanceManager::class)->toInertiaProp()['auth']['surface']['css_vars'];

        $this->assertArrayNotHasKey('--myra-auth-image', $vars);
        $this->assertArrayHasKey('--myra-auth-bg', $vars);

        $this->get('/login')->assertOk();
    }

    /** A pattern key stored against a gradient type is out of family, so null. */
    public function test_a_recipe_from_the_wrong_family_is_refused(): void
    {
        $this->writeAppearance(['auth_bg_type' => 'gradient', 'auth_bg_recipe' => 'dots']);

        $this->assertNull(app(AppearanceManager::class)->auth()->background->recipe);
    }

    public function test_the_kill_switch_returns_stock_without_reading_settings(): void
    {
        $this->writeAppearance([
            'auth_layout' => 'cover',
            'auth_bg_type' => 'image',
            'auth_bg_image_path' => 'appearance/hostile.jpg',
            'auth_flip' => true,
        ]);

        config(['myra.appearance.enabled' => false]);

        app(AppearanceManager::class)->forget();

        $queries = 0;
        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $auth = app(AppearanceManager::class)->auth();

        $this->assertSame(0, $queries, 'The kill switch must not touch the database.');
        $this->assertSame('split', $auth->layout->key());
        $this->assertSame('SplitLayout', $auth->layout->componentName());
        $this->assertSame('brand', $auth->background->type->value);
        $this->assertFalse($auth->flip);
    }

    /** The surface style tag is NOT gated on the brand being enabled. */
    public function test_a_disabled_brand_still_gets_its_configured_surface(): void
    {
        DB::table('settings')->updateOrInsert(
            ['group' => 'brand', 'name' => 'enabled'],
            ['payload' => json_encode(false), 'locked' => false, 'created_at' => now(), 'updated_at' => now()],
        );

        $this->writeAppearance(['auth_bg_type' => 'solid', 'auth_bg_color' => '#123456']);

        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('<style id="myra-brand">', $html);
        $this->assertStringContainsString('<style id="myra-surface">', $html);
        $this->assertStringContainsString('--myra-auth-bg:oklch(', $html);
    }

    public function test_a_hostile_hex_never_reaches_the_emitted_css(): void
    {
        $this->writeAppearance([
            'auth_bg_type' => 'solid',
            'auth_bg_color' => '#fff");}body{display:none',
        ]);

        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringNotContainsString('display:none', $html);
        $this->assertStringNotContainsString('body{', $html);

        // It never became a colour at all; the brand primary is used instead.
        $this->assertNull(app(AppearanceManager::class)->auth()->background->colorHex);
    }

    public function test_a_traversal_shaped_image_path_is_refused(): void
    {
        foreach (['../../.env', '/etc/passwd', 'http://evil.test/x.png', 'a\\..\\b.png'] as $path) {
            $background = Background::fromSettings(
                ['auth_bg_type' => 'image', 'auth_bg_image_path' => $path],
                'auth',
            );

            $this->assertNull($background->imagePath, "Accepted a hostile path [{$path}].");
        }
    }

    public function test_a_completely_broken_settings_table_still_serves_the_login(): void
    {
        $this->writeAppearance([
            'auth_layout' => ['nested' => ['deep' => true]],
            'auth_bg_type' => 42,
            'auth_bg_scrim' => ['strong'],
            'auth_bg_recipe' => 99,
            'auth_bg_color' => ['#fff'],
            'auth_flip' => 'yes-please',
            'auth_show_tagline' => 'nope',
        ]);

        $payload = $this->appearanceMetaFrom($this->get('/login')->assertOk()->getContent());

        $this->assertSame('split', $payload['auth']['layout']);
        $this->assertSame('brand', $payload['auth']['surface']['type']);
        $this->assertFalse($payload['auth']['flip']);
    }
}
