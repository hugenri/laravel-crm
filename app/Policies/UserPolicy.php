<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autorización de la administración de usuarios.
 *
 * Dos responsabilidades separadas a propósito:
 *
 *  1. PERMISO (esta clase): responde "¿tiene permiso para…?" con los permisos
 *     que vienen de los roles. Una habilidad equivale a un permiso de
 *     config/roles.php.
 *
 *  2. REGLAS DE NEGOCIO (App\Actions\Users\ChangeUserStatus, DeleteUser y las
 *     form requests): responden "¿se puede operar sobre ESTE usuario?", como
 *     no desactivarte a ti mismo o al último activo. Fuera de la policy para
 *     que cambiar permisos nunca relaje una regla de seguridad y para que una
 *     futura API reutilice las mismas reglas.
 *
 * El administrador de config('crm.admin_emails') la salta con el Gate::before
 * de AppServiceProvider.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view');
    }

    public function view(User $user, User $model): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $model): bool
    {
        return $user->can('users.update');
    }

    public function delete(User $user, User $model): bool
    {
        return $user->can('users.delete');
    }

    public function changeStatus(User $user, User $model): bool
    {
        return $user->can('users.change_status');
    }

    /**
     * Asignar roles es la única forma de escalar privilegios, así que tiene su
     * permiso propio en vez de colgarse de users.update.
     */
    public function assignRoles(User $user, User $model): bool
    {
        return $user->can('users.assign_roles');
    }
}
