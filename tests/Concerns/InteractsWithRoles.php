<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

/**
 * Helpers de las pruebas de roles y permisos.
 *
 * Se siembra siempre el catálogo real: con la tabla vacía las policies
 * preguntarían por permisos que no existen y toda aserción sería trivial.
 */
trait InteractsWithRoles
{
    protected function seedRoles(): void
    {
        $this->seed(RolePermissionSeeder::class);
    }

    /**
     * Usuario con exactamente los roles indicados y sin privilegios extra.
     *
     * El correo lo deja la fábrica, que genera uno único, así que un test
     * puede crear todas las cuentas que necesite.
     */
    protected function userWithRoles(array $roles, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'is_active' => true,
            'email_verified_at' => now(),
        ], $attributes));

        $user->syncRoles($roles);

        return $user->fresh();
    }

    protected function role(string $name): Role
    {
        return Role::where('name', $name)->firstOrFail();
    }
}
