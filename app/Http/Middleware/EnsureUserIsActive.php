<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Rechaza peticiones de cuentas desactivadas. Se comprueba en cada
     * petición autenticada para que revocar el acceso surta efecto de
     * inmediato, sin esperar a que expire la sesión.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && ! $user->isActive()) {
            Auth::guard('web')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                abort(403, __('auth.account_inactive'));
            }

            return redirect()
                ->route('login')
                ->withErrors(['email' => __('auth.account_inactive')]);
        }

        return $next($request);
    }
}
