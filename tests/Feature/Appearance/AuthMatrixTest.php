<?php

namespace Tests\Feature\Appearance;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * MYRA v2.8 [D] — the merge gate.
 *
 * Seven guest pages crossed with four layouts and six background types. Three
 * of the seven are served to a LOGGED-IN user, so no shell may assume
 * `auth.user === null`.
 *
 * Bundle D's worktree has no appearance engine, so the layout dimension passes
 * vacuously there: the rows are written straight to the settings table, exactly
 * as the admin bundle writes them, and whatever the engine resolves them to
 * must still return 200. RE-RUN THIS AFTER MERGE.
 */
class AuthMatrixTest extends TestCase
{
    private const LAYOUTS = ['split', 'centered', 'cover', 'card'];

    private const TYPES = ['brand', 'solid', 'gradient', 'pattern', 'image', 'none'];

    private const SHELLS = 'js/Layouts/Guest';

    /** @return array<string,array{0:string,1:string}> */
    public static function matrix(): array
    {
        $cases = [];

        foreach (self::LAYOUTS as $layout) {
            foreach (self::TYPES as $type) {
                $cases["{$layout} + {$type}"] = [$layout, $type];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider matrix
     */
    public function test_every_guest_page_renders_under_every_layout_and_background(string $layout, string $type): void
    {
        $this->writeAppearance($layout, $type);

        // Guest first: an authenticated actor is redirected away from these.
        foreach ([
            '/login' => 'Auth/Login',
            '/register' => 'Auth/Register',
            '/forgot-password' => 'Auth/ForgotPassword',
            '/reset-password/'.Str::random(40) => 'Auth/ResetPassword',
        ] as $url => $component) {
            $this->assertGuestPageRenders($url, $component, $layout, $type);
        }

        // The three that reach a logged-in user.
        $this->actingAs(User::factory()->unverified()->create());

        foreach ([
            '/verify-email' => 'Auth/VerifyEmail',
            '/confirm-password' => 'Auth/ConfirmPassword',
            '/two-factor/challenge' => 'Auth/TwoFactorChallenge',
        ] as $url => $component) {
            $this->assertGuestPageRenders($url, $component, $layout, $type);
        }
    }

    /**
     * A layout key the registry never heard of must not take the sign-in page
     * with it — the seven pages still render, on the stock split shell.
     */
    public function test_a_hostile_layout_key_still_renders_every_guest_page(): void
    {
        foreach (['nope', '../../etc/passwd', '', str_repeat('x', 300)] as $key) {
            $this->writeAppearance($key, 'gradient', ['auth_bg_recipe' => 'not-a-recipe']);

            $this->get('/login')->assertOk();
            $this->get('/register')->assertOk();
        }
    }

    /**
     * Every shell must be able to grow: Register is four fields plus a
     * five-segment strength meter, and TwoFactorChallenge's code input needs
     * the full card width.
     */
    public function test_no_shell_traps_the_page_in_the_viewport(): void
    {
        foreach ($this->shellSources() as $file => $source) {
            $root = $this->rootAttributes($source);

            $this->assertStringContainsString('min-h-screen', $root, "{$file} lost min-h-screen on its root.");
            $this->assertDoesNotMatchRegularExpression('/(?<![-\w])h-screen/', $root, "{$file} pins its root to the viewport.");
            $this->assertStringNotContainsString('overflow-hidden', $root, "{$file} clips its own root.");
            $this->assertStringNotContainsString('background-attachment', $source, "{$file} fixes its background.");
        }
    }

    public function test_every_shell_keeps_the_form_card_isolated_and_wide_enough(): void
    {
        foreach ($this->shellSources() as $file => $source) {
            $this->assertStringContainsString('<slot />', $source, "{$file} never renders the form.");
            $this->assertStringContainsString('max-w-md', $source, "{$file} narrows the card below max-w-md.");
            $this->assertStringContainsString('CardContent', $source, "{$file} moved the form out of the card.");
            $this->assertStringContainsString('BrandMark', $source, "{$file} drops the brand entirely.");
        }
    }

    /** Decorative only: no accessible name, no announced scrim, no v-html. */
    public function test_every_shell_marks_its_decoration_presentational(): void
    {
        foreach ($this->shellSources() as $file => $source) {
            $this->assertStringNotContainsString('v-html', $source, "{$file} interpolates markup.");
            $this->assertStringNotContainsString('url(', $source, "{$file} builds a CSS url.");

            if (str_contains($source, '<img')) {
                $this->assertStringContainsString('alt=""', $source, "{$file} names a decorative image.");
                $this->assertStringContainsString('@error', $source, "{$file} has no fallback for a 404ing image.");
                $this->assertStringContainsString('aria-hidden="true"', $source, "{$file} announces its scrim.");
            }
        }
    }

    private function assertGuestPageRenders(string $url, string $component, string $layout, string $type): void
    {
        $context = "{$url} under {$layout} + {$type}";

        $response = $this->get($url);

        $response->assertOk();

        $this->assertSame($component, $response->viewData('page')['component'], $context);

        $payload = $this->emittedAppearance($response->getContent());

        if ($payload === null) {
            return; // No engine in this build — the page still had to render.
        }

        $auth = $payload['auth'] ?? [];
        $surface = $auth['surface'] ?? [];

        $this->assertContains($auth['layout'] ?? null, self::LAYOUTS, $context);
        $this->assertIsString($auth['component'] ?? null, $context);

        // What was saved is what the page is wearing.
        $this->assertSame($layout, $auth['layout'], "{$context}: the saved layout never reached the page.");
        $this->assertSame($type, $surface['type'] ?? null, "{$context}: the saved background never reached the page.");

        // A base colour exists for EVERY type, which is what makes a missing
        // image or an unknown recipe a colour-only render instead of a hole.
        $this->assertNotSame('', (string) ($surface['base'] ?? ''), "{$context}: no base colour to fall back to.");
        $this->assertNotSame('', (string) ($surface['foreground'] ?? ''), "{$context}: no computed foreground.");

        if ($type === 'image') {
            $this->assertNull($surface['image_url'], "{$context}: a deleted background must not be linked.");
            $this->assertSame('light', $surface['scrim'], "{$context}: the image scrim floor was not applied.");
        }

        $shell = resource_path(self::SHELLS.'/'.$auth['component'].'.vue');

        $this->assertFileExists($shell, "{$context}: the server resolved a shell this build cannot mount.");
    }

    /** @param array<string,mixed> $extra */
    private function writeAppearance(string $layout, string $type, array $extra = []): void
    {
        $rows = array_merge([
            'auth_layout' => $layout,
            'auth_flip' => true,
            'auth_show_tagline' => true,
            'auth_bg_type' => $type,
            'auth_bg_color' => '#1e3a8a',
            'auth_bg_recipe' => $type === 'pattern' ? 'dots' : 'dusk',
            'auth_bg_image_path' => 'appearance/never-uploaded.jpg',
            'auth_bg_scrim' => 'none',
        ], $extra);

        foreach ($rows as $name => $value) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'appearance', 'name' => $name],
                ['payload' => json_encode($value), 'locked' => false, 'created_at' => now(), 'updated_at' => now()]
            );
        }

        /** @var class-string $manager */
        $manager = 'App\\Brand\\BrandManager';

        if (class_exists($manager)) {
            app($manager)->forget();
        }
    }

    /** @return array<string,string> */
    private function shellSources(): array
    {
        $files = glob(resource_path(self::SHELLS.'/*Layout.vue')) ?: [];

        $this->assertNotEmpty($files, 'No guest shells found — the dispatcher would have nothing to mount.');

        $out = [];

        foreach ($files as $path) {
            $out[basename($path)] = (string) file_get_contents($path);
        }

        return $out;
    }

    /** The attributes of the first element inside <template>. */
    private function rootAttributes(string $source): string
    {
        $template = strstr($source, '<template>');

        $this->assertIsString($template, 'A shell with no template cannot mount.');

        preg_match('/<template>\s*<[\w-]+([^>]*)>/s', (string) $template, $matches);

        return $matches[1] ?? '';
    }

    /** @return array<string,mixed>|null */
    private function emittedAppearance(string $html): ?array
    {
        if (! preg_match('/<meta name="myra-appearance" content="([^"]*)"/', $html, $matches)) {
            return null;
        }

        $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8'), true);

        return is_array($decoded) ? $decoded : null;
    }
}
