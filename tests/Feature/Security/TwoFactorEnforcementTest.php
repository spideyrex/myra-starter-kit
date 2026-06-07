<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Tests\TestCase;

class TwoFactorEnforcementTest extends TestCase
{
    public function test_non_two_factor_user_reaches_dashboard_after_login(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_two_factor_user_is_redirected_to_challenge_after_login(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);

        $response = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response->assertRedirect(route('two-factor.challenge'));
    }

    public function test_two_factor_user_cannot_reach_dashboard_without_passing_challenge(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('two-factor.challenge'));
    }

    public function test_two_factor_user_reaches_dashboard_once_challenge_passed(): void
    {
        $user = User::factory()->create(['two_factor_confirmed_at' => now()]);

        $response = $this->withSession(['two_factor_confirmed' => true])
            ->actingAs($user)
            ->get('/dashboard');

        $response->assertOk();
    }
}
