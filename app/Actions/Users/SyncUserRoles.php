<?php

namespace App\Actions\Users;

use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Support\Collection;

class SyncUserRoles
{
    /**
     * Sustituye los roles de una cuenta por los dados.
     *
     * El permiso ya lo comprobó el form request. Aqui vive la regla que
     * protege el sistema: nadie puede editar sus propios roles, igual que no
     * puede desactivarse ni borrarse a si mismo, para que el ultimo
     * administrador no se quite el rol y deje sin nadie que lo restaure.
     *
     * @param  array<int, int|string>  $roleIds
     *
     * @throws DomainException
     */
    public function handle(User $actor, User $target, array $roleIds): User
    {
        if ($actor->is($target)) {
            throw new DomainException(__('users.cannot_change_own_roles'));
        }

        $target->syncRoles($this->toNames($roleIds));

        return $target->refresh();
    }

    /**
     * Acepta identificadores y nombres de rol, y descarta lo que no exista
     * para que un formulario viejo no adjunte un rol borrado mientras estaba
     * abierto.
     *
     * @param  array<int, int|string>  $roleIds
     * @return array<int, string>
     */
    private function toNames(array $roleIds): array
    {
        if (array_filter($roleIds, 'is_string') === []) {
            return Role::query()->whereIn('id', $roleIds)->pluck('name')->all();
        }

        return (new Collection($roleIds))
            ->map(fn ($role) => $role instanceof Role ? $role->name : (string) $role)
            ->filter(fn (string $name) => Role::where('name', $name)->exists())
            ->values()
            ->all();
    }
}
