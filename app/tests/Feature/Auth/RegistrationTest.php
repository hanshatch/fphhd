<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered_when_no_users_exist(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_registration_is_blocked_if_user_already_exists(): void
    {
        User::factory()->create();

        $response = $this->get('/register');

        $response->assertRedirect(route('login'));
    }

    public function test_new_user_can_register_and_is_redirected_to_totp_setup(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Hans Hatch',
            'email'                 => 'hans@hatch.mx',
            'password'              => 'Segura#2026!',
            'password_confirmation' => 'Segura#2026!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('totp.setup'));
    }

    public function test_registration_fails_if_password_is_weak(): void
    {
        $response = $this->post('/register', [
            'name'                  => 'Hans Hatch',
            'email'                 => 'hans@hatch.mx',
            'password'              => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertGuest();
        $response->assertSessionHasErrors('password');
    }
}
