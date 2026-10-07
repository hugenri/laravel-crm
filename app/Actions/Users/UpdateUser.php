<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UpdateUser
{
    /**
     * La contrasena solo se escribe si llega nueva, asi que editar el nombre
     * nunca resetea la credencial.
     *
     * @param  array{name?: string, email?: string, password?: ?string, is_active?: bool, email_verified?: bool}  $data
     */
    public function handle(User $user, array $data): User
    {
        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        if (array_key_exists('email', $data)) {
            $user->email = mb_strtolower($data['email']);
        }

        if (array_key_exists('is_active', $data)) {
            $user->is_active = $data['is_active'];
        }

        if (array_key_exists('email_verified', $data)) {
            // Si ya estaba verificada se conserva el timestamp original, no se
            // vuelve a sellar en cada guardado.
            $user->email_verified_at = $data['email_verified']
                ? $user->email_verified_at ?? now()
                : null;
        }

        if (filled($data['password'] ?? null)) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return $user->refresh();
    }
}
