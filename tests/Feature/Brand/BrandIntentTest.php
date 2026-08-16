<?php

namespace Tests\Feature\Brand;

use App\Brand\BrandAssetPipeline;
use App\Brand\BrandManager;
use App\Brand\BrandPalette;
use App\Jobs\PublishBrandAssets;
use App\Settings\AppearanceSettings;
use App\Settings\BrandSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The brand must never be silently WRONG. Everything here exercises the real
 * controllers and the real resolver, never a hand-authored token literal.
 */
class BrandIntentTest extends TestCase
{
    use SeedsBrand;

    public function test_an_appearance_save_never_overwrites_a_brand_the_operator_set(): void
    {
        Storage::fake('public');

        $this->seedBrand([
            'logo_path' => 'brand/deadbeef/logo.png',
            'favicon_path' => 'brand/deadbeef/favicon.png',
            'sidebar_background' => '#111827',
        ]);

        $this->actingAsSuperAdmin();

        $this->post(route('admin.settings.update-appearance'), [
            'primary_color' => '#ff0000',
            'theme' => 'red',
            'sidebar_background' => '#00ff00',
            'logo_position' => 'sidebar',
        ])->assertRedirect();

        $brand = app(BrandSettings::class);

        $this->assertSame('#7c3aed', $brand->primary);
        $this->assertSame('violet', $brand->preset);
        $this->assertSame('brand/deadbeef/logo.png', $brand->logo_path);
        $this->assertSame('brand/deadbeef/favicon.png', $brand->favicon_path);
        $this->assertSame('#111827', $brand->sidebar_background);

        // The Appearance group still took the write — the tab is not broken.
        $this->assertSame('#ff0000', app(AppearanceSettings::class)->primary_color);
    }

    public function test_an_appearance_save_may_still_fill_a_brand_slot_that_is_empty(): void
    {
        Storage::fake('public');

        $this->seedBrand(['sidebar_background' => null]);
        $this->actingAsSuperAdmin();

        $this->post(route('admin.settings.update-appearance'), [
            'sidebar_background' => '#0f172a',
        ])->assertRedirect();

        $this->assertSame('#0f172a', app(BrandSettings::class)->sidebar_background);
    }

    public function test_an_appearance_upload_never_replaces_an_existing_brand_logo(): void
    {
        Storage::fake('public');

        $this->seedBrand(['logo_path' => 'brand/deadbeef/logo.png']);
        $this->actingAsSuperAdmin();

        $png = UploadedFile::fake()->image('new.png', 64, 64);

        $this->post(route('admin.settings.update-appearance'), ['logo' => $png])->assertRedirect();

        $appearance = app(AppearanceSettings::class);

        $this->assertNotNull($appearance->logo_path);
        $this->assertSame('brand/deadbeef/logo.png', app(BrandSettings::class)->logo_path);
    }

    public function test_the_palette_preset_actually_drives_the_emitted_tokens(): void
    {
        $this->seedBrand(['preset' => 'blue', 'primary' => BrandPalette::PRESETS['blue']]);

        $blue = app(BrandManager::class)->current()->cssVariables(false);

        $this->seedBrand(['preset' => 'green', 'primary' => BrandPalette::PRESETS['green']]);
        app()->forgetInstance(BrandManager::class);

        $green = app(BrandManager::class)->current()->cssVariables(false);

        $this->assertNotSame($blue['--primary'], $green['--primary']);
        $this->assertSame(BrandPalette::PRESET_TOKENS['blue']['light']['--primary'], $blue['--primary']);
        $this->assertSame(BrandPalette::PRESET_TOKENS['green']['light']['--primary'], $green['--primary']);
    }

    public function test_choosing_a_preset_through_the_real_endpoint_changes_the_preview_tokens(): void
    {
        $this->seedBrand();
        $this->actingAsSuperAdmin();

        $base = ['name' => 'Acme Corp', 'enabled' => true];

        $violet = $this->postJson(route('admin.brand.preview'), $base + [
            'preset' => 'violet',
            'primary' => BrandPalette::PRESETS['violet'],
        ])->assertOk()->json('light.--primary');

        $yellow = $this->postJson(route('admin.brand.preview'), $base + [
            'preset' => 'yellow',
            'primary' => BrandPalette::PRESETS['yellow'],
        ])->assertOk()->json('light.--primary');

        $this->assertNotSame($violet, $yellow);
    }

    public function test_an_explicit_primary_still_beats_the_preset(): void
    {
        // The seeded brand is preset=violet with a primary that is NOT violet's.
        $this->seedBrand();

        $palette = app(BrandManager::class)->current()->palette;

        $this->assertSame('#7c3aed', $palette->primaryHex);
        $this->assertFalse($palette->usesPresetPalette());
    }

    public function test_the_preset_supplies_the_primary_when_none_was_ever_chosen(): void
    {
        $this->seedBrand(['preset' => 'orange', 'primary' => '']);

        $this->assertSame(
            BrandPalette::PRESETS['orange'],
            app(BrandManager::class)->current()->palette->primaryHex,
        );
    }

    public function test_the_icon_set_is_derived_once_and_the_mark_wins_over_the_favicon(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to render brand derivatives.');
        }

        Storage::fake('public');

        $pipeline = app(BrandAssetPipeline::class);

        $mark = $pipeline->store($this->upload('mark.png', 256, 220, 20, 20), 'mark');
        $favicon = $pipeline->store($this->upload('favicon.png', 64, 20, 20, 220), 'favicon');

        $this->seedBrand(['mark_path' => $mark, 'favicon_path' => $favicon]);

        (new PublishBrandAssets())->handle($pipeline, app(BrandManager::class));

        $fromJob = $pipeline->derivativeBytes('icon-512');

        $fromMark = Storage::disk('public')->get($pipeline->derive('mark', $mark)['icon-512']);
        $fromFavicon = Storage::disk('public')->get($pipeline->derive('mark', $favicon)['icon-512']);

        $this->assertNotNull($fromJob);
        $this->assertNotSame($fromMark, $fromFavicon, 'the two sources must be distinguishable');
        $this->assertSame($fromMark, $fromJob, 'the favicon slot overwrote the mark-derived icon set');
    }

    private function upload(string $name, int $size, int $r, int $g, int $b): UploadedFile
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, $r, $g, $b));

        ob_start();
        imagepng($canvas);
        $bytes = (string) ob_get_clean();
        imagedestroy($canvas);

        return UploadedFile::fake()->createWithContent($name, $bytes);
    }

    public function test_the_derivative_prefix_does_not_move_when_an_unrelated_setting_changes(): void
    {
        if (! function_exists('imagecreatetruecolor')) {
            $this->markTestSkipped('GD is required to render brand derivatives.');
        }

        Storage::fake('public');

        $this->seedBrand();

        $pipeline = app(BrandAssetPipeline::class);
        (new PublishBrandAssets())->handle($pipeline, app(BrandManager::class));

        $before = $pipeline->iconManifest();
        $this->assertArrayHasKey('icon-192', $before);

        $seo = app(\App\Settings\SeoSettings::class);
        $seo->meta_description = 'A completely unrelated description.';
        $seo->save();

        app()->forgetInstance(BrandManager::class);

        $after = app(BrandAssetPipeline::class)->iconManifest();

        $this->assertSame($before, $after, 'An SEO edit must not orphan every rendered icon.');
    }
}
