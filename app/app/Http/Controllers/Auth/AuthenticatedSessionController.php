<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $user = User::where('email', $request->email)->first();

        // Verificar bloqueo de cuenta
        if ($user && $user->isLocked()) {
            AuditLog::record(AuditLog::ACTION_LOGIN_FAIL, ['reason' => 'account_locked', 'email' => $request->email]);

            throw ValidationException::withMessages([
                'email' => "Cuenta bloqueada. Intenta de nuevo en {$user->lockMinutesRemaining()} minuto(s).",
            ]);
        }

        // Intentar autenticar
        if (! Auth::attempt($request->only('email', 'password'), $request->boolean('remember'))) {
            if ($user) {
                $user->recordFailedLogin();

                if ($user->isLocked()) {
                    AuditLog::record(AuditLog::ACTION_ACCOUNT_LOCK, ['email' => $request->email]);
                }
            }

            AuditLog::record(AuditLog::ACTION_LOGIN_FAIL, ['email' => $request->email]);

            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        $user = Auth::user();
        $user->clearFailedLogins();
        $request->session()->regenerate();

        // Si TOTP está habilitado, redirigir a verificación
        if ($user->totp_enabled) {
            $request->session()->put('totp_pending', true);

            return redirect()->route('totp.challenge');
        }

        // Si no tiene TOTP aún, forzar enrolamiento
        if (! $user->totp_enabled && ! $user->totp_secret) {
            return redirect()->route('totp.setup');
        }

        AuditLog::record(AuditLog::ACTION_LOGIN);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record(AuditLog::ACTION_LOGOUT);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
