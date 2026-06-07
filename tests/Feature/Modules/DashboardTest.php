<?php

namespace Tests\Feature\Modules;

use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_authenticated_active_user_can_view_dashboard(): void
    {
        $this->actingAsUser();
        $this->get('/dashboard')->assertOk();
    }
}
