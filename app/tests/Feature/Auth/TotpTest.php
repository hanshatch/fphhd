<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class TotpTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_with_partial_enrollment_cannot_access_dashboard(): void
    {
        // Estado "a medias": secret persistido pero nunca confirmado (bug histórico)
        $user = User::factory()->create([
            'totp_secret'  => 'JBSWY3DPEHPK3PXP',
            'totp_enabled' => false,
        ]);

        $response = $this->actingAs($user)->get('/dashboard');

        $response->assertRedirect(route('totp.setup'));
    }

    public function test_login_without_totp_enabled_always_redirects_to_setup(): void
    {
        $user = User::factory()->create([
            'totp_secret'  => 'JBSWY3DPEHPK3PXP',
            'totp_enabled' => false,
        ]);

        $response = $this->post('/login', [
            'email'    => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect(route('totp.setup'));
    }

    public function test_setup_does_not_persist_secret_until_confirmed(): void
    {
        $user = User::factory()->create([
            'totp_secret'  => null,
            'totp_enabled' => false,
        ]);

        $this->actingAs($user)->get(route('totp.setup'))->assertOk();

        $this->assertNull($user->fresh()->totp_secret);
    }

    public function test_setup_confirm_with_valid_code_enables_totp(): void
    {
        $user = User::factory()->create([
            'totp_secret'  => null,
            'totp_enabled' => false,
        ]);

        $this->actingAs($user)->get(route('totp.setup'));

        $secret = session('totp_pending_secret');
        $this->assertNotNull($secret);

        $code = (new Google2FA())->getCurrentOtp($secret);

        $response = $this->actingAs($user)->post(route('totp.setup.confirm'), ['code' => $code]);

        $response->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertTrue($user->totp_enabled);
        $this->assertSame($secret, $user->totp_secret);
    }

    public function test_totp_secret_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create([
            'totp_secret'  => 'JBSWY3DPEHPK3PXP',
            'totp_enabled' => true,
        ]);

        $raw = $user->getRawOriginal('totp_secret');

        $this->assertNotSame('JBSWY3DPEHPK3PXP', $raw);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->totp_secret);
    }

    public function test_legacy_plaintext_secret_forces_re_enrollment_instead_of_500(): void
    {
        $user = User::factory()->create(['totp_enabled' => true]);

        // Simular secret legado guardado en texto plano (previo al cast encrypted)
        \Illuminate\Support\Facades\DB::table('users')
            ->where('id', $user->id)
            ->update(['totp_secret' => 'JBSWY3DPEHPK3PXP']);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $response = $this->post(route('totp.challenge.verify'), ['code' => '123456']);

        $response->assertRedirect(route('totp.setup'));

        $user->refresh();
        $this->assertFalse($user->totp_enabled);
        $this->assertNull($user->totp_secret);
    }

    public function test_totp_challenge_is_rate_limited(): void
    {
        $user = User::factory()->create([
            'totp_secret'  => 'JBSWY3DPEHPK3PXP',
            'totp_enabled' => true,
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('totp.challenge.verify'), ['code' => '000000']);
        }

        $response = $this->post(route('totp.challenge.verify'), ['code' => '000000']);

        $response->assertStatus(429);
    }
}
