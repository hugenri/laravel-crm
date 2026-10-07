<?php

namespace App\Actions\Users;

use App\Models\User;

class CreateUser
{
    /**
     * La cuenta nueva recibe el rol por defecto de config('roles.default_role')
     * para no quedar sin permisos; si el administrador elige otros, se pasan y
     * lo sobrescriben.
     *
     * @param  array{name: string, email: string, password: string, is_active: bool, email_verified: bool}  $data
     * @param  array<int, int|string>|null  $roleIds
     */
    public function handle(array $data, ?array $roleIds = null): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower($data['email']),
            'password' => $data['password'],
            'is_active' => $data['is_active'],
            'email_verified_at' => $data['email_verified'] ? now() : null,
        ]);

        $user->syncRoles($roleIds ?? [config('roles.default_role')]);

        return $user;
    }
}
