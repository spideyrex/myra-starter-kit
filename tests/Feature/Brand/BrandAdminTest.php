<?php

namespace Tests\Feature\Brand;

use App\Settings\BrandSettings;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BrandAdminTest extends TestCase
{
    use SeedsBrand;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'enabled' => true,
            'name' => 'Initech',
            'short_name' => 'Init',
            'tagline' => 'Ship it',
            'description' => 'Console',
            'primary' => '#0ea5e9',
            'accent' => null,
            'sidebar_background' => null,
            'sidebar_foreground' => null,
            'sidebar_accent' => null,
            'preset' => 'blue',
            'font_sans' => 'inter',
            'font_mono' => 'ui-monospace',
            'radius' => '0.5rem',
            'dark_default' => false,
            'logo_position' => 'sidebar',
        ], $overrides);
    }

    public function test_the_page_is_gated_by_brand_view(): void
    {
        $this->actingAsUser();

        $this->get(route('admin.brand.index'))->assertForbidden();
    }

    public function test_a_super_admin_can_open_the_page(): void
    {
        $this->actingAsSuperAdmin();

        $this->get(route('admin.brand.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Brand/Index')->has('tokens')->has('contrast'));
    }

    public function test_update_persists_and_takes_effect_immediately(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(route('admin.brand.update'), $this->payload())->assertRedirect();

        $this->assertSame('Initech', app(BrandSettings::class)->name);
        $this->assertStringContainsString('Initech', $this->get('/')->assertOk()->getContent());
    }

    public function test_update_is_gated_by_brand_update(): void
    {
        $user = $this->actingAsUser();
        $user->givePermissionTo('brand.view');

        $this->put(route('admin.brand.update'), $this->payload())->assertForbidden();
    }

    public function test_an_unknown_preset_or_font_is_refused(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(route('admin.brand.update'), $this->payload(['font_sans' => 'comic-sans']))
            ->assertSessionHasErrors('font_sans');

        $this->put(route('admin.brand.update'), $this->payload(['primary' => 'rgb(1,2,3)']))
            ->assertSessionHasErrors('primary');
    }

    public function test_preview_returns_real_tokens_and_persists_nothing(): void
    {
        $this->seedBrand();
        $this->actingAsSuperAdmin();

        $response = $this->postJson(route('admin.brand.preview'), ['primary' => '#ff0000', 'name' => 'Draft'])
            ->assertOk();

        $this->assertSame('#ff0000', $response->json('tokens.palette.primary'));
        $this->assertStringStartsWith('oklch(', $response->json('light.--primary'));
        $this->assertArrayHasKey('primary', $response->json('contrast'));

        $this->assertSame('Acme Corp', app(BrandSettings::class)->name);
        $this->assertNotSame('#ff0000', app(BrandSettings::class)->primary);
    }

    public function test_an_unknown_asset_slot_is_404(): void
    {
        Storage::fake('public');
        $this->actingAsSuperAdmin();

        $this->delete(route('admin.brand.asset.destroy', ['slot' => 'wallpaper']))->assertNotFound();
    }

    public function test_the_offline_shell_renders_from_the_brand_view(): void
    {
        $this->seedBrand();

        $html = view('brand.offline', ['brand' => app(\App\Brand\BrandManager::class)->current()])->render();

        $this->assertStringContainsString('Acme Corp', $html);
        $this->assertStringContainsString('#7c3aed', $html);
        $this->assertStringNotContainsString('Myra', $html);
    }

    public function test_the_asset_derivatives_job_never_throws_without_a_source_image(): void
    {
        Storage::fake('public');
        $this->seedBrand();

        (new \App\Jobs\PublishBrandAssets())->handle(
            app(\App\Brand\BrandAssetPipeline::class),
            app(\App\Brand\BrandManager::class),
        );

        $icons = app(\App\Brand\BrandAssetPipeline::class)->iconManifest();

        $this->assertArrayHasKey('icon-192', $icons);
        $this->assertArrayHasKey('logo-email', $icons);
    }

    public function test_reset_switches_the_manager_off(): void
    {
        $this->seedBrand();
        $this->actingAsSuperAdmin();

        $this->post(route('admin.brand.reset'))->assertRedirect();

        $this->assertFalse(app(BrandSettings::class)->enabled);
    }
}
