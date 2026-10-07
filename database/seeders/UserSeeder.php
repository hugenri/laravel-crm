<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Cuentas de demostración de la instalación.
     *
     * Es idempotente: usa updateOrCreate en lugar de create, de modo que
     * ejecutarlo varias veces actualiza las cuentas en lugar de chocar contra
     * la restricción de email único.
     */
    public function run(): void
    {
        // El administrador bootstrap conserva el acceso concedido por
        // AppServiceProvider y además recibe el rol Administrador, de modo que
        // su comportamiento no depende de la lista CRM_ADMIN_EMAILS y puede
        // delegarse a otra cuenta sin romper la instalación.
        foreach (config('crm.admin_emails') as $email) {
            $admin = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => 'Administrador',
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'is_active' => true,
                ],
            );

            $admin->syncRoles('Administrador');
        }

        $demo = User::query()->updateOrCreate(
            ['email' => 'demo@crm.test'],
            [
                'name' => 'Usuario Demo',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => true,
            ],
        );

        $demo->syncRoles('Vendedor');

        // Se conserva la cuenta desactivada de la primera fase: verifica que
        // el rechazo de usuarios inactivos sigue funcionando con roles.
        $inactive = User::query()->updateOrCreate(
            ['email' => 'inactivo@crm.test'],
            [
                'name' => 'Usuario Desactivado',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'is_active' => false,
            ],
        );

        $inactive->syncRoles('Vendedor');
    }
}
