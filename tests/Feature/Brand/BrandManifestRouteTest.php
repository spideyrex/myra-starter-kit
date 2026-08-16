<?php

namespace Tests\Feature\Brand;

use App\Brand\BrandManager;
use App\Settings\BrandSettings;
use Tests\TestCase;

class BrandManifestRouteTest extends TestCase
{
    use SeedsBrand;

    public function test_the_etag_is_the_brand_hash_and_a_repeat_request_is_304(): void
    {
        $this->seedBrand();

        $response = $this->get('/manifest.webmanifest')->assertOk();
        $etag = $response->headers->get('ETag');

        $this->assertSame('"'.app(BrandManager::class)->hash().'"', $etag);

        $this->withHeaders(['If-None-Match' => $etag])
            ->get('/manifest.webmanifest')
            ->assertStatus(304);
    }

    public function test_the_etag_rotates_when_the_brand_changes(): void
    {
        $this->seedBrand();
        $first = $this->get('/manifest.webmanifest')->headers->get('ETag');

        $settings = app(BrandSettings::class);
        $settings->name = 'Initech';
        $settings->save();

        app()->forgetInstance(BrandManager::class);

        $second = $this->get('/manifest.webmanifest')->headers->get('ETag');

        $this->assertNotSame($first, $second);
    }

    public function test_the_manifest_is_public(): void
    {
        $this->get('/manifest.webmanifest')->assertOk();
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
