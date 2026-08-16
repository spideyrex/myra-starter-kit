<?php

namespace Tests\Feature\Pwa;

use Tests\TestCase;

/** With myra.pwa.enabled false a v2.5.0 deploy is indistinguishable from v2.4.0. */
class PwaDisabledIsNoOpTest extends TestCase
{
    public function test_no_manifest_link_and_no_theme_color_meta_are_rendered(): void
    {
        config()->set('myra.pwa.enabled', false);
        $this->actingAsSuperAdmin();

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringNotContainsString('rel="manifest"', $html);
        $this->assertStringNotContainsString('name="theme-color"', $html);
    }

    public function test_the_shared_prop_is_false(): void
    {
        config()->set('myra.pwa.enabled', false);
        $this->actingAsSuperAdmin();

        $props = $this->get(route('dashboard'))->assertOk()->viewData('page')['props'];

        $this->assertFalse($props['pwaEnabled']);
    }

    public function test_the_ai_feature_props_are_all_false_by_default(): void
    {
        $this->actingAsSuperAdmin();

        $props = $this->get(route('dashboard'))->assertOk()->viewData('page')['props'];

        $this->assertSame(
            ['filter' => false, 'schema' => false, 'summarise' => false],
            $props['aiFeatures'],
        );
    }

    public function test_no_ai_provider_secret_ever_reaches_an_inertia_prop(): void
    {
        $this->actingAsSuperAdmin();

        $settings = app(\App\Settings\AiSettings::class);
        $settings->api_key = 'sk-sentinel-must-never-leave-the-server';
        $settings->save();
        cache()->forget('ai_enabled');

        $encoded = json_encode($this->get(route('dashboard'))->assertOk()->viewData('page')['props']);

        $this->assertStringNotContainsString('sk-sentinel-must-never-leave-the-server', $encoded);
        $this->assertStringNotContainsString('api_key', $encoded);
    }

    public function test_turning_it_on_renders_the_manifest_and_both_theme_colors(): void
    {
        config()->set('myra.pwa.enabled', true);
        $this->actingAsSuperAdmin();

        $html = $this->get(route('dashboard'))->assertOk()->getContent();

        $this->assertStringContainsString('rel="manifest"', $html);
        $this->assertStringContainsString('(prefers-color-scheme: dark)', $html);
        $this->assertStringContainsString('(prefers-color-scheme: light)', $html);
    }

    public function test_the_static_shell_files_exist_and_carry_no_user_data(): void
    {
        $offline = file_get_contents(public_path('offline.html'));

        $this->assertFileExists(public_path('myra-sw.js'));
        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertStringNotContainsString('@inertia', $offline);
        $this->assertStringNotContainsString('csrf', strtolower($offline));
    }
}
