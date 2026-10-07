<?php

namespace App\Actions\Roles;

use App\Models\Role;
use DomainException;

class DeleteRole
{
    /**
     * Borrar un rol es irreversible para las cuentas que lo usan, asi que las
     * reglas viven aqui y no en el controlador: no se borran los roles del
     * producto (config('roles.system_roles')) ni los que siguen asignados.
     *
     * @throws DomainException
     */
    public function handle(Role $role): void
    {
        if ($role->isSystemRole()) {
            throw new DomainException(__('roles.system_role_protected'));
        }

        if ($role->users()->exists()) {
            throw new DomainException(__('roles.role_in_use'));
        }

        $role->delete();
    }
}
