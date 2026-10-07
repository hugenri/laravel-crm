<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;

/**
 * Autorización de la administración de roles y permisos.
 *
 * Como UserPolicy, solo responde si el permiso existe. La regla que protege
 * un rol de borrarse vive en App\Actions\Roles\DeleteRole, para que aplique
 * venga por donde venga el borrado.
 */
class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        return $user->can('roles.delete');
    }
}
