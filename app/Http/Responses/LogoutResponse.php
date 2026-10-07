<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;

/**
 * Devuelve al usuario a la pantalla de login con un aviso explícito, para que
 * el fin de la sesión no se confunda con un fallo de la petición.
 */
class LogoutResponse implements LogoutResponseContract
{
    public function toResponse($request)
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        return redirect()
            ->route('login')
            ->with('success', __('auth.logged_out'));
    }
}
