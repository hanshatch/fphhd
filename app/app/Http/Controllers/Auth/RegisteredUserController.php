<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View|RedirectResponse
    {
        // Solo permitir registro si no existe ningún usuario
        if (User::exists()) {
            return redirect()->route('login')->with('status', 'La aplicación ya está registrada.');
        }

        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // Bloquear si ya hay un usuario registrado
        if (User::exists()) {
            abort(403, 'Registro no permitido.');
        }

        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'string', 'lowercase', 'email', 'max:255'],
            'password' => ['required', 'confirmed', Rules\Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);

        $user = User::create([
            'name'     => $request->name,
            'email'    => $request->email,
            'password' => $request->password, // casted a hashed automáticamente
        ]);

        event(new Registered($user));
        Auth::login($user);

        // Redirigir a configurar TOTP obligatorio
        return redirect()->route('totp.setup');
    }
}
