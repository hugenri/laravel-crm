<?php

namespace App\Actions\Roles;

use App\Models\Permission;
use App\Models\Role;

class SyncRolePermissions
{
    /**
     * Sustituye los permisos de un rol por los dados.
     *
     * El rol de administrador conserva todo el catalogo siempre: si perdiera
     * roles.update no habria forma de devolverselo desde la interfaz.
     *
     * @param  array<int, int|string>  $permissionIds
     */
    public function handle(Role $role, array $permissionIds): Role
    {
        if ($role->name === config('roles.full_access_role')) {
            $role->syncPermissions(Permission::query()->get());

            return $role->refresh();
        }

        $role->syncPermissions($this->toNames($permissionIds));

        return $role->refresh();
    }

    /**
     * @param  array<int, int|string>  $permissionIds
     * @return array<int, string>
     */
    private function toNames(array $permissionIds): array
    {
        return Permission::query()
            ->whereIn('id', $permissionIds)
            ->pluck('name')
            ->all();
    }
}
