<?php

namespace Tests\Feature\Security;

use App\Settings\GeneralSettings;
use Tests\TestCase;

class RegistrationToggleTest extends TestCase
{
    public function test_registration_open_by_default(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_registration_get_redirects_when_disabled(): void
    {
        $this->disableRegistration();

        $this->get('/register')->assertRedirect(route('login'));
    }

    public function test_registration_post_forbidden_when_disabled(): void
    {
        $this->disableRegistration();

        $response = $this->post('/register', [
            'name' => 'Blocked User',
            'email' => 'blocked@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertForbidden();
        $this->assertGuest();
    }

    private function disableRegistration(): void
    {
        $settings = app(GeneralSettings::class);
        $settings->registration_enabled = false;
        $settings->save();
    }
}
