<?php

namespace Tests\Feature\Brand;

use Tests\TestCase;

/** admin_email, site_url and timezone used to ship to every guest on /login. */
class SiteSettingsLeakTest extends TestCase
{
    public function test_a_guest_request_carries_no_operational_settings(): void
    {
        $props = $this->get('/login')->assertOk()->viewData('page')['props'];

        $site = $props['siteSettings'] ?? [];

        foreach (['admin_email', 'site_url', 'timezone'] as $key) {
            $this->assertArrayNotHasKey($key, $site, $key.' must not reach an unauthenticated guest');
        }
    }

    public function test_the_public_identity_keys_are_still_present(): void
    {
        $site = $this->get('/login')->assertOk()->viewData('page')['props']['siteSettings'];

        $this->assertArrayHasKey('site_name', $site);
        $this->assertArrayHasKey('primary_color', $site);
        $this->assertArrayHasKey('logo_position', $site);
    }

    public function test_the_brand_prop_is_a_curated_projection(): void
    {
        $brand = $this->get('/login')->assertOk()->viewData('page')['props']['brand'];

        $this->assertArrayHasKey('name', $brand);
        $this->assertArrayHasKey('initial', $brand);
        $this->assertArrayNotHasKey('admin_email', $brand);
        $this->assertArrayNotHasKey('site_url', $brand);
        $this->assertArrayNotHasKey('timezone', $brand);
    }
}
