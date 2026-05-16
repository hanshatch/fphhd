<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_are_redirected_to_totp_setup_after_login_without_totp(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('totp.setup'));
    }

    public function test_users_with_totp_enabled_are_redirected_to_challenge(): void
    {
        $user = User::factory()->create([
            'totp_secret'  => 'JBSWY3DPEHPK3PXP',
            'totp_enabled' => true,
        ]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('totp.challenge'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email'    => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_failed_login_increments_attempts(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $this->assertEquals(1, $user->fresh()->failed_login_attempts);
    }

    public function test_account_is_locked_after_five_failed_attempts(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->assertTrue($user->fresh()->isLocked());
    }

    public function test_locked_account_cannot_login(): void
    {
        $user = User::factory()->create([
            'failed_login_attempts' => 5,
            'locked_until'          => now()->addMinutes(15),
        ]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('email');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->withSession(['totp_verified' => true])
            ->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
