<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Tests\TestCase;

class AccountStatusTest extends TestCase
{
    public function test_active_user_can_login(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticated();
    }

    public function test_suspended_user_cannot_login(): void
    {
        $user = User::factory()->suspended()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_pending_user_cannot_login(): void
    {
        $user = User::factory()->pending()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertGuest();
    }

    public function test_suspending_a_user_blocks_their_next_request(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/dashboard')->assertOk();

        $user->update(['status' => 'suspended']);

        $this->get('/dashboard')->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
