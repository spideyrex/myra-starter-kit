<?php

namespace Tests\Feature\Brand;

use App\Brand\BrandManager;
use App\Settings\BrandSettings;
use Tests\TestCase;

class BrandManifestRouteTest extends TestCase
{
    use SeedsBrand;

    /**
     * public/manifest.webmanifest is a committed static file, and every real web
     * server (and `php artisan serve`) serves an existing file before it ever
     * reaches PHP. So the branded document must live at a URL no file can
     * occupy, and the rendered page must actually point there.
     */
    public function test_the_branded_manifest_url_cannot_be_shadowed_by_a_static_file(): void
    {
        $this->seedBrand();
        config()->set('myra.pwa.enabled', true);

        $url = route('brand.manifest', [], false);

        $this->assertSame('/brand/manifest.webmanifest', $url);
        $this->assertFileDoesNotExist(public_path(ltrim($url, '/')));
        $this->assertDirectoryDoesNotExist(public_path('brand'));

        $this->actingAsSuperAdmin();
        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('rel="manifest" href="'.url($url).'"', $html);
        $this->assertSame('Acme Corp', $this->get($url)->assertOk()->json('name'));
    }

    public function test_the_etag_is_the_brand_hash_and_a_repeat_request_is_304(): void
    {
        $this->seedBrand();

        $response = $this->get(route('brand.manifest'))->assertOk();
        $etag = $response->headers->get('ETag');

        $this->assertSame('"'.app(BrandManager::class)->hash().'"', $etag);

        $this->withHeaders(['If-None-Match' => $etag])
            ->get(route('brand.manifest'))
            ->assertStatus(304);
    }

    public function test_the_etag_rotates_when_the_brand_changes(): void
    {
        $this->seedBrand();
        $first = $this->get(route('brand.manifest'))->headers->get('ETag');

        $settings = app(BrandSettings::class);
        $settings->name = 'Initech';
        $settings->save();

        app()->forgetInstance(BrandManager::class);

        $second = $this->get(route('brand.manifest'))->headers->get('ETag');

        $this->assertNotSame($first, $second);
    }

    public function test_the_manifest_is_public_on_both_the_branded_and_legacy_urls(): void
    {
        $this->get(route('brand.manifest'))->assertOk();
        $this->get(route('brand.manifest.legacy'))->assertOk();
    }

    public function test_the_icon_route_only_serves_the_two_declared_sizes(): void
    {
        $this->seedBrand();

        $this->get('/brand/icon-192.png')->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get('/brand/icon-512.png')->assertOk();
        $this->get('/brand/icon-64.png')->assertNotFound();
    }

    public function test_the_build_id_folds_in_the_brand_hash(): void
    {
        $this->seedBrand();
        $this->actingAsSuperAdmin();

        $first = $this->get(route('dashboard'))->assertOk()->viewData('page')['props']['buildId'];

        $settings = app(BrandSettings::class);
        $settings->name = 'Initech';
        $settings->save();

        app()->forgetInstance(BrandManager::class);

        $second = $this->get(route('dashboard'))->assertOk()->viewData('page')['props']['buildId'];

        $this->assertNotSame($first, $second);
    }
}
