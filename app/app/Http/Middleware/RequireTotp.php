<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequireTotp
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return $next($request);
        }

        // Si está en proceso de verificar TOTP
        if ($request->session()->get('totp_pending')) {
            if (! $request->routeIs('totp.challenge', 'totp.challenge.verify', 'logout')) {
                return redirect()->route('totp.challenge');
            }

            return $next($request);
        }

        // Si TOTP está habilitado pero no lo ha verificado en esta sesión
        if ($user->totp_enabled && ! $request->session()->get('totp_verified')) {
            if (! $request->routeIs('totp.challenge', 'totp.challenge.verify', 'logout')) {
                $request->session()->put('totp_pending', true);

                return redirect()->route('totp.challenge');
            }
        }

        // Si aún no tiene TOTP habilitado (aunque exista un secret a medio enrolar), forzar setup
        if (! $user->totp_enabled && ! $request->routeIs('totp.setup', 'totp.setup.confirm', 'logout')) {
            return redirect()->route('totp.setup');
        }

        return $next($request);
    }
}
