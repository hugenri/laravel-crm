<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Crea el catálogo de permisos y los roles del sistema a partir de
     * config/roles.php.
     *
     * Es idempotente: ejecutarlo varias veces no duplica registros.
     *
     * La matriz de config/roles.php es la fuente de verdad, así que los
     * permisos de los roles del sistema se restauran en cada ejecución. Esto
     * es deliberado: un permiso nuevo declarado en la configuración tiene que
     * llegar al administrador sin intervención manual, y ejecutar el seeder es
     * la forma de dejar el sistema en el estado documentado.
     */
    public function run(): void
    {
        $this->createPermissions();
        $this->createRoles();

        // La caché de permisos de Spatie guarda el snapshot leído en cada
        // petición; se invalida para que los cambios de esta ejecución se
        // reflejen de inmediato.
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createPermissions(): void
    {
        foreach (array_keys(config('roles.permissions')) as $name) {
            Permission::findOrCreate($name, 'web');
        }
    }

    private function createRoles(): void
    {
        $all = array_keys(config('roles.permissions'));

        foreach (config('roles.matrix') as $roleName => $granted) {
            $role = Role::findOrCreate($roleName, 'web');

            // "*" significa todos los permisos del catálogo. Se sincroniza
            // siempre para que un permiso nuevo llegue al administrador sin
            // intervención manual.
            $permissions = $granted === ['*'] ? $all : $granted;

            $role->syncPermissions($permissions);
        }
    }
}
