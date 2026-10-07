<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * Plantilla raíz que se carga en la primera visita.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Versión de los assets.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props que se comparten por defecto.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),

            'appName' => config('app.name'),

            // Solo los atributos que el layout realmente dibuja, así que el
            // usuario nunca se serializa completo en cada respuesta.
            //
            // "permissions" y "roles" vienen de Spatie por el trait HasRoles.
            // Compartir los nombres deja preguntar can('users.view') sin saber
            // de qué rol viene, así que un rol nuevo no toca Vue. El Gate sigue
            // siendo el único límite real: esta prop solo oculta botones que el
            // servidor rechazaría igual.
            'auth' => fn (): array => [
                'user' => $request->user()?->only([
                    'id', 'name', 'email', 'is_active', 'email_verified_at', 'created_at',
                ]),
                'roles' => $request->user()?->getRoleNames()->all() ?? [],
                'permissions' => $request->user()?->getAllPermissions()->pluck('name')->all() ?? [],
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],

            'canRegister' => (bool) config('crm.registration_enabled'),
        ];
    }
}
