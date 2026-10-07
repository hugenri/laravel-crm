<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Rol de la aplicación. Se extiende el modelo del paquete para que los
 * ajustes (relaciones, casts, borrado) se hagan aquí y no en vendor.
 *
 * @property int $id
 * @property string $name
 * @property string $guard_name
 */
class Role extends SpatieRole
{
    /**
     * Roles declarados en config('roles.system_roles'). El producto los crea y
     * mantiene, así que el módulo de roles los protege frente al borrado.
     */
    public function isSystemRole(): bool
    {
        return in_array($this->name, config('roles.system_roles', []), true);
    }
}
