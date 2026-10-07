<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Única fuente del grant temporal de administrador. Cuando entren los
        // roles y permisos, este callback pasará a comprobar el rol asignado;
        // ninguna policy, controller o ruta cambia porque todo pasa por el Gate.
        Gate::before(function (User $user, string $ability) {
            return $this->isBootstrapAdmin($user) ? true : null;
        });
    }

    /**
     * Los administradores bootstrap se declaran por configuración para que la
     * primera cuenta no quede bloqueada antes de que existan los roles.
     */
    private function isBootstrapAdmin(User $user): bool
    {
        return in_array(
            mb_strtolower((string) $user->email),
            array_map('mb_strtolower', config('crm.admin_emails', [])),
            true,
        );
    }
}
