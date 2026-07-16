<?php

namespace Tests\Traits;

use App\Models\User;

trait WithTotpSession
{
    /**
     * Simula un usuario autenticado con TOTP configurado y verificado en sesión.
     * Evita que el middleware RequireTotp redirija durante los tests.
     */
    protected function actingAsVerified(User $user): static
    {
        $user->forceFill([
            'totp_secret'  => 'JBSWY3DPEHPK3PXP',
            'totp_enabled' => true,
        ])->save();

        return $this
            ->actingAs($user)
            ->withSession(['totp_verified' => true]);
    }
}
