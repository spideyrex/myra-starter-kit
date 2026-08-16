<?php

namespace Tests\Feature\Appearance;

use App\Appearance\AppearanceManager;
use App\Appearance\AuthLayoutRegistry;
use App\Appearance\Background;
use App\Brand\BrandPalette;
use Tests\TestCase;

/**
 * MANDATORY 3 — contrast is a correctness property.
 *
 * THE MECHANISM, stated plainly: a SERVER-COMPUTED foreground (BrandPalette::
 * foregroundOn, a measured WCAG pick between black and white), plus a scrim
 * floor for image-backed surfaces, plus card isolation of the form. NOT a
 * validation refusal — a rule that can reject an appearance can, through a
 * settings import or a seeder, leave an admin staring at a screen they cannot
 * fix — and NOT the client-side isLightColor() heuristic in useThemeColors.ts,
 * which is dead code on exactly the installs that customise their brand and is
 * a perceptual lightness threshold rather than a contrast ratio.
 */
class ContrastGuaranteeTest extends TestCase
{
    use WritesAppearanceSettings;

    private BrandPalette $palette;

    protected function setUp(): void
    {
        parent::setUp();

        AuthLayoutRegistry::flush();

        $this->palette = new BrandPalette('#18181b');
    }

    private function solid(string $hex): Background
    {
        return Background::fromSettings(['auth_bg_type' => 'solid', 'auth_bg_color' => $hex], 'auth');
    }

    public function test_text_is_legible_over_a_light_background(): void
    {
        $background = $this->solid('#ffffff');

        $this->assertSame('#000000', $background->foregroundHex($this->palette));
        $this->assertGreaterThanOrEqual(4.5, $background->contrast($this->palette));
    }

    public function test_text_is_legible_over_a_dark_background(): void
    {
        $background = $this->solid('#0f172a');

        $this->assertSame('#ffffff', $background->foregroundHex($this->palette));
        $this->assertGreaterThanOrEqual(4.5, $background->contrast($this->palette));
    }

    /**
     * The theorem from the spec, made executable: because the pick always takes
     * the better of black and white, the worst achievable ratio is ~4.58:1 at
     * the luminance where the two are equal.
     */
    public function test_every_background_colour_yields_a_legible_foreground(): void
    {
        $hexes = ['#767676', '#777777', '#757575', '#8a8a8a', '#6b6b6b', '#000000', '#ffffff'];

        // A sweep across the full lightness/chroma range.
        foreach ([0x00, 0x3a, 0x76, 0x9a, 0xc8, 0xff] as $r) {
            foreach ([0x00, 0x76, 0xb4, 0xff] as $g) {
                foreach ([0x00, 0x76, 0xff] as $b) {
                    $hexes[] = sprintf('#%02x%02x%02x', $r, $g, $b);
                }
            }
        }

        $hexes = array_values(array_unique($hexes));

        $this->assertGreaterThanOrEqual(64, count($hexes));

        foreach ($hexes as $hex) {
            $background = $this->solid($hex);
            $foreground = $background->foregroundHex($this->palette);

            $this->assertContains($foreground, ['#ffffff', '#000000'], "Foreground on {$hex} was not a pure pick.");
            $this->assertGreaterThanOrEqual(
                4.5,
                $background->contrast($this->palette),
                "Illegible foreground over {$hex}.",
            );
        }
    }

    /** Every type, including image and none, still yields a measured pair. */
    public function test_every_background_type_carries_a_base_and_a_foreground(): void
    {
        foreach (['brand', 'solid', 'gradient', 'pattern', 'image', 'none'] as $type) {
            $background = Background::fromSettings(
                ['auth_bg_type' => $type, 'auth_bg_color' => '#3b0764', 'auth_bg_recipe' => 'dusk'],
                'auth',
            );

            $payload = $background->toArray($this->palette);

            $this->assertStringStartsWith('oklch(', $payload['base'], $type);
            $this->assertStringStartsWith('oklch(', $payload['foreground'], $type);
            $this->assertGreaterThanOrEqual(4.5, $payload['contrast'], $type);
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $background->baseHex($this->palette), $type);
        }
    }

    /** Warn loudly, correct silently: an illegible pairing still saves. */
    public function test_an_illegible_pairing_saves_and_only_warns(): void
    {
        $this->writeAppearance(['auth_bg_type' => 'solid', 'auth_bg_color' => '#fefefe']);

        $this->get('/login')->assertOk();

        $background = app(AppearanceManager::class)->auth()->background;

        $this->assertSame('#fefefe', $background->colorHex);

        // The corrective is the computed foreground, not a refusal to save.
        $this->assertSame('#000000', $background->foregroundHex($this->palette));
        $this->assertGreaterThanOrEqual(
            (float) config('myra.brand.min_contrast', 4.5),
            $background->contrast($this->palette),
        );
    }

    /** Layer 6: the form never leaves bg-card / text-card-foreground. */
    public function test_the_form_is_always_card_isolated(): void
    {
        $shells = glob(resource_path('js/Layouts/Guest/*Layout.vue')) ?: [];

        $this->assertNotEmpty($shells);

        foreach ($shells as $shell) {
            $source = (string) file_get_contents($shell);
            $name = basename($shell);

            $this->assertMatchesRegularExpression(
                '/<Card\b|bg-card/',
                $source,
                "[{$name}] does not isolate the form on a card surface.",
            );
            $this->assertStringContainsString('<slot />', $source, "[{$name}] never renders the form.");
            $this->assertStringContainsString('max-w-md', $source, "[{$name}] narrows the form card.");
        }
    }

    /** Under every layout key and every background type, /login still serves. */
    public function test_the_login_page_survives_every_layout_and_background_pairing(): void
    {
        foreach (AuthLayoutRegistry::ids() as $layout) {
            foreach (['brand', 'solid', 'gradient', 'pattern', 'image', 'none'] as $type) {
                $this->writeAppearance([
                    'auth_layout' => $layout,
                    'auth_bg_type' => $type,
                    'auth_bg_color' => '#3b0764',
                    'auth_bg_recipe' => $type === 'pattern' ? 'dots' : 'dusk',
                ]);

                $payload = $this->appearanceMetaFrom($this->get('/login')->assertOk()->getContent());

                $this->assertSame($type, $payload['auth']['surface']['type'], "{$layout}/{$type}");
                $this->assertGreaterThanOrEqual(4.5, $payload['auth']['surface']['contrast'], "{$layout}/{$type}");
            }
        }
    }
}
