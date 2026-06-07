<?php

namespace Tests\Feature\Modules;

use App\Settings\AiSettings;
use App\Settings\GeneralSettings;
use Tests\TestCase;

class SettingsModuleTest extends TestCase
{
    public function test_index_requires_permission(): void
    {
        $this->actingAsUser();
        $this->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_super_admin_can_view_settings(): void
    {
        $this->actingAsSuperAdmin();
        $this->get(route('admin.settings.index'))->assertOk();
    }

    public function test_can_update_general_settings_including_login_tagline_and_signup(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(route('admin.settings.update', 'general'), [
            'site_name' => 'Myra Test',
            'login_tagline' => 'Welcome to the test suite',
            'registration_enabled' => false,
        ])->assertRedirect();

        $settings = app(GeneralSettings::class);
        $this->assertSame('Welcome to the test suite', $settings->login_tagline);
        $this->assertFalse($settings->registration_enabled);
    }

    public function test_can_update_ai_settings(): void
    {
        $this->actingAsSuperAdmin();

        $this->put(route('admin.settings.update-ai'), [
            'enabled' => true,
            'provider' => 'anthropic',
            'temperature' => 0.5,
            'max_tokens' => 1000,
        ])->assertRedirect();

        $this->assertSame('anthropic', app(AiSettings::class)->provider);
    }

    public function test_ai_test_connection_requires_permission(): void
    {
        $this->actingAsUser();
        $this->post(route('admin.ai.test-connection'))->assertForbidden();
    }
}
