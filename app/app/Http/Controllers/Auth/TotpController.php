<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

class TotpController extends Controller
{
    private Google2FA $google2fa;

    public function __construct()
    {
        $this->google2fa = new Google2FA();
    }

    // ── Setup (enrolamiento inicial) ──────────────────────────────────────────

    public function setupShow(Request $request): View|RedirectResponse
    {
        $user = Auth::user();

        if ($user->totp_enabled) {
            return redirect()->route('dashboard');
        }

        // El secret pendiente vive SOLO en sesión hasta que el código se confirme;
        // así nunca queda un enrolamiento a medias persistido en la base.
        $secret = $request->session()->get('totp_pending_secret');

        if (! $secret) {
            $secret = $this->google2fa->generateSecretKey();
            $request->session()->put('totp_pending_secret', $secret);
        }

        $qrCodeUrl = $this->google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        return view('auth.totp-setup', [
            'secret'    => $secret,
            'qrCodeUrl' => $qrCodeUrl,
        ]);
    }

    public function setupConfirm(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Ingresa el código de 6 dígitos.',
            'code.digits'   => 'El código debe tener exactamente 6 dígitos.',
        ]);

        $user   = Auth::user();
        $secret = $request->session()->get('totp_pending_secret');

        if (! $secret) {
            return redirect()->route('totp.setup');
        }

        $valid = $this->google2fa->verifyKey($secret, $request->code, 1);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'Código incorrecto. Verifica la hora de tu dispositivo.',
            ]);
        }

        $user->forceFill([
            'totp_secret'  => $secret,
            'totp_enabled' => true,
        ])->save();

        $request->session()->forget('totp_pending_secret');
        $request->session()->put('totp_verified', true);

        AuditLog::record(AuditLog::ACTION_TOTP_ENABLE);

        return redirect()->route('dashboard')->with('status', '2FA activado correctamente.');
    }

    // ── Challenge (verificación en cada login) ────────────────────────────────

    public function challengeShow(Request $request): View|RedirectResponse
    {
        if (! $request->session()->get('totp_pending')) {
            return redirect()->route('dashboard');
        }

        return view('auth.totp-challenge');
    }

    public function challengeVerify(Request $request): RedirectResponse
    {
        if (! $request->session()->get('totp_pending')) {
            return redirect()->route('dashboard');
        }

        $request->validate([
            'code' => ['required', 'digits:6'],
        ], [
            'code.required' => 'Ingresa el código de 6 dígitos.',
            'code.digits'   => 'El código debe tener exactamente 6 dígitos.',
        ]);

        $user = Auth::user();

        try {
            $secret = $user->totp_secret;
        } catch (DecryptException) {
            // Secret legado en texto plano (previo al cast 'encrypted'):
            // se resetea el 2FA y se fuerza un nuevo enrolamiento.
            $user->forceFill(['totp_secret' => null, 'totp_enabled' => false])->save();
            $request->session()->forget('totp_pending');

            return redirect()->route('totp.setup')
                ->with('status', 'Tu 2FA necesita reconfigurarse. Escanea el código de nuevo.');
        }

        $valid = $this->google2fa->verifyKey($secret, $request->code, 1);

        if (! $valid) {
            throw ValidationException::withMessages([
                'code' => 'Código incorrecto. Intenta de nuevo.',
            ]);
        }

        $request->session()->forget('totp_pending');
        $request->session()->put('totp_verified', true);

        AuditLog::record(AuditLog::ACTION_LOGIN);

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
